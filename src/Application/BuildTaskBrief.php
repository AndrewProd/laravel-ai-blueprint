<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Application\Workflows\BuiltInWorkflows;
use AndrewProd\AiBlueprint\Contracts\WorkflowProvider;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;
use AndrewProd\AiBlueprint\Domain\Tasks\TaskBrief;
use AndrewProd\AiBlueprint\Domain\Tasks\TaskDefinition;
use AndrewProd\AiBlueprint\Domain\Tasks\TaskType;
use AndrewProd\AiBlueprint\Generators\TaskBrief\TaskBriefGenerator;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\UnsafePath;
use AndrewProd\AiBlueprint\Support\Paths;
use AndrewProd\AiBlueprint\Support\Str;
use InvalidArgumentException;

/**
 * Creates docs/tasks/<slug>/{task,context,checklist}.md. Task briefs are owned by
 * the developer after creation: they are not tracked in the manifest and an
 * existing brief is never overwritten without $force.
 */
final class BuildTaskBrief
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly WorkflowProvider $workflows = new BuiltInWorkflows,
        private readonly TaskBriefGenerator $generator = new TaskBriefGenerator,
    ) {}

    /**
     * @throws InvalidArgumentException for invalid input
     * @throws InvalidBlueprint when no usable blueprint exists
     */
    public function prepare(string $title, string $type, ?string $outputDirectory, string $agent, string $description = ''): TaskBrief
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new InvalidArgumentException('Task title must be between 1 and 200 characters.');
        }

        $taskType = TaskType::tryFrom($type) ?? throw new InvalidArgumentException(
            "Unknown task type [{$type}]. Available: ".implode(', ', TaskType::values()).'.'
        );

        if (! in_array($agent, ['generic', 'codex', 'claude', 'cursor'], true)) {
            throw new InvalidArgumentException("Unknown adapter [{$agent}]. Available: generic, codex, claude, cursor.");
        }

        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = 'task-'.substr(hash('sha256', $title), 0, 8);
        }
        $slug = substr($slug, 0, 80);

        $blueprint = $this->workspace->store->loadBlueprint();
        $context = $this->workspace->artifactContext($this->outputDirectory($outputDirectory));

        return $this->generator->build(
            new TaskDefinition($title, $slug, $taskType, trim($description)),
            $this->workflows->get($taskType->value),
            $blueprint,
            $context,
            $agent,
        );
    }

    public function directoryFor(TaskBrief $brief, ?string $outputDirectory): string
    {
        return $this->outputDirectory($outputDirectory).'/'.$brief->task->slug;
    }

    /**
     * @return list<string> written paths
     *
     * @throws UnsafePath
     * @throws \RuntimeException when the brief exists and $force is false
     */
    public function write(TaskBrief $brief, ?string $outputDirectory, bool $force = false): array
    {
        $directory = $this->directoryFor($brief, $outputDirectory);

        if (! $force) {
            foreach (array_keys($brief->files) as $file) {
                if ($this->workspace->writer->exists($directory.'/'.$file)) {
                    throw new \RuntimeException("Task brief [{$directory}] already exists. Use --force to overwrite it or choose another title.");
                }
            }
        }

        $written = [];
        foreach ($brief->files as $file => $content) {
            $this->workspace->writer->write($directory.'/'.$file, $content);
            $written[] = $directory.'/'.$file;
        }

        return $written;
    }

    private function outputDirectory(?string $option): string
    {
        if ($option === null || $option === '') {
            return $this->workspace->config->tasksDirectory;
        }
        if (Paths::isAbsolute($option)) {
            throw new InvalidArgumentException('--output must be a path relative to the project root.');
        }

        try {
            $normalized = Paths::normalize($option);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('--output must not point outside the project root.');
        }

        if ($normalized === '') {
            throw new InvalidArgumentException('--output must not be the project root.');
        }

        return $normalized;
    }
}

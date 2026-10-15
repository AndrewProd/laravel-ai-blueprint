<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Contracts\RequiresValidBlueprint;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;
use AndrewProd\AiBlueprint\Domain\Blueprint\ProjectInfo;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\CheckResult;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;
use AndrewProd\AiBlueprint\Support\Paths;

/**
 * Runs the built-in deterministic rules. Never runs project commands.
 */
final class CheckProject
{
    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @param  list<string>|null  $changedFiles  restrict file-specific violations to these paths
     */
    public function run(?array $changedFiles = null): CheckResult
    {
        $violations = [];
        $blueprint = null;

        try {
            $blueprint = $this->workspace->store->loadBlueprint();
        } catch (InvalidBlueprint $e) {
            foreach ($e->errors as $error) {
                $violations[] = new Violation($e->ruleId, Severity::Error, $error, $this->workspace->store->blueprintPath(), 'Run `php artisan ai-blueprint:scan` to regenerate it.');
            }
        }

        $context = $this->context($blueprint);
        $rulesRun = [];

        foreach ($this->workspace->rules() as $rule) {
            if ($blueprint === null && $rule instanceof RequiresValidBlueprint) {
                continue;
            }
            $rulesRun[] = $rule->id();
            foreach ($rule->evaluate($blueprint ?? $this->emptyBlueprint(), $context) as $violation) {
                $violations[] = $violation;
            }
        }

        if ($changedFiles !== null) {
            $changed = array_flip($changedFiles);
            $violations = array_values(array_filter(
                $violations,
                static fn (Violation $v): bool => $v->path === null || isset($changed[$v->path]),
            ));
        }

        usort($violations, static fn (Violation $a, Violation $b): int => [$b->severity->weight(), $a->ruleId, (string) $a->path, $a->message] <=> [$a->severity->weight(), $b->ruleId, (string) $b->path, $b->message]);

        return new CheckResult($violations, $rulesRun);
    }

    private function context(?Blueprint $blueprint): CheckContext
    {
        $writer = $this->workspace->writer;
        $root = $this->workspace->root;

        $expected = [];
        if ($blueprint !== null) {
            try {
                foreach ((new GenerateArtifacts($this->workspace))->generate($blueprint) as $path => $artifact) {
                    $expected[$path] = $artifact->content;
                }
            } catch (\Throwable) {
                // Outdated-file detection is best effort.
            }
        }

        return new CheckContext(
            manifest: $this->workspace->store->loadManifest()->all(),
            taskBriefs: $this->taskBriefs(),
            decisionsFile: $this->workspace->store->rawDecisions(),
            fileExists: static function (string $relative) use ($root): bool {
                try {
                    $normalized = Paths::normalize($relative);
                } catch (\InvalidArgumentException) {
                    return false;
                }

                return $normalized !== '' && file_exists(Paths::join($root, $normalized));
            },
            readFile: static fn (string $relative): ?string => $writer->read($relative),
            expectedArtifacts: $expected,
            outputDirectory: $this->workspace->config->outputDirectory,
        );
    }

    /**
     * @return list<string>
     */
    private function taskBriefs(): array
    {
        $directory = Paths::join($this->workspace->root, $this->workspace->config->tasksDirectory);
        if (! is_dir($directory)) {
            return [];
        }

        $briefs = [];
        foreach (glob($directory.'/*/task.md') ?: [] as $file) {
            $relative = Paths::relativeTo($this->workspace->root, str_replace('\\', '/', $file));
            if ($relative !== null) {
                $briefs[] = $relative;
            }
        }
        sort($briefs);

        return $briefs;
    }

    private function emptyBlueprint(): Blueprint
    {
        return new Blueprint('1.0', '', '', new ProjectInfo($this->workspace->projectName(), 'unknown'));
    }
}

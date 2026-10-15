<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\BuildTaskBrief;
use AndrewProd\AiBlueprint\Application\Workspace;

final class TaskCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:task
        {title : Short task title, e.g. "Add customer notifications"}
        {--type=feature : Workflow: feature, bugfix, refactor, api-endpoint, integration}
        {--description= : Goal / description written into task.md}
        {--output= : Directory for task briefs (default from config)}
        {--adapter=generic : Agent the brief is prepared for: generic, codex, claude, cursor}
        {--dry-run : Print the brief without writing files}
        {--force : Overwrite an existing brief with the same slug}';

    protected $description = 'Create a task brief (task.md, context.md, checklist.md) for an AI coding agent';

    protected function perform(Workspace $workspace): int
    {
        $service = new BuildTaskBrief($workspace);
        $output = $this->option('output');
        $output = is_string($output) && $output !== '' ? $output : null;
        $adapter = $this->option('adapter');

        $brief = $service->prepare(
            (string) $this->argument('title'),
            (string) $this->option('type'),
            $output,
            is_array($adapter) ? (string) ($adapter[0] ?? 'generic') : (string) $adapter,
            (string) ($this->option('description') ?? ''),
        );

        $directory = $service->directoryFor($brief, $output);

        if ($this->option('dry-run')) {
            foreach ($brief->files as $file => $content) {
                $this->line("<fg=gray>=== {$directory}/{$file} ===</>");
                $this->line($content);
            }
            $this->components->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        foreach ($service->write($brief, $output, (bool) $this->option('force')) as $path) {
            $this->components->twoColumnDetail($path, '<fg=green>created</>');
        }

        $this->newLine();
        $this->components->info("Edit {$directory}/task.md (goal, acceptance criteria), then hand it to your agent.");

        return self::SUCCESS;
    }
}

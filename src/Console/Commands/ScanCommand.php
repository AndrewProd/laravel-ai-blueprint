<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\ScanProject;
use AndrewProd\AiBlueprint\Application\Workspace;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;
use AndrewProd\AiBlueprint\Domain\Components\Component;

final class ScanCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:scan
        {--dry-run : Scan and report without writing blueprint.json}
        {--no-cache : Re-parse every file}';

    protected $description = 'Re-scan the project and update blueprint.json (documentation is not touched; use ai-blueprint:sync)';

    protected function perform(Workspace $workspace): int
    {
        $previous = null;
        try {
            $previous = $workspace->store->loadBlueprint();
        } catch (InvalidBlueprint) {
            // First scan, or the old file is unreadable; it will be replaced.
        }

        $blueprint = (new ScanProject($workspace))->run(! $this->option('no-cache'));
        $this->scanSummary($blueprint);

        if ($previous !== null) {
            $before = array_map(static fn (Component $c): string => $c->id, $previous->components);
            $after = array_map(static fn (Component $c): string => $c->id, $blueprint->components);
            $added = array_diff($after, $before);
            $removed = array_diff($before, $after);
            $this->newLine();
            $this->components->twoColumnDetail('<fg=gray>Changes since last scan</>');
            $this->components->twoColumnDetail('Added components', (string) count($added));
            $this->components->twoColumnDetail('Removed components', (string) count($removed));
            if ($this->output->isVerbose()) {
                foreach ($added as $id) {
                    $this->line("  <fg=green>+ {$id}</>");
                }
                foreach ($removed as $id) {
                    $this->line("  <fg=red>- {$id}</>");
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: blueprint.json was not written.');

            return self::SUCCESS;
        }

        $workspace->store->saveBlueprint($blueprint);
        $this->newLine();
        $this->components->info("Blueprint saved to {$workspace->store->blueprintPath()}. Run `php artisan ai-blueprint:sync` to update generated files.");

        return self::SUCCESS;
    }
}

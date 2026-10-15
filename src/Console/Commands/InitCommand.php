<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\ScanProject;
use AndrewProd\AiBlueprint\Application\Workspace;

final class InitCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:init
        {--adapter=* : Agent adapters to generate (generic, codex, claude, cursor); defaults to config}
        {--dry-run : Show what would be created without writing anything}
        {--force : Overwrite conflicting files (manual edits will be lost)}
        {--no-cache : Re-parse every file}';

    protected $description = 'Scan the project and create the blueprint, documentation and agent instructions';

    protected function perform(Workspace $workspace): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $adapters = $this->adapterOption();
        $workspace->adapters($adapters); // validate early

        if (! $workspace->writer->exists('composer.json')) {
            $this->components->error('composer.json not found. Run this command from the root of a PHP/Laravel project.');

            return self::FAILURE;
        }

        $this->components->info('Laravel AI Blueprint'.($dryRun ? ' (dry run — nothing will be written)' : ''));

        $blueprint = (new ScanProject($workspace))->run(! $this->option('no-cache'));
        if ($blueprint->project->framework !== 'laravel') {
            $this->components->warn('This does not look like a Laravel application; Laravel-specific analysis may find nothing.');
        }

        $this->scanSummary($blueprint);

        if (! $dryRun) {
            $workspace->store->saveBlueprint($blueprint);
            $gitignore = $workspace->config->outputDirectory.'/.gitignore';
            if (! $workspace->writer->exists($gitignore)) {
                $workspace->writer->write($gitignore, "cache/\n");
            }
            if (! $workspace->writer->exists($workspace->store->decisionsPath())) {
                $workspace->store->saveDecisions([]);
            }
        }

        $this->newLine();
        $this->synchronize($workspace, $blueprint, $adapters, (bool) $this->option('force'), $dryRun, false);

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Needs review</>', count($blueprint->unknowns).' open question(s)');
        foreach ($blueprint->unknowns as $key) {
            $this->components->twoColumnDetail($key, '<fg=yellow>unknown</>');
        }
        if (! $blueprint->isComplete()) {
            $this->components->warn('Some areas were only partially analyzed. Run `php artisan ai-blueprint:check` for details.');
        }

        $this->newLine();
        $this->components->info('Next step: php artisan ai-blueprint:review');

        return self::SUCCESS;
    }
}

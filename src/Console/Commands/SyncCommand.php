<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\Workspace;

final class SyncCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:sync
        {--adapter=* : Agent adapters to render (generic, codex, claude, cursor); defaults to config}
        {--dry-run : Show the plan and diffs of conflicting files without writing}
        {--diff : Print diffs for every changed file}
        {--force : Overwrite manually edited or pre-existing files without asking}';

    protected $description = 'Update generated documents and agent instructions from blueprint.json without overwriting manual edits';

    protected function perform(Workspace $workspace): int
    {
        $blueprint = $workspace->store->loadBlueprint();
        $adapters = $this->adapterOption();

        $this->synchronize(
            $workspace,
            $blueprint,
            $adapters,
            (bool) $this->option('force'),
            (bool) $this->option('dry-run'),
            (bool) $this->option('diff'),
        );

        return self::SUCCESS;
    }
}

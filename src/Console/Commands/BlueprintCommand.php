<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\GenerateArtifacts;
use AndrewProd\AiBlueprint\Application\SyncItem;
use AndrewProd\AiBlueprint\Application\SyncManagedFiles;
use AndrewProd\AiBlueprint\Application\Workspace;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\UnsafePath;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

abstract class BlueprintCommand extends Command
{
    abstract protected function perform(Workspace $workspace): int;

    public function handle(): int
    {
        try {
            $workspace = $this->laravel->make(Workspace::class);

            return $this->perform($workspace);
        } catch (InvalidBlueprint $e) {
            $this->components->error("[{$e->ruleId}] ".$e->getMessage());

            return self::FAILURE;
        } catch (InvalidArgumentException|UnsafePath|RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return list<string>|null
     */
    protected function adapterOption(): ?array
    {
        $adapters = $this->option('adapter');
        if (! is_array($adapters) || $adapters === []) {
            return null;
        }

        $names = [];
        foreach ($adapters as $value) {
            foreach (explode(',', (string) $value) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    protected function scanSummary(Blueprint $blueprint): void
    {
        $this->components->twoColumnDetail('<fg=gray>Project detected</>');
        $this->components->twoColumnDetail('Framework', $blueprint->project->framework === 'laravel'
            ? 'Laravel'.($blueprint->project->frameworkVersion !== null ? ' '.$blueprint->project->frameworkVersion : '')
            : $blueprint->project->framework);
        $this->components->twoColumnDetail('PHP', $blueprint->project->php ?? 'unknown');
        $this->components->twoColumnDetail('Frontend', $blueprint->project->frontend === [] ? 'not detected' : implode(', ', $blueprint->project->frontend));

        foreach ($blueprint->analyzers as $report) {
            $color = match ($report->status) {
                AnalyzerReport::COMPLETE => 'green',
                AnalyzerReport::PARTIAL => 'yellow',
                AnalyzerReport::FAILED => 'red',
                default => 'gray',
            };
            $this->components->twoColumnDetail('Analyzer: '.$report->analyzer, "<fg={$color}>{$report->status}</>");
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Components</>');
        foreach ($blueprint->componentCounts() as $type => $count) {
            $this->components->twoColumnDetail($type, (string) $count);
        }
        if ($blueprint->excludedSensitive !== []) {
            $this->components->twoColumnDetail('Sensitive files skipped', (string) count($blueprint->excludedSensitive));
        }
    }

    /**
     * Plan, confirm and apply managed-file changes.
     *
     * @param  list<string>|null  $adapters
     * @return list<SyncItem>
     */
    protected function synchronize(Workspace $workspace, Blueprint $blueprint, ?array $adapters, bool $force, bool $dryRun, bool $showDiff): array
    {
        $artifacts = (new GenerateArtifacts($workspace))->generate($blueprint, $adapters);
        $manifest = $workspace->store->loadManifest();
        $sync = new SyncManagedFiles($workspace);
        $items = $sync->plan($artifacts, $manifest);

        $approved = [];
        foreach ($items as $item) {
            $interactiveConflict = $item->isConflict() && ! $force && ! $dryRun && $this->input->isInteractive();

            if ($item->diff !== null && ($showDiff || $interactiveConflict || ($item->isConflict() && $dryRun))) {
                $this->line($item->diff);
            }

            if ($interactiveConflict) {
                $reason = $item->action === SyncItem::CONFLICT ? 'was edited manually' : 'already exists and was not created by Blueprint';
                if ($this->confirm("{$item->path} {$reason}. Overwrite it with the generated version? Your changes will be lost.", false)) {
                    $approved[] = $item;
                }
            }
        }

        $sync->apply($items, $manifest, $force, $dryRun);
        if ($approved !== []) {
            $sync->apply($approved, $manifest, true, $dryRun);
        }

        $this->syncSummary($items, $dryRun);

        $conflicts = array_filter($items, static fn (SyncItem $i): bool => $i->isConflict() && ! $i->written);
        if ($conflicts !== []) {
            $this->newLine();
            $this->components->warn(count($conflicts).' file(s) were left untouched to preserve manual changes. Review with `php artisan ai-blueprint:sync --dry-run`, overwrite with --force.');
        }

        return $items;
    }

    /**
     * @param  list<SyncItem>  $items
     */
    protected function syncSummary(array $items, bool $dryRun): void
    {
        $this->components->twoColumnDetail('<fg=gray>Managed files'.($dryRun ? ' (dry run)' : '').'</>');
        foreach ($items as $item) {
            $label = match (true) {
                $item->action === SyncItem::CREATE => $dryRun ? '<fg=green>would create</>' : '<fg=green>created</>',
                $item->action === SyncItem::UPDATE => ($dryRun ? '<fg=green>would update</>' : '<fg=green>updated</>').($item->mode === 'section' ? ' (managed section)' : ''),
                $item->action === SyncItem::APPEND => $dryRun ? '<fg=green>would append managed section</>' : '<fg=green>appended managed section (your content kept)</>',
                $item->action === SyncItem::UNCHANGED => '<fg=gray>up to date</>',
                $item->written => $dryRun ? '<fg=red>would overwrite</>' : '<fg=red>overwritten</>',
                $item->action === SyncItem::CONFLICT => '<fg=yellow>skipped: edited manually'.($item->mode === 'section' ? ' (inside managed section)' : '').'</>',
                default => '<fg=yellow>skipped: existing file not created by Blueprint</>',
            };
            $this->components->twoColumnDetail($item->path, $label);
        }
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\Workspace;
use AndrewProd\AiBlueprint\Domain\Components\Component;

final class MapCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:map
        {--type= : Only list components of this type (e.g. model, route, table)}
        {--format=table : Output format: table or json}';

    protected $description = 'Show the project map recorded in blueprint.json';

    protected function perform(Workspace $workspace): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['table', 'json'], true)) {
            $this->components->error('--format must be "table" or "json".');

            return self::FAILURE;
        }

        $blueprint = $workspace->store->loadBlueprint();
        $type = $this->option('type');
        $components = is_string($type) && $type !== '' ? $blueprint->componentsOfType($type) : $blueprint->components;

        if ($format === 'json') {
            $this->line((string) json_encode([
                'project' => $blueprint->project->toArray(),
                'complete' => $blueprint->isComplete(),
                'counts' => (object) $blueprint->componentCounts(),
                'components' => array_map(static fn (Component $c): array => $c->toArray(), $components),
                'unknowns' => $blueprint->unknowns,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->scanSummary($blueprint);

        if (is_string($type) && $type !== '') {
            $this->newLine();
            $this->table(['Name', 'Path', 'Status'], array_map(
                static fn (Component $c): array => [$c->name, (string) $c->path, $c->status->value],
                $components,
            ));
        }

        $integrations = $blueprint->componentsOfType('integration');
        if ($integrations !== []) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=gray>Integrations</>');
            foreach ($integrations as $integration) {
                $this->components->twoColumnDetail($integration->name, $integration->status->value);
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Architecture decisions</>');
        foreach ($blueprint->decisions as $decision) {
            $this->components->twoColumnDetail($decision->key, '<fg=green>confirmed</>');
        }
        foreach ($blueprint->unknowns as $key) {
            $this->components->twoColumnDetail($key, '<fg=yellow>unknown</>');
        }

        $this->newLine();
        $this->components->twoColumnDetail('Blueprint generated at', $blueprint->generatedAt);
        $this->components->twoColumnDetail('Analysis complete', $blueprint->isComplete() ? 'yes' : '<fg=yellow>no</>');

        return self::SUCCESS;
    }
}

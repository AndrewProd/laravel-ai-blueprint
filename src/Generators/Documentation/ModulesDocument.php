<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators\Documentation;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Generators\ProjectFacts;

final class ModulesDocument extends DocumentGenerator
{
    private const LIMIT = 150;

    private const TYPE_LABELS = [
        'controller' => 'Controllers',
        'model' => 'Models',
        'service' => 'Services',
        'action' => 'Actions',
        'repository' => 'Repositories',
        'job' => 'Jobs',
        'event' => 'Events',
        'listener' => 'Listeners',
        'mail' => 'Mailables',
        'notification' => 'Notifications',
        'policy' => 'Policies',
        'middleware' => 'Middleware',
        'form_request' => 'Form requests',
        'api_resource' => 'API resources',
        'command' => 'Console commands',
        'provider' => 'Service providers',
        'observer' => 'Observers',
        'integration' => 'Integrations',
        'interface' => 'Interfaces',
        'enum' => 'Enums',
    ];

    public function name(): string
    {
        return 'docs.modules';
    }

    public function relativePath(): string
    {
        return 'architecture/modules.md';
    }

    protected function blocks(Blueprint $blueprint, ProjectFacts $facts, ArtifactContext $context): array
    {
        $boundaries = $blueprint->decision('module_boundaries');

        $blocks = [
            '# Modules and components',
            self::PRELIMINARY,
            "## Module boundaries\n\n".($boundaries !== null
                ? '**Confirmed:** '.Markdown::cell($boundaries->answer)
                : "_Not confirmed._ The grouping below is by directory only and does not imply module ownership.\n\n".$this->areas($blueprint)),
        ];

        foreach (self::TYPE_LABELS as $type => $label) {
            $components = $blueprint->componentsOfType($type);
            if ($components === []) {
                continue;
            }
            $rows = array_map(static fn (Component $c): array => [
                $c->name,
                $c->path !== null ? Markdown::code($c->path) : '',
                $c->status->value,
            ], array_slice($components, 0, self::LIMIT));

            $blocks[] = "## {$label}\n\n".Markdown::table(['Name', 'Source', 'Status'], $rows).$this->more($components);
        }

        $blocks[] = $this->routes($blueprint);
        $blocks[] = $this->tables($blueprint);
        $blocks[] = $this->events($blueprint);

        return $blocks;
    }

    private function areas(Blueprint $blueprint): string
    {
        $areas = [];
        foreach ($blueprint->components as $component) {
            if ($component->path === null || ! str_starts_with($component->path, 'app/')) {
                continue;
            }
            $segments = explode('/', $component->path);
            $area = count($segments) > 2 ? $segments[1] : '(app root)';
            $areas[$area] = ($areas[$area] ?? 0) + 1;
        }
        ksort($areas);

        $rows = [];
        foreach ($areas as $area => $count) {
            $rows[] = [$area === '(app root)' ? $area : Markdown::code('app/'.$area), (string) $count];
        }

        return $rows === [] ? '_No application classes discovered._' : Markdown::table(['Directory', 'Classes'], $rows);
    }

    private function routes(Blueprint $blueprint): string
    {
        $routes = $blueprint->componentsOfType('route');
        if ($routes === []) {
            return "## Routes\n\n_No statically defined routes discovered._";
        }

        $handlers = [];
        foreach ($blueprint->relationships as $relationship) {
            if ($relationship->type === 'handled_by') {
                $handlers[$relationship->from] = $blueprint->component($relationship->to)?->name;
            }
        }

        $rows = array_map(static function (Component $route) use ($handlers): array {
            $handler = $handlers[$route->id] ?? null;
            $action = (string) ($route->attributes['action'] ?? '');
            if ($handler !== null) {
                $handler .= '@'.($route->attributes['controller_method'] ?? '__invoke');
            } elseif (isset($route->attributes['controller'])) {
                $handler = $route->attributes['controller'].' (not found)';
            }

            return [
                (string) ($route->attributes['method'] ?? ''),
                Markdown::cell('/'.ltrim((isset($route->attributes['assumed_prefix']) ? $route->attributes['assumed_prefix'].'/' : '').(string) ($route->attributes['uri'] ?? ''), '/')),
                $handler ?? $action,
                Markdown::code((string) $route->path),
            ];
        }, array_slice($routes, 0, self::LIMIT));

        $apiNote = array_filter($routes, static fn (Component $r): bool => isset($r->attributes['assumed_prefix'])) !== []
            ? "URIs from `routes/api.php` are shown with Laravel's default `api` prefix (configurable in `bootstrap/app.php`).\n\n"
            : "\n\n";

        return "## Routes\n\nStatically discovered from routes/*.php. Routes registered by packages or at runtime are not listed. "
            .$apiNote
            .Markdown::table(['Method', 'URI', 'Handler', 'Defined in'], $rows).$this->more($routes);
    }

    private function tables(Blueprint $blueprint): string
    {
        $tables = $blueprint->componentsOfType('table');
        if ($tables === []) {
            return "## Database tables\n\n_No tables discovered in migrations._";
        }

        $rows = array_map(static function (Component $table): array {
            $columns = array_map(static fn (array $c): string => (string) $c['name'], $table->attributes['columns'] ?? []);
            $fks = array_map(static fn (array $fk): string => $fk['column'].' → '.$fk['on'].($fk['inferred'] ? ' (inferred)' : ''), $table->attributes['foreign_keys'] ?? []);

            return [
                Markdown::code($table->name),
                implode(', ', $columns),
                implode(', ', $fks),
            ];
        }, array_slice($tables, 0, self::LIMIT));

        return "## Database tables\n\nReconstructed from migrations without connecting to a database.\n\n"
            .Markdown::table(['Table', 'Columns', 'Foreign keys'], $rows).$this->more($tables);
    }

    private function events(Blueprint $blueprint): string
    {
        $lines = [];
        foreach ($blueprint->relationships as $relationship) {
            if ($relationship->type === 'listens_to') {
                $listener = $blueprint->component($relationship->from);
                $event = $blueprint->component($relationship->to);
                if ($listener !== null && $event !== null) {
                    $lines[] = Markdown::cell($event->name).' → '.Markdown::cell($listener->name);
                }
            }
        }

        return $lines === [] ? '' : "## Events and listeners\n\nDerived from listener `handle()` type hints.\n\n".Markdown::bullets($lines);
    }

    /**
     * @param  list<Component>  $components
     */
    private function more(array $components): string
    {
        $extra = count($components) - self::LIMIT;

        return $extra > 0 ? "\n\n_…and {$extra} more; see `blueprint.json`._" : '';
    }
}

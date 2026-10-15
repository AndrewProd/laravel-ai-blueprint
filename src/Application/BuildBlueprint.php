<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Domain\Architecture\ArchitectureDecision;
use AndrewProd\AiBlueprint\Domain\Architecture\QuestionCatalog;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Blueprint\ProjectInfo;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Evidence;
use AndrewProd\AiBlueprint\Domain\Components\Relationship;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Support\Version;

/**
 * Normalizes analyzer output into a deterministic Blueprint:
 * - components deduplicated by id; conflicting duplicates are kept and reported;
 * - "class:FQCN" relationship targets resolved to component ids;
 * - output sorted so repeated scans of the same tree produce identical JSON
 *   (apart from generated_at).
 */
final class BuildBlueprint
{
    /**
     * @param  list<AnalysisResult>  $results
     * @param  list<ArchitectureDecision>  $decisions
     * @param  list<string>  $excludedSensitive
     */
    public function build(
        string $projectName,
        bool $isLaravel,
        array $results,
        array $decisions,
        array $excludedSensitive,
        string $generatedAt,
    ): Blueprint {
        $facts = [];
        $findings = [];
        $reports = [];
        $rawComponents = [];
        $rawRelationships = [];

        foreach ($results as $result) {
            $reports[] = $result->report;
            array_push($findings, ...$result->findings);
            array_push($rawComponents, ...$result->components);
            array_push($rawRelationships, ...$result->relationships);
            foreach ($result->facts as $key => $value) {
                $facts[$key] = is_array($value) && is_array($facts[$key] ?? null)
                    ? array_merge($facts[$key], $value)
                    : $value;
            }
        }

        [$components, $duplicateFindings] = $this->mergeComponents($rawComponents);
        array_push($findings, ...$duplicateFindings);

        [$relationships, $relationshipFindings] = $this->resolveRelationships($rawRelationships, $components);
        array_push($findings, ...$relationshipFindings);
        array_push($relationships, ...$this->linkModelsToTables($components, $relationships));

        $relationships = $this->dedupeRelationships($relationships);

        $decidedKeys = array_map(static fn (ArchitectureDecision $d): string => $d->key, $decisions);
        $unknowns = array_values(array_diff(array_keys(QuestionCatalog::all()), $decidedKeys));

        usort($decisions, static fn (ArchitectureDecision $a, ArchitectureDecision $b): int => strcmp($a->key, $b->key));
        usort($findings, static fn (Finding $a, Finding $b): int => [$a->code, (string) $a->path, $a->message] <=> [$b->code, (string) $b->path, $b->message]);
        usort($reports, static fn (AnalyzerReport $a, AnalyzerReport $b): int => strcmp($a->analyzer, $b->analyzer));

        $tools = array_values(array_unique(array_merge($facts['tools'] ?? [], $facts['frontend_tools'] ?? [])));
        sort($tools);
        $packages = $facts['packages'] ?? [];
        ksort($packages);

        $project = new ProjectInfo(
            name: (string) ($facts['name'] ?? $projectName),
            framework: $isLaravel ? 'laravel' : (string) ($facts['framework'] ?? 'unknown'),
            frameworkVersion: $facts['framework_version'] ?? null,
            php: $facts['php'] ?? null,
            frontend: array_values($facts['frontend'] ?? []),
            tools: $tools,
            packages: $packages,
        );

        return new Blueprint(
            Version::SCHEMA,
            $generatedAt,
            Version::PACKAGE,
            $project,
            $components,
            $relationships,
            $decisions,
            $unknowns,
            $findings,
            $reports,
            $excludedSensitive,
        );
    }

    /**
     * @param  list<Component>  $raw
     * @return array{0: list<Component>, 1: list<Finding>}
     */
    private function mergeComponents(array $raw): array
    {
        usort($raw, static fn (Component $a, Component $b): int => [$a->id, (string) $a->path] <=> [$b->id, (string) $b->path]);

        /** @var array<string, Component> $byId */
        $byId = [];
        $findings = [];

        foreach ($raw as $component) {
            $existing = $byId[$component->id] ?? null;

            if ($existing === null) {
                $byId[$component->id] = $component;

                continue;
            }

            if ($existing->type === $component->type && $existing->path === $component->path) {
                $byId[$component->id] = new Component(
                    $existing->id,
                    $existing->type,
                    $existing->name,
                    $existing->path,
                    $existing->status,
                    $this->mergeEvidence($existing->evidence, $component->evidence),
                    $existing->attributes,
                );

                continue;
            }

            // Conflicting facts: keep both under distinct ids and report instead of picking one.
            $n = 2;
            while (isset($byId[$component->id.'~'.$n])) {
                $n++;
            }
            $id = $component->id.'~'.$n;
            $byId[$id] = new Component($id, $component->type, $component->name, $component->path, $component->status, $component->evidence, $component->attributes);
            $findings[] = new Finding(
                'DUPLICATE_COMPONENT',
                Severity::Info,
                "Component id [{$component->id}] is defined in more than one place ({$existing->path}, {$component->path}).",
                $component->path,
                'normalizer',
            );
        }

        $components = array_values($byId);
        usort($components, static fn (Component $a, Component $b): int => [$a->type, $a->id] <=> [$b->type, $b->id]);

        return [$components, $findings];
    }

    /**
     * @param  list<Relationship>  $raw
     * @param  list<Component>  $components
     * @return array{0: list<Relationship>, 1: list<Finding>}
     */
    private function resolveRelationships(array $raw, array $components): array
    {
        $byClass = [];
        $ids = [];
        foreach ($components as $component) {
            $ids[$component->id] = true;
            $class = $component->attributes['class'] ?? null;
            if (is_string($class)) {
                $byClass[strtolower($class)] ??= $component->id;
            }
        }

        $resolved = [];
        $findings = [];

        foreach ($raw as $relationship) {
            $to = $relationship->to;
            if (str_starts_with($to, 'class:')) {
                $class = substr($to, 6);
                $to = $byClass[strtolower($class)] ?? null;

                if ($to === null) {
                    if ($relationship->type === 'handled_by') {
                        $findings[] = new Finding(
                            'ROUTE_HANDLER_NOT_FOUND',
                            Severity::Warning,
                            "Route [{$relationship->from}] references [{$class}], which was not found in the scanned files.",
                            $relationship->evidence[0]->path ?? null,
                            'normalizer',
                        );
                    }

                    // References to framework or vendor classes are not part of the project map.
                    continue;
                }
            } elseif (! isset($ids[$to])) {
                continue;
            }

            if ($to === $relationship->from || ! isset($ids[$relationship->from])) {
                continue;
            }

            $resolved[] = new Relationship($relationship->from, $to, $relationship->type, $relationship->status, $relationship->evidence);
        }

        return [$resolved, $findings];
    }

    /**
     * Eloquent's default table naming is a convention, so model -> table links are inferred.
     *
     * @param  list<Component>  $components
     * @param  list<Relationship>  $existing
     * @return list<Relationship>
     */
    private function linkModelsToTables(array $components, array $existing): array
    {
        $tables = [];
        foreach ($components as $component) {
            if ($component->type === 'table') {
                $tables[$component->name] = $component->id;
            }
        }

        $links = [];
        foreach ($components as $component) {
            if ($component->type !== 'model' || ($component->attributes['abstract'] ?? false)) {
                continue;
            }
            $table = $this->conventionalTable($component->name);
            if (isset($tables[$table])) {
                $links[] = new Relationship($component->id, $tables[$table], 'uses_table', Status::Inferred, $component->evidence);
            }
        }

        return $links;
    }

    private function conventionalTable(string $model): string
    {
        $snake = strtolower((string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $model));

        return match (true) {
            str_ends_with($snake, 'y') && preg_match('/[aeiou]y$/', $snake) !== 1 => substr($snake, 0, -1).'ies',
            preg_match('/(s|x|z|ch|sh)$/', $snake) === 1 => $snake.'es',
            default => $snake.'s',
        };
    }

    /**
     * @param  list<Relationship>  $relationships
     * @return list<Relationship>
     */
    private function dedupeRelationships(array $relationships): array
    {
        /** @var array<string, Relationship> $byKey */
        $byKey = [];
        foreach ($relationships as $relationship) {
            $key = $relationship->key();
            $existing = $byKey[$key] ?? null;
            if ($existing === null) {
                $byKey[$key] = $relationship;

                continue;
            }
            // Observed beats inferred for the same link.
            $status = $existing->status === Status::Observed || $relationship->status === Status::Observed ? Status::Observed : $existing->status;
            $byKey[$key] = new Relationship($existing->from, $existing->to, $existing->type, $status, $this->mergeEvidence($existing->evidence, $relationship->evidence));
        }

        ksort($byKey);

        return array_values($byKey);
    }

    /**
     * @param  list<Evidence>  $a
     * @param  list<Evidence>  $b
     * @return list<Evidence>
     */
    private function mergeEvidence(array $a, array $b): array
    {
        $merged = [];
        foreach (array_merge($a, $b) as $evidence) {
            $merged[json_encode($evidence->toArray())] = $evidence;
        }
        ksort($merged);

        return array_values($merged);
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Blueprint;

use AndrewProd\AiBlueprint\Domain\Architecture\ArchitectureDecision;
use AndrewProd\AiBlueprint\Domain\Architecture\QuestionCatalog;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Relationship;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;

/**
 * The normalized, machine-readable model of a project.
 */
final class Blueprint
{
    /**
     * @param  list<Component>  $components
     * @param  list<Relationship>  $relationships
     * @param  list<ArchitectureDecision>  $decisions
     * @param  list<string>  $unknowns
     * @param  list<Finding>  $findings
     * @param  list<AnalyzerReport>  $analyzers
     * @param  list<string>  $excludedSensitive
     */
    public function __construct(
        public readonly string $schemaVersion,
        public readonly string $generatedAt,
        public readonly string $generatorVersion,
        public readonly ProjectInfo $project,
        public readonly array $components = [],
        public readonly array $relationships = [],
        public readonly array $decisions = [],
        public readonly array $unknowns = [],
        public readonly array $findings = [],
        public readonly array $analyzers = [],
        public readonly array $excludedSensitive = [],
    ) {}

    /**
     * Same blueprint with a new set of confirmed decisions; unknowns are recomputed.
     *
     * @param  list<ArchitectureDecision>  $decisions
     */
    public function withDecisions(array $decisions): self
    {
        usort($decisions, static fn (ArchitectureDecision $a, ArchitectureDecision $b): int => strcmp($a->key, $b->key));
        $decided = array_map(static fn (ArchitectureDecision $d): string => $d->key, $decisions);

        return new self(
            $this->schemaVersion,
            $this->generatedAt,
            $this->generatorVersion,
            $this->project,
            $this->components,
            $this->relationships,
            $decisions,
            array_values(array_diff(array_keys(QuestionCatalog::all()), $decided)),
            $this->findings,
            $this->analyzers,
            $this->excludedSensitive,
        );
    }

    public function isComplete(): bool
    {
        foreach ($this->analyzers as $report) {
            if (in_array($report->status, [AnalyzerReport::PARTIAL, AnalyzerReport::FAILED], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<Component>
     */
    public function componentsOfType(string $type): array
    {
        return array_values(array_filter($this->components, static fn (Component $c): bool => $c->type === $type));
    }

    public function component(string $id): ?Component
    {
        foreach ($this->components as $component) {
            if ($component->id === $id) {
                return $component;
            }
        }

        return null;
    }

    public function decision(string $key): ?ArchitectureDecision
    {
        foreach ($this->decisions as $decision) {
            if ($decision->key === $key) {
                return $decision;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    public function componentCounts(): array
    {
        $counts = [];
        foreach ($this->components as $component) {
            $counts[$component->type] = ($counts[$component->type] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'generated_at' => $this->generatedAt,
            'generator' => ['name' => 'laravel-ai-blueprint', 'version' => $this->generatorVersion],
            'project' => $this->project->toArray(),
            'analysis' => [
                'complete' => $this->isComplete(),
                'analyzers' => array_map(static fn (AnalyzerReport $r): array => $r->toArray(), $this->analyzers),
                'excluded_sensitive_files' => $this->excludedSensitive,
            ],
            'components' => array_map(static fn (Component $c): array => $c->toArray(), $this->components),
            'architecture' => [
                'confirmed_decisions' => array_map(static fn (ArchitectureDecision $d): array => $d->toArray(), $this->decisions),
                'inferred_relationships' => array_map(static fn (Relationship $r): array => $r->toArray(), $this->relationships),
            ],
            'unknowns' => array_map(
                static fn (string $key): array => ['key' => $key, 'question' => QuestionCatalog::question($key)],
                $this->unknowns,
            ),
            'findings' => array_map(static fn (Finding $f): array => $f->toArray(), $this->findings),
        ];
    }

    /**
     * Rebuild from decoded JSON. Structural validation happens in BlueprintValidator first.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['schema_version'],
            (string) $data['generated_at'],
            (string) ($data['generator']['version'] ?? 'unknown'),
            ProjectInfo::fromArray($data['project']),
            array_map(static fn (array $c): Component => Component::fromArray($c), $data['components'] ?? []),
            array_map(static fn (array $r): Relationship => Relationship::fromArray($r), $data['architecture']['inferred_relationships'] ?? []),
            array_map(static fn (array $d): ArchitectureDecision => ArchitectureDecision::fromArray($d), $data['architecture']['confirmed_decisions'] ?? []),
            array_map(static fn (array|string $u): string => is_array($u) ? (string) $u['key'] : $u, $data['unknowns'] ?? []),
            array_map(static fn (array $f): Finding => Finding::fromArray($f), $data['findings'] ?? []),
            array_map(static fn (array $a): AnalyzerReport => AnalyzerReport::fromArray($a), $data['analysis']['analyzers'] ?? []),
            array_values($data['analysis']['excluded_sensitive_files'] ?? []),
        );
    }
}

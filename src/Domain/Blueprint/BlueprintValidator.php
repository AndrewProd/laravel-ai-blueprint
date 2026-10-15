<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Blueprint;

use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Support\Version;

/**
 * Dependency-free structural validation mirroring resources/schemas/blueprint.schema.json.
 * The JSON Schema itself is verified against generated output in the test suite.
 */
final class BlueprintValidator
{
    /**
     * @return list<string> human readable errors, empty when valid
     */
    public function errors(mixed $data): array
    {
        if (! is_array($data)) {
            return ['Blueprint root must be a JSON object.'];
        }

        $errors = [];

        foreach (['schema_version', 'generated_at'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key])) {
                $errors[] = "Missing or invalid string field [{$key}].";
            }
        }

        $project = $data['project'] ?? null;
        if (! is_array($project) || ! is_string($project['name'] ?? null) || ! is_string($project['framework'] ?? null)) {
            $errors[] = 'Field [project] must contain string [name] and [framework].';
        } else {
            foreach (['frontend', 'tools'] as $list) {
                if (isset($project[$list]) && ! array_is_list($project[$list])) {
                    $errors[] = "Field [project.{$list}] must be a list.";
                }
            }
        }

        $statuses = array_map(static fn (Status $s): string => $s->value, Status::cases());

        if (! isset($data['components']) || ! is_array($data['components']) || ! array_is_list($data['components'])) {
            $errors[] = 'Field [components] must be a list.';
        } else {
            $seen = [];
            foreach ($data['components'] as $i => $component) {
                if (! is_array($component)) {
                    $errors[] = "Component #{$i} must be an object.";

                    continue;
                }
                foreach (['id', 'type', 'name'] as $key) {
                    if (! is_string($component[$key] ?? null) || $component[$key] === '') {
                        $errors[] = "Component #{$i} is missing [{$key}].";
                    }
                }
                if (! in_array($component['status'] ?? null, $statuses, true)) {
                    $errors[] = "Component #{$i} has an invalid [status].";
                }
                if (isset($component['evidence']) && ! $this->validEvidence($component['evidence'])) {
                    $errors[] = "Component #{$i} has invalid [evidence].";
                }
                $id = $component['id'] ?? null;
                if (is_string($id)) {
                    if (isset($seen[$id])) {
                        $errors[] = "Duplicate component id [{$id}].";
                    }
                    $seen[$id] = true;
                }
            }
        }

        $architecture = $data['architecture'] ?? null;
        if (! is_array($architecture)) {
            $errors[] = 'Field [architecture] must be an object.';
        } else {
            foreach ($architecture['confirmed_decisions'] ?? [] as $i => $decision) {
                if (! is_array($decision) || ! is_string($decision['key'] ?? null) || ($decision['status'] ?? null) !== 'confirmed') {
                    $errors[] = "Confirmed decision #{$i} must have a [key] and status [confirmed].";
                }
            }
            foreach ($architecture['inferred_relationships'] ?? [] as $i => $relationship) {
                if (! is_array($relationship) || ! is_string($relationship['from'] ?? null) || ! is_string($relationship['to'] ?? null)) {
                    $errors[] = "Relationship #{$i} must have [from] and [to].";
                } elseif (! in_array($relationship['status'] ?? null, $statuses, true)) {
                    $errors[] = "Relationship #{$i} has an invalid [status].";
                }
            }
        }

        if (isset($data['unknowns']) && ! is_array($data['unknowns'])) {
            $errors[] = 'Field [unknowns] must be a list.';
        }

        $severities = array_map(static fn (Severity $s): string => $s->value, Severity::cases());
        foreach ($data['findings'] ?? [] as $i => $finding) {
            if (! is_array($finding) || ! is_string($finding['code'] ?? null) || ! in_array($finding['severity'] ?? null, $severities, true)) {
                $errors[] = "Finding #{$i} must have a [code] and a valid [severity].";
            }
        }

        $reportStatuses = [AnalyzerReport::COMPLETE, AnalyzerReport::PARTIAL, AnalyzerReport::FAILED, AnalyzerReport::SKIPPED];
        foreach ($data['analysis']['analyzers'] ?? [] as $i => $report) {
            if (! is_array($report) || ! in_array($report['status'] ?? null, $reportStatuses, true)) {
                $errors[] = "Analyzer report #{$i} has an invalid [status].";
            }
        }

        return $errors;
    }

    /**
     * @throws InvalidBlueprint
     */
    public function assertValid(mixed $data): void
    {
        if (is_array($data) && is_string($data['schema_version'] ?? null) && ! Version::supportsSchema($data['schema_version'])) {
            throw InvalidBlueprint::unsupportedVersion($data['schema_version']);
        }

        $errors = $this->errors($data);
        if ($errors !== []) {
            throw InvalidBlueprint::corrupt(...$errors);
        }
    }

    private function validEvidence(mixed $evidence): bool
    {
        if (! is_array($evidence) || ! array_is_list($evidence)) {
            return false;
        }
        foreach ($evidence as $item) {
            if (! is_array($item) || ! is_string($item['path'] ?? null)) {
                return false;
            }
        }

        return true;
    }
}

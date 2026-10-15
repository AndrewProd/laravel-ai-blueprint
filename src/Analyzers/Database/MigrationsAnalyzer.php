<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Database;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Analyzers\ProjectContext;
use AndrewProd\AiBlueprint\Contracts\Analyzer;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Evidence;
use AndrewProd\AiBlueprint\Domain\Components\Relationship;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use ParseError;

/**
 * Replays migrations in file-name order to build an approximate table schema.
 */
final class MigrationsAnalyzer implements Analyzer
{
    public function name(): string
    {
        return 'migrations';
    }

    public function supports(ProjectContext $project): bool
    {
        return $project->files->in('database/migrations', ['php']) !== [];
    }

    public function analyze(ProjectContext $project): AnalysisResult
    {
        $parser = new MigrationFileParser;
        /** @var array<string, array{columns: array<string, array{name: string, type: string, nullable: bool}>, indexes: list<array<string, mixed>>, foreign_keys: list<array<string, mixed>>, evidence: list<Evidence>}> $tables */
        $tables = [];
        $findings = [];
        $partial = false;

        $files = $project->files->in('database/migrations', ['php']);
        sort($files);

        foreach ($files as $path) {
            $code = $project->files->read($path);
            if ($code === null) {
                continue;
            }

            try {
                $operations = $parser->parse($code);
            } catch (ParseError $e) {
                $partial = true;
                $findings[] = new Finding('BP006', Severity::Warning, "Migration could not be parsed (syntax error near line {$e->getLine()}).", $path, $this->name());

                continue;
            }

            foreach ($parser->unrecognized() as $item) {
                $partial = true;
                $findings[] = new Finding('MIGRATION_DYNAMIC', Severity::Info, "Partially analyzed: {$item['reason']} at line {$item['line']}.", $path, $this->name());
            }

            foreach ($operations as $op) {
                $table = $op['table'];
                $evidence = new Evidence($path, $op['line']);

                if ($op['op'] === 'drop') {
                    unset($tables[$table]);

                    continue;
                }

                if ($op['op'] === 'rename') {
                    if (isset($tables[$table])) {
                        $tables[$op['to']] = $tables[$table];
                        $tables[$op['to']]['evidence'][] = $evidence;
                        unset($tables[$table]);
                    }

                    continue;
                }

                if ($op['op'] === 'table' && ! isset($tables[$table])) {
                    $findings[] = new Finding('MIGRATION_UNKNOWN_TABLE', Severity::Info, "Schema::table('{$table}') modifies a table that was not created by a scanned migration.", $path, $this->name());
                }

                $tables[$table] ??= ['columns' => [], 'indexes' => [], 'foreign_keys' => [], 'evidence' => []];
                $tables[$table]['evidence'][] = $evidence;

                foreach ($op['columns'] ?? [] as $column) {
                    $tables[$table]['columns'][$column['name']] = $column;
                }
                foreach ($op['dropped'] ?? [] as $column) {
                    unset($tables[$table]['columns'][$column]);
                }
                foreach ($op['renamed'] ?? [] as $from => $to) {
                    if (isset($tables[$table]['columns'][$from])) {
                        $tables[$table]['columns'][$to] = ['name' => $to] + $tables[$table]['columns'][$from];
                        unset($tables[$table]['columns'][$from]);
                    }
                }
                array_push($tables[$table]['indexes'], ...($op['indexes'] ?? []));
                array_push($tables[$table]['foreign_keys'], ...($op['foreign_keys'] ?? []));
            }
        }

        ksort($tables);
        $components = [];
        $relationships = [];

        foreach ($tables as $name => $table) {
            $id = 'table.'.$name;
            $components[] = new Component(
                $id,
                'table',
                $name,
                $table['evidence'][0]->path,
                Status::Observed,
                $table['evidence'],
                [
                    'columns' => array_values($table['columns']),
                    'indexes' => $table['indexes'],
                    'foreign_keys' => $table['foreign_keys'],
                ],
            );

            foreach ($table['foreign_keys'] as $fk) {
                $relationships[] = new Relationship(
                    $id,
                    'table.'.$fk['on'],
                    'foreign_key',
                    $fk['inferred'] ? Status::Inferred : Status::Observed,
                    [$table['evidence'][0]],
                );
            }
        }

        return new AnalysisResult(
            new AnalyzerReport($this->name(), $partial ? AnalyzerReport::PARTIAL : AnalyzerReport::COMPLETE, [
                'Migrations are read statically; no database connection is made and nothing is executed.',
                'Raw SQL statements and dynamic expressions are not interpreted.',
            ]),
            $components,
            $relationships,
            $findings,
        );
    }
}

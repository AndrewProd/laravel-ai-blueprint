<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Database;

use AndrewProd\AiBlueprint\Infrastructure\Parsers\TokenStream;

/**
 * Reads Schema::create/table/rename/drop calls from the up() method of a migration.
 * The migration is never executed and no database connection is made.
 */
final class MigrationFileParser
{
    /** Blueprint methods that do not define a column. */
    private const NON_COLUMN = [
        'index', 'unique', 'primary', 'fulltext', 'spatialindex', 'foreign', 'dropcolumn', 'dropcolumns',
        'dropforeign', 'dropindex', 'dropunique', 'dropprimary', 'renamecolumn', 'renameindex', 'drop',
        'dropifexists', 'engine', 'charset', 'collation', 'comment', 'temporary', 'dropconstrainedforeignid',
        'dropforeignidfor', 'droptimestamps', 'dropsoftdeletes', 'dropremembertoken', 'dropmorphs', 'after', 'rawindex',
    ];

    /** Helpers that add well-known columns. */
    private const IMPLICIT = [
        'id' => [['id', 'bigIncrements']],
        'timestamps' => [['created_at', 'timestamp'], ['updated_at', 'timestamp']],
        'timestampstz' => [['created_at', 'timestampTz'], ['updated_at', 'timestampTz']],
        'nullabletimestamps' => [['created_at', 'timestamp'], ['updated_at', 'timestamp']],
        'softdeletes' => [['deleted_at', 'timestamp']],
        'softdeletestz' => [['deleted_at', 'timestampTz']],
        'remembertoken' => [['remember_token', 'string']],
    ];

    /** @var list<array{line: int, reason: string}> */
    private array $unrecognized = [];

    /**
     * @return list<array{op: string, table: string, line: int, to?: string, columns?: list<array{name: string, type: string, nullable: bool}>, dropped?: list<string>, renamed?: array<string, string>, foreign_keys?: list<array{column: string, references: string, on: string, inferred: bool}>, indexes?: list<array{type: string, columns: list<string>}>}>
     *
     * @throws \ParseError
     */
    public function parse(string $code): array
    {
        $this->unrecognized = [];
        $s = TokenStream::fromCode($code);
        [$start, $end] = $this->upMethodBody($s);
        $operations = [];

        for ($i = $start; $i < $end; $i++) {
            if (! ($s->is($i, T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED) && in_array(ltrim($s->text($i), '\\'), ['Schema', 'Illuminate\Support\Facades\Schema'], true))) {
                continue;
            }
            if (! $s->is($i + 1, T_DOUBLE_COLON) || ! $s->is($i + 2, T_STRING) || $s->text($i + 3) !== '(') {
                continue;
            }

            $method = strtolower($s->text($i + 2));
            $open = $i + 3;
            $args = $s->arguments($open);
            $line = $s->line($i);

            if (! in_array($method, ['create', 'table', 'rename', 'drop', 'dropifexists'], true)) {
                if (! in_array($method, ['hastable', 'hascolumn', 'hascolumns', 'connection', 'disableforeignkeyconstraints', 'enableforeignkeyconstraints', 'withoutforeignkeyconstraints'], true)) {
                    $this->unrecognized[] = ['line' => $line, 'reason' => "unsupported Schema::{$method}()"];
                }

                continue;
            }

            $table = $s->literalString($args[0] ?? null);
            if ($table === null) {
                $this->unrecognized[] = ['line' => $line, 'reason' => 'dynamic table name'];
                $i = $s->matching($open);

                continue;
            }

            if ($method === 'rename') {
                $to = $s->literalString($args[1] ?? null);
                if ($to === null) {
                    $this->unrecognized[] = ['line' => $line, 'reason' => 'dynamic table rename'];
                } else {
                    $operations[] = ['op' => 'rename', 'table' => $table, 'to' => $to, 'line' => $line];
                }
                $i = $s->matching($open);

                continue;
            }

            if ($method === 'drop' || $method === 'dropifexists') {
                $operations[] = ['op' => 'drop', 'table' => $table, 'line' => $line];
                $i = $s->matching($open);

                continue;
            }

            $operations[] = ['op' => $method, 'table' => $table, 'line' => $line] + $this->parseBlueprintClosure($s, $args[1] ?? null);
            $i = $s->matching($open);
        }

        return $operations;
    }

    /**
     * @return list<array{line: int, reason: string}>
     */
    public function unrecognized(): array
    {
        return $this->unrecognized;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function upMethodBody(TokenStream $s): array
    {
        $count = $s->count();
        for ($i = 0; $i < $count; $i++) {
            if ($s->is($i, T_FUNCTION) && strtolower($s->text($i + 1)) === 'up') {
                for ($j = $i; $j < $count; $j++) {
                    if ($s->text($j) === '{') {
                        return [$j + 1, $s->matching($j)];
                    }
                }
            }
        }

        // No up() method (unusual); fall back to the whole file.
        return [0, $count];
    }

    /**
     * @param  array{0: int, 1: int}|null  $range
     * @return array{columns: list<array{name: string, type: string, nullable: bool}>, dropped: list<string>, renamed: array<string, string>, foreign_keys: list<array{column: string, references: string, on: string, inferred: bool}>, indexes: list<array{type: string, columns: list<string>}>}
     */
    private function parseBlueprintClosure(TokenStream $s, ?array $range): array
    {
        $result = ['columns' => [], 'dropped' => [], 'renamed' => [], 'foreign_keys' => [], 'indexes' => []];
        if ($range === null) {
            return $result;
        }

        // Find the closure parameter name, e.g. "$table".
        $variable = null;
        for ($i = $range[0]; $i <= $range[1]; $i++) {
            if ($s->is($i, T_VARIABLE)) {
                $variable = $s->text($i);
                break;
            }
            if ($s->text($i) === ')') {
                break;
            }
        }
        if ($variable === null) {
            $this->unrecognized[] = ['line' => $s->line($range[0]), 'reason' => 'Schema callback without a Blueprint parameter'];

            return $result;
        }

        for ($i = $range[0]; $i <= $range[1]; $i++) {
            if (! $s->is($i, T_VARIABLE) || $s->text($i) !== $variable || ! $s->is($i + 1, T_OBJECT_OPERATOR) || $s->text($i + 3) !== '(') {
                continue;
            }

            // Collect the fluent chain: $table->string('email')->nullable()->unique();
            $chain = [];
            $j = $i + 2;
            while ($s->is($j, T_STRING) && $s->text($j + 1) === '(') {
                $chain[] = ['name' => strtolower($s->text($j)), 'original' => $s->text($j), 'args' => $s->arguments($j + 1), 'line' => $s->line($j)];
                $j = $s->matching($j + 1) + 1;
                if (! $s->is($j, T_OBJECT_OPERATOR)) {
                    break;
                }
                $j++;
            }
            if ($chain === []) {
                continue;
            }
            $this->applyChain($s, $chain, $result);
            $i = $j;
        }

        return $result;
    }

    /**
     * @param  list<array{name: string, original: string, args: list<array{0: int, 1: int}>, line: int}>  $chain
     * @param  array<string, mixed>  $result
     */
    private function applyChain(TokenStream $s, array $chain, array &$result): void
    {
        $head = $chain[0];
        $modifiers = array_column(array_slice($chain, 1), 'name');
        $firstArg = $s->literalString($head['args'][0] ?? null);
        $listArg = $s->literalStringList($head['args'][0] ?? null);

        if (isset(self::IMPLICIT[$head['name']])) {
            $columns = self::IMPLICIT[$head['name']];
            if ($head['name'] === 'id' && $firstArg !== null) {
                $columns = [[$firstArg, 'bigIncrements']];
            }
            foreach ($columns as [$name, $type]) {
                $result['columns'][] = ['name' => $name, 'type' => $type, 'nullable' => $head['name'] !== 'id'];
            }

            return;
        }

        if (in_array($head['name'], ['morphs', 'nullablemorphs', 'uuidmorphs', 'ulidmorphs'], true) && $firstArg !== null) {
            $nullable = $head['name'] === 'nullablemorphs';
            $result['columns'][] = ['name' => $firstArg.'_id', 'type' => 'morphId', 'nullable' => $nullable];
            $result['columns'][] = ['name' => $firstArg.'_type', 'type' => 'string', 'nullable' => $nullable];

            return;
        }

        if (in_array($head['name'], ['dropcolumn', 'dropcolumns'], true)) {
            foreach ($head['args'] as $arg) {
                array_push($result['dropped'], ...($s->literalStringList($arg) ?? []));
            }

            return;
        }

        if ($head['name'] === 'renamecolumn') {
            $to = $s->literalString($head['args'][1] ?? null);
            if ($firstArg !== null && $to !== null) {
                $result['renamed'][$firstArg] = $to;
            }

            return;
        }

        if (in_array($head['name'], ['index', 'unique', 'primary', 'fulltext'], true)) {
            if ($listArg !== null) {
                $result['indexes'][] = ['type' => $head['name'], 'columns' => $listArg];
            }

            return;
        }

        if ($head['name'] === 'foreign') {
            $references = null;
            $on = null;
            foreach (array_slice($chain, 1) as $call) {
                if ($call['name'] === 'references') {
                    $references = $s->literalString($call['args'][0] ?? null);
                } elseif ($call['name'] === 'on') {
                    $on = $s->literalString($call['args'][0] ?? null);
                }
            }
            if ($firstArg !== null && $on !== null) {
                $result['foreign_keys'][] = ['column' => $firstArg, 'references' => $references ?? 'id', 'on' => $on, 'inferred' => false];
            }

            return;
        }

        if (in_array($head['name'], self::NON_COLUMN, true) || $firstArg === null) {
            return;
        }

        $result['columns'][] = ['name' => $firstArg, 'type' => $head['original'], 'nullable' => in_array('nullable', $modifiers, true)];

        foreach (array_slice($chain, 1) as $call) {
            if (in_array($call['name'], ['unique', 'index', 'primary'], true)) {
                $result['indexes'][] = ['type' => $call['name'], 'columns' => [$firstArg]];
            }
            if ($call['name'] === 'constrained') {
                $table = $s->literalString($call['args'][0] ?? null);
                $result['foreign_keys'][] = [
                    'column' => $firstArg,
                    'references' => $s->literalString($call['args'][1] ?? null) ?? 'id',
                    // constrained() without arguments derives the table from the column name.
                    'on' => $table ?? $this->guessTable($firstArg),
                    'inferred' => $table === null,
                ];
            }
        }
    }

    private function guessTable(string $column): string
    {
        $base = (string) preg_replace('/_id$/', '', $column);

        return match (true) {
            str_ends_with($base, 'y') && ! preg_match('/[aeiou]y$/', $base) => substr($base, 0, -1).'ies',
            str_ends_with($base, 's'), str_ends_with($base, 'x'), str_ends_with($base, 'ch'), str_ends_with($base, 'sh') => $base.'es',
            default => $base.'s',
        };
    }
}

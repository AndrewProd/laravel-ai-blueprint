<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\CheckProject;
use AndrewProd\AiBlueprint\Application\Workspace;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;
use AndrewProd\AiBlueprint\Infrastructure\Git\ChangedFiles;
use RuntimeException;

final class CheckCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:check
        {--format=table : Output format: table or json}
        {--strict : Fail on warnings as well as errors}
        {--changed : Only report file-specific problems for files changed since --base (requires git)}
        {--base=HEAD : Base revision for --changed}';

    protected $description = 'Run deterministic checks on the blueprint, managed files and task briefs';

    protected function perform(Workspace $workspace): int
    {
        $format = (string) $this->option('format');
        if (! in_array($format, ['table', 'json'], true)) {
            $this->components->error('--format must be "table" or "json".');

            return self::FAILURE;
        }

        $changed = null;
        if ($this->option('changed')) {
            try {
                $changed = (new ChangedFiles($workspace->root))->since((string) $this->option('base'));
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $strict = $this->option('strict') || $workspace->config->strict;
        $result = (new CheckProject($workspace))->run($changed);

        if ($format === 'json') {
            $this->line((string) json_encode($result->toArray($strict), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result->passes($strict) ? self::SUCCESS : self::FAILURE;
        }

        if ($result->violations === []) {
            $this->components->info('No problems found ('.count($result->rulesRun).' rules: '.implode(', ', $result->rulesRun).').');

            return self::SUCCESS;
        }

        $this->table(
            ['Rule', 'Severity', 'Path', 'Problem'],
            array_map(fn (Violation $v): array => [
                $v->ruleId,
                match ($v->severity) {
                    Severity::Error => '<fg=red>error</>',
                    Severity::Warning => '<fg=yellow>warning</>',
                    Severity::Info => '<fg=gray>info</>',
                },
                (string) $v->path,
                $v->message.($v->hint !== null && $this->output->isVerbose() ? "\n↳ ".$v->hint : ''),
            ], $result->violations),
        );

        $summary = sprintf(
            '%d error(s), %d warning(s), %d info%s.',
            $result->count(Severity::Error),
            $result->count(Severity::Warning),
            $result->count(Severity::Info),
            $changed !== null ? ' (limited to '.count($changed).' changed file(s))' : '',
        );

        if ($result->passes($strict)) {
            $this->components->info($summary.' Use -v to see hints.');

            return self::SUCCESS;
        }

        $this->components->error($summary.($strict ? ' Strict mode: warnings fail the check.' : '').' Use -v to see hints.');

        return self::FAILURE;
    }
}

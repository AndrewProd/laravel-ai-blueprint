<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators;

use AndrewProd\AiBlueprint\Domain\Architecture\QuestionCatalog;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;

/**
 * Human-readable facts derived from a blueprint, shared by generators.
 */
final class ProjectFacts
{
    public function __construct(private readonly Blueprint $blueprint) {}

    public function framework(): string
    {
        $project = $this->blueprint->project;
        $name = $project->framework === 'laravel' ? 'Laravel' : ucfirst($project->framework);

        return $project->frameworkVersion !== null ? $name.' '.$project->frameworkVersion : $name;
    }

    public function frontend(): string
    {
        return $this->blueprint->project->frontend === [] ? 'not detected' : implode(', ', $this->blueprint->project->frontend);
    }

    public function hasTool(string $tool): bool
    {
        return in_array($tool, $this->blueprint->project->tools, true);
    }

    /**
     * Commands an agent or developer can run to verify a change. They are
     * suggestions written into documents; the package never runs them.
     *
     * @return list<array{0: string, 1: string}> [command, purpose]
     */
    public function verificationCommands(): array
    {
        $commands = [];
        $laravel = $this->blueprint->project->framework === 'laravel';

        if ($this->hasTool('pest')) {
            $commands[] = [$laravel ? 'php artisan test' : 'vendor/bin/pest', 'Run the PHP test suite (Pest)'];
        } elseif ($this->hasTool('phpunit')) {
            $commands[] = [$laravel ? 'php artisan test' : 'vendor/bin/phpunit', 'Run the PHP test suite (PHPUnit)'];
        }
        if ($this->hasTool('phpstan') || $this->hasTool('larastan')) {
            $commands[] = ['vendor/bin/phpstan analyse', 'Static analysis'];
        }
        if ($this->hasTool('psalm')) {
            $commands[] = ['vendor/bin/psalm', 'Static analysis'];
        }
        if ($this->hasTool('pint')) {
            $commands[] = ['vendor/bin/pint --test', 'Check code style'];
        }
        if ($this->hasTool('php-cs-fixer')) {
            $commands[] = ['vendor/bin/php-cs-fixer fix --dry-run', 'Check code style'];
        }
        $runner = match (true) {
            $this->hasTool('pnpm') => 'pnpm',
            $this->hasTool('yarn') => 'yarn',
            $this->hasTool('bun') => 'bun',
            default => 'npm',
        };
        if ($this->hasTool('vitest') || $this->hasTool('jest')) {
            $commands[] = [$runner.' run test', 'Run frontend tests (check package.json for the exact script name)'];
        }

        $commands[] = ['php artisan ai-blueprint:check', 'Validate the blueprint, managed docs and task briefs'];

        return $commands;
    }

    /**
     * @return list<string>
     */
    public function confirmedDecisionLines(): array
    {
        $lines = [];
        foreach ($this->blueprint->decisions as $decision) {
            $lines[] = '**'.Markdown::cell($decision->key).'** — '.Markdown::cell($decision->answer);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public function unknownLines(): array
    {
        return array_map(
            static fn (string $key): string => '**'.$key.'** — '.QuestionCatalog::question($key),
            $this->blueprint->unknowns,
        );
    }
}

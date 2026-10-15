<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators\Documentation;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Generators\ProjectFacts;

final class TestingDocument extends DocumentGenerator
{
    public function name(): string
    {
        return 'docs.testing';
    }

    public function relativePath(): string
    {
        return 'engineering/testing.md';
    }

    protected function blocks(Blueprint $blueprint, ProjectFacts $facts, ArtifactContext $context): array
    {
        $suites = [];
        foreach ($blueprint->componentsOfType('test') as $test) {
            $suite = (string) ($test->attributes['suite'] ?? 'unknown');
            $suites[$suite] = ($suites[$suite] ?? 0) + 1;
        }
        ksort($suites);
        $rows = [];
        foreach ($suites as $suite => $count) {
            $rows[] = [$suite, (string) $count];
        }

        $frameworks = array_values(array_intersect($blueprint->project->tools, ['phpunit', 'pest', 'dusk', 'mockery', 'vitest', 'jest', 'playwright', 'cypress']));
        $expectations = $blueprint->decision('testing_expectations');

        return [
            '# Testing',
            "## Detected test setup\n\n".Markdown::bullets([
                'Frameworks: '.($frameworks === [] ? 'none detected' : implode(', ', $frameworks)),
            ])."\n\n".($rows === [] ? '_No test files discovered._' : Markdown::table(['Suite', 'Test files'], $rows)),
            "## Commands\n\nThese commands are suggestions derived from installed tools. Laravel AI Blueprint never runs them.\n\n"
                .Markdown::table(['Command', 'Purpose'], array_map(static fn (array $c): array => [Markdown::code($c[0]), $c[1]], $facts->verificationCommands())),
            "## Expectations\n\n".($expectations !== null
                ? '**Confirmed:** '.Markdown::cell($expectations->answer)
                : '_Not confirmed._ By default: add or update tests for every behavior change, and cover bug fixes with a regression test.'),
            "## Rules\n\n".Markdown::bullets([
                'Never claim tests pass unless they were actually run; report the command and its result.',
                'Tests must not call real external services, production databases or third-party APIs.',
                'Do not put secrets or real customer data in tests or fixtures.',
                'A missing test file next to a class does not prove the class is untested; search before adding duplicates.',
            ]),
        ];
    }
}

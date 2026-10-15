<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators\Documentation;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Generators\ProjectFacts;

final class CodingStandardsDocument extends DocumentGenerator
{
    public function name(): string
    {
        return 'docs.coding-standards';
    }

    public function relativePath(): string
    {
        return 'engineering/coding-standards.md';
    }

    protected function blocks(Blueprint $blueprint, ProjectFacts $facts, ArtifactContext $context): array
    {
        $tooling = [];
        if ($facts->hasTool('pint')) {
            $tooling[] = 'Code style is enforced with Laravel Pint (`vendor/bin/pint`). Do not hand-format against it.';
        }
        if ($facts->hasTool('php-cs-fixer')) {
            $tooling[] = 'Code style is enforced with PHP-CS-Fixer.';
        }
        if ($facts->hasTool('phpstan') || $facts->hasTool('larastan')) {
            $tooling[] = 'New code must pass PHPStan'.($facts->hasTool('larastan') ? ' (Larastan)' : '').' at the configured level.';
        }
        if ($facts->hasTool('rector')) {
            $tooling[] = 'Rector is installed; check its configuration before large refactors.';
        }

        $logic = $blueprint->decision('business_logic_location');

        return [
            '# Coding standards',
            "## Detected tooling\n\n".Markdown::bullets($tooling, '_No formatter or static analyzer detected in composer.json._'),
            "## Where code belongs\n\n".($logic !== null
                ? '**Confirmed:** '.Markdown::cell($logic->answer)
                : '_Not confirmed._ Follow the closest existing example in the same area of the codebase and ask before introducing a new layer or pattern (repositories, actions, DTOs, ...).'),
            "## General rules\n\n".Markdown::bullets([
                'Follow existing conventions in the surrounding code before general best practices.',
                'Reuse existing services, helpers and components; search before adding new ones.',
                'Do not change public contracts (routes, API responses, events, queued job payloads, database schema) without explicit agreement.',
                'Do not add a new dependency without a written justification in the task report.',
                'Keep changes focused on the task; avoid unrelated refactoring.',
                'Database changes go through new migrations; never edit migrations that may already have run.',
            ]),
        ];
    }
}

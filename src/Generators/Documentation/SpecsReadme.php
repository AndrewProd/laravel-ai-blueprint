<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators\Documentation;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Generators\ProjectFacts;

final class SpecsReadme extends DocumentGenerator
{
    public function name(): string
    {
        return 'docs.specs';
    }

    public function relativePath(): string
    {
        return 'specs/README.md';
    }

    protected function blocks(Blueprint $blueprint, ProjectFacts $facts, ArtifactContext $context): array
    {
        return [
            '# Specifications',
            'Long-lived requirements and contracts (API behavior, business rules, integrations) live in this directory, one file per topic.',
            "## Task briefs\n\nPer-task context is generated separately:\n\n```bash\nphp artisan ai-blueprint:task \"Short task title\" --type=feature\n```\n\nBriefs are written to ".Markdown::code($context->tasksDirectory.'/<slug>/').' and belong to you after creation — edit them freely.',
            "## Writing a spec\n\n".Markdown::bullets([
                'State the goal and the user-visible behavior.',
                'List acceptance criteria that can be verified by a test or a manual step.',
                'Name the contracts involved (routes, events, payloads, tables).',
                'Write down open questions instead of guessing.',
            ]),
        ];
    }
}

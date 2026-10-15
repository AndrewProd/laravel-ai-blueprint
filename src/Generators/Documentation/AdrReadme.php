<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators\Documentation;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Generators\ProjectFacts;

final class AdrReadme extends DocumentGenerator
{
    public function name(): string
    {
        return 'docs.adr';
    }

    public function relativePath(): string
    {
        return 'adr/README.md';
    }

    protected function blocks(Blueprint $blueprint, ProjectFacts $facts, ArtifactContext $context): array
    {
        return [
            '# Architecture decision records',
            'Record significant decisions as `NNNN-short-title.md` files in this directory (context, decision, consequences). '
                .'Short answers to the standard questions are captured with `php artisan ai-blueprint:review` and stored in '
                .Markdown::code($context->outputDirectory.'/decisions.json').'.',
            "## Confirmed via review\n\n".Markdown::bullets(
                $facts->confirmedDecisionLines(),
                '_None yet._',
            ),
            "## Still open\n\n".Markdown::bullets($facts->unknownLines()),
        ];
    }
}

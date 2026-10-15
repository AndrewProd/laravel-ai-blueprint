<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators\Documentation;

use AndrewProd\AiBlueprint\Contracts\ArtifactGenerator;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Generators\ProjectFacts;
use AndrewProd\AiBlueprint\Support\Version;

abstract class DocumentGenerator implements ArtifactGenerator
{
    /** Path relative to the docs directory. */
    abstract public function relativePath(): string;

    /**
     * @return list<string> markdown blocks
     */
    abstract protected function blocks(Blueprint $blueprint, ProjectFacts $facts, ArtifactContext $context): array;

    public function generate(Blueprint $blueprint, ArtifactContext $context): GeneratedArtifact
    {
        $content = Markdown::document([
            Markdown::header($this->name()),
            ...$this->blocks($blueprint, new ProjectFacts($blueprint), $context),
        ]);

        return new GeneratedArtifact($context->doc($this->relativePath()), $content, $this->name(), 'documentation', Version::TEMPLATES);
    }

    protected const PRELIMINARY = '> **Preliminary map, automatically discovered from the code.** Items marked *inferred* are guesses based on naming or structure. Only entries under "Confirmed decisions" were confirmed by a developer (`php artisan ai-blueprint:review`).';
}

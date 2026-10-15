<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Contracts;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;

/**
 * Produces the content of one managed document. Writing is done by ManagedFileWriter.
 */
interface ArtifactGenerator
{
    public function name(): string;

    public function generate(Blueprint $blueprint, ArtifactContext $context): GeneratedArtifact;
}

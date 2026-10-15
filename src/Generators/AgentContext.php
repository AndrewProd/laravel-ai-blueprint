<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;

final class AgentContext
{
    public function __construct(
        public readonly Blueprint $blueprint,
        public readonly ArtifactContext $artifacts,
    ) {}
}

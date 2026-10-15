<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators;

final class GeneratedArtifact
{
    public function __construct(
        public readonly string $path,
        public readonly string $content,
        public readonly string $generator,
        public readonly string $type,
        public readonly string $templateVersion,
    ) {}
}

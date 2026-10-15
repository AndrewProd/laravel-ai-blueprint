<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators;

/**
 * Settings that influence generated content.
 */
final class ArtifactContext
{
    /**
     * @param  list<string>  $documentPaths  managed documentation paths that will exist
     */
    public function __construct(
        public readonly string $outputDirectory,
        public readonly string $docsDirectory,
        public readonly string $tasksDirectory,
        public readonly bool $documentation,
        public readonly array $documentPaths = [],
    ) {}

    public function doc(string $relative): string
    {
        return $this->docsDirectory.'/'.$relative;
    }
}

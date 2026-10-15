<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Rules;

/**
 * Everything a rule may inspect besides the blueprint. Rules read files only
 * through $readFile/$fileExists so they stay inside the project root.
 */
final class CheckContext
{
    /**
     * @param  array<string, mixed>  $manifest  decoded manifest.json entries keyed by path
     * @param  list<string>  $taskBriefs  relative paths of task.md files
     * @param  array<string, mixed>|null  $decisionsFile  raw decoded decisions.json
     * @param  \Closure(string): bool  $fileExists
     * @param  \Closure(string): ?string  $readFile
     * @param  array<string, string>  $expectedArtifacts  path => content the generators would write now
     */
    public function __construct(
        public readonly array $manifest,
        public readonly array $taskBriefs,
        public readonly ?array $decisionsFile,
        public readonly \Closure $fileExists,
        public readonly \Closure $readFile,
        public readonly array $expectedArtifacts = [],
        public readonly string $outputDirectory = '.ai-blueprint',
    ) {}

    public function statePath(string $file): string
    {
        return $this->outputDirectory.'/'.$file;
    }

    public function exists(string $relative): bool
    {
        return ($this->fileExists)($relative);
    }

    public function read(string $relative): ?string
    {
        return ($this->readFile)($relative);
    }
}

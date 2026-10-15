<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers;

use AndrewProd\AiBlueprint\Infrastructure\Cache\AnalysisCache;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\ProjectFiles;

/**
 * Input shared by all analyzers: the restricted file view and an optional cache.
 */
final class ProjectContext
{
    public function __construct(
        public readonly ProjectFiles $files,
        public readonly ?AnalysisCache $cache = null,
    ) {}

    public function root(): string
    {
        return $this->files->root();
    }

    public function isLaravel(): bool
    {
        if ($this->files->exists('artisan') && $this->files->exists('bootstrap/app.php')) {
            return true;
        }

        $composer = $this->files->readJson('composer.json');

        return isset($composer['require']['laravel/framework']);
    }
}

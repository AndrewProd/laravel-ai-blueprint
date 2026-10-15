<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Filesystem;

use AndrewProd\AiBlueprint\Support\Paths;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Read-only view of the project tree restricted to included, non-excluded,
 * non-sensitive files. This is the only way analyzers access the filesystem.
 */
final class ProjectFiles
{
    /** @var list<string>|null */
    private ?array $files = null;

    /** @var list<string> */
    private array $sensitive = [];

    /** @var list<string> */
    private array $unreadable = [];

    /**
     * @param  list<string>  $include  directories relative to root
     * @param  list<string>  $exclude  directories or glob patterns relative to root
     * @param  list<string>  $sensitivePatterns  glob patterns for files that must never be read
     */
    public function __construct(
        private readonly string $root,
        private readonly array $include,
        private readonly array $exclude,
        private readonly array $sensitivePatterns,
        private readonly int $maxFileSize = 1_048_576,
    ) {}

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        if ($this->files === null) {
            $this->files = $this->scan();
        }

        return $this->files;
    }

    /**
     * @param  list<string>  $extensions  e.g. ["php"]; matched against the end of the file name
     * @return list<string>
     */
    public function in(string $directory, array $extensions = []): array
    {
        $directory = trim($directory, '/');

        return array_values(array_filter($this->all(), static function (string $file) use ($directory, $extensions): bool {
            if (! Paths::isWithin($file, $directory)) {
                return false;
            }
            if ($extensions === []) {
                return true;
            }
            foreach ($extensions as $extension) {
                if (str_ends_with($file, '.'.ltrim($extension, '.'))) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * Files that matched a sensitive pattern inside included paths and were skipped.
     *
     * @return list<string>
     */
    public function sensitiveSkipped(): array
    {
        $this->all();

        return $this->sensitive;
    }

    /**
     * @return list<string>
     */
    public function unreadable(): array
    {
        return $this->unreadable;
    }

    public function isSensitive(string $relative): bool
    {
        foreach ($this->sensitivePatterns as $pattern) {
            if (Paths::matches($relative, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function isExcluded(string $relative): bool
    {
        $segments = explode('/', $relative);

        foreach ($this->exclude as $pattern) {
            if (Paths::isWithin($relative, $pattern)) {
                return true;
            }

            if (str_contains($pattern, '/')) {
                if (Paths::matches($relative, $pattern)) {
                    return true;
                }

                continue;
            }

            // Patterns without "/" match a file or directory name at any depth.
            foreach ($segments as $segment) {
                if (Paths::matches($segment, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a project-relative file exists (does not imply it may be read).
     */
    public function exists(string $relative): bool
    {
        $absolute = $this->absolute($relative);

        return $absolute !== null && is_file($absolute);
    }

    public function directoryExists(string $relative): bool
    {
        $absolute = $this->absolute($relative);

        return $absolute !== null && is_dir($absolute);
    }

    /**
     * Read a text file. Returns null for sensitive, excluded, binary, oversized,
     * missing or out-of-project files. Root-level files outside the include list
     * (composer.json, package.json, ...) may be read as long as they are not sensitive.
     */
    public function read(string $relative): ?string
    {
        try {
            $relative = Paths::normalize($relative);
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($relative === '' || $this->isSensitive($relative) || $this->isExcluded($relative)) {
            return null;
        }

        $absolute = $this->absolute($relative);
        if ($absolute === null || ! is_file($absolute) || ! is_readable($absolute)) {
            return null;
        }

        $real = realpath($absolute);
        $realRoot = realpath($this->root);
        if ($real === false || $realRoot === false || Paths::relativeTo($realRoot, $real) === null) {
            return null; // symlink escaping the project
        }

        if (filesize($absolute) > $this->maxFileSize) {
            $this->unreadable[] = $relative;

            return null;
        }

        $contents = @file_get_contents($absolute);
        if ($contents === false) {
            $this->unreadable[] = $relative;

            return null;
        }

        if (str_contains(substr($contents, 0, 8000), "\0")) {
            return null; // binary
        }

        return $contents;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readJson(string $relative): ?array
    {
        $contents = $this->read($relative);
        if ($contents === null) {
            return null;
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    private function absolute(string $relative): ?string
    {
        try {
            $relative = Paths::normalize($relative);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return Paths::join($this->root, $relative);
    }

    /**
     * @return list<string>
     */
    private function scan(): array
    {
        $files = [];
        $sensitive = [];

        foreach ($this->include as $directory) {
            try {
                $directory = Paths::normalize($directory);
            } catch (\InvalidArgumentException) {
                continue;
            }
            $absolute = Paths::join($this->root, $directory);
            if ($directory === '' || ! is_dir($absolute) || is_link($absolute) || $this->isExcluded($directory)) {
                continue;
            }

            try {
                $iterator = new RecursiveIteratorIterator(
                    new ExcludingFilter(
                        new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS),
                        $this->root,
                        fn (string $relative): bool => $this->isExcluded($relative),
                    ),
                );

                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    if (! $file->isFile() || $file->isLink()) {
                        continue;
                    }
                    $relative = Paths::relativeTo($this->root, str_replace('\\', '/', $file->getPathname()));
                    if ($relative === null || $relative === '') {
                        continue;
                    }
                    if ($this->isSensitive($relative)) {
                        $sensitive[] = $relative;

                        continue;
                    }
                    $files[$relative] = true;
                }
            } catch (UnexpectedValueException) {
                $this->unreadable[] = $directory;
            }
        }

        $list = array_keys($files);
        sort($list);
        sort($sensitive);
        $this->sensitive = array_values(array_unique($sensitive));

        return $list;
    }
}

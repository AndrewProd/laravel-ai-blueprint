<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Filesystem;

use AndrewProd\AiBlueprint\Support\Paths;
use InvalidArgumentException;

/**
 * The only component that writes to the project. Every path is validated:
 * relative, normalized, inside the real project root (no symlink escape),
 * and outside protected directories.
 */
final class SafeFileWriter
{
    private const PROTECTED = ['.git', 'vendor', 'node_modules'];

    private readonly string $realRoot;

    public function __construct(private readonly string $root)
    {
        $real = realpath($root);
        if ($real === false || ! is_dir($real)) {
            throw new InvalidArgumentException("Project root [{$root}] does not exist.");
        }
        $this->realRoot = str_replace('\\', '/', $real);
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * @throws UnsafePath
     */
    public function resolve(string $relative): string
    {
        if ($relative === '' || Paths::isAbsolute($relative)) {
            throw UnsafePath::because($relative, 'path must be relative to the project root');
        }
        if (str_contains($relative, "\0")) {
            throw UnsafePath::because($relative, 'path contains a null byte');
        }

        try {
            $normalized = Paths::normalize($relative);
        } catch (InvalidArgumentException) {
            throw UnsafePath::because($relative, 'path escapes the project root');
        }
        if ($normalized === '') {
            throw UnsafePath::because($relative, 'path points at the project root');
        }

        foreach (self::PROTECTED as $protected) {
            if (Paths::isWithin($normalized, $protected)) {
                throw UnsafePath::because($relative, "writing inside {$protected}/ is not allowed");
            }
        }

        $absolute = Paths::join($this->realRoot, $normalized);

        if (is_link($absolute)) {
            throw UnsafePath::because($relative, 'target is a symbolic link');
        }

        // The deepest existing ancestor must resolve inside the project.
        $ancestor = dirname($absolute);
        while (! file_exists($ancestor) && $ancestor !== dirname($ancestor)) {
            $ancestor = dirname($ancestor);
        }
        $realAncestor = realpath($ancestor);
        if ($realAncestor === false || Paths::relativeTo($this->realRoot, str_replace('\\', '/', $realAncestor)) === null) {
            throw UnsafePath::because($relative, 'path resolves outside the project root');
        }

        return $absolute;
    }

    public function exists(string $relative): bool
    {
        try {
            return is_file($this->resolve($relative));
        } catch (UnsafePath) {
            return false;
        }
    }

    public function read(string $relative): ?string
    {
        try {
            $absolute = $this->resolve($relative);
        } catch (UnsafePath) {
            return null;
        }
        if (! is_file($absolute)) {
            return null;
        }
        $contents = @file_get_contents($absolute);

        return $contents === false ? null : $contents;
    }

    /**
     * Atomically write a file (temp file + rename).
     *
     * @throws UnsafePath
     */
    public function write(string $relative, string $contents): void
    {
        $absolute = $this->resolve($relative);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException("Could not create directory for [{$relative}].");
        }

        $temp = $directory.'/.'.basename($absolute).'.'.bin2hex(random_bytes(4)).'.tmp';
        if (@file_put_contents($temp, $contents) === false) {
            throw new \RuntimeException("Could not write [{$relative}].");
        }
        if (! @rename($temp, $absolute)) {
            @unlink($temp);
            throw new \RuntimeException("Could not write [{$relative}].");
        }
    }

    public function isWritable(string $relative): bool
    {
        try {
            $absolute = $this->resolve($relative);
        } catch (UnsafePath) {
            return false;
        }

        $probe = $absolute;
        while (! file_exists($probe) && $probe !== dirname($probe)) {
            $probe = dirname($probe);
        }

        return is_writable($probe);
    }
}

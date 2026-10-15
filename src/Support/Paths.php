<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Support;

use InvalidArgumentException;

/**
 * Pure path helpers. All project-relative paths inside the blueprint use forward
 * slashes and never start with "./" or "/".
 */
final class Paths
{
    /**
     * Normalize a relative path: forward slashes, no "." segments, resolved "..".
     *
     * @throws InvalidArgumentException when the path escapes its base via "..".
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw new InvalidArgumentException("Path [{$path}] escapes its base directory.");
                }
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    public static function join(string $base, string ...$parts): string
    {
        $path = rtrim(str_replace('\\', '/', $base), '/');

        foreach ($parts as $part) {
            $part = trim(str_replace('\\', '/', $part), '/');
            if ($part !== '') {
                $path .= '/'.$part;
            }
        }

        return $path;
    }

    public static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    /**
     * Relative path of an absolute path inside $root, or null when outside.
     */
    public static function relativeTo(string $root, string $absolute): ?string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $absolute = str_replace('\\', '/', $absolute);

        if ($absolute === $root) {
            return '';
        }

        if (! str_starts_with($absolute, $root.'/')) {
            return null;
        }

        return substr($absolute, strlen($root) + 1);
    }

    /**
     * Whether $relative equals $prefix or lives below it.
     */
    public static function isWithin(string $relative, string $prefix): bool
    {
        $prefix = trim($prefix, '/');

        return $prefix === '' || $relative === $prefix || str_starts_with($relative, $prefix.'/');
    }

    /**
     * Simple glob match for project paths. Supports "*" (within a segment),
     * "**" (any depth) and "?". Patterns without "/" match the basename.
     */
    public static function matches(string $relative, string $pattern): bool
    {
        $subject = str_contains($pattern, '/') ? $relative : basename($relative);

        $regex = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === '*') {
                if (($pattern[$i + 1] ?? '') === '*') {
                    $regex .= '.*';
                    $i++;
                } else {
                    $regex .= '[^/]*';
                }
            } elseif ($char === '?') {
                $regex .= '[^/]';
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        return preg_match('#^'.$regex.'$#', $subject) === 1;
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Filesystem;

/**
 * A delimited block that Blueprint owns inside a file it did not create
 * (for example an AGENTS.md that already existed). Content outside the
 * markers belongs to the user and is never touched.
 */
final class ManagedSection
{
    public const START = '<!-- ai-blueprint:start — generated section, managed by `php artisan ai-blueprint:sync` -->';

    public const END = '<!-- ai-blueprint:end -->';

    public static function wrap(string $content): string
    {
        return self::START."\n".rtrim($content)."\n".self::END."\n";
    }

    /**
     * The managed content between the markers, or null when the markers are missing or malformed.
     */
    public static function extract(string $file): ?string
    {
        $start = strpos($file, self::START);
        $end = strpos($file, self::END);
        if ($start === false || $end === false || $end < $start || substr_count($file, self::START) !== 1) {
            return null;
        }

        $inner = substr($file, $start + strlen(self::START), $end - $start - strlen(self::START));

        return trim($inner, "\r\n")."\n";
    }

    public static function append(string $file, string $content): string
    {
        return rtrim($file)."\n\n".self::wrap($content);
    }

    /**
     * Replace the managed block, or append it if the markers are gone.
     */
    public static function replace(string $file, string $content): string
    {
        if (self::extract($file) === null) {
            return self::append($file, $content);
        }

        $start = (int) strpos($file, self::START);
        $end = (int) strpos($file, self::END) + strlen(self::END);
        $after = ltrim(substr($file, $end), "\r\n");

        return substr($file, 0, $start).self::wrap($content).($after !== '' ? "\n".$after : '');
    }

    /**
     * The part of a file that a manifest entry's hash covers.
     *
     * @param  array{mode?: string}  $entry
     */
    public static function trackedContent(string $file, array $entry): ?string
    {
        return ($entry['mode'] ?? 'file') === 'section' ? self::extract($file) : $file;
    }
}

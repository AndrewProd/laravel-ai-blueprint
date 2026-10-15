<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Support;

final class Str
{
    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim($value, '-');
    }

    public static function snake(string $value): string
    {
        $value = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', '_', $value);

        return strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', $value));
    }

    /**
     * Split identifiers and sentences into lower-case search words:
     * "CustomerNotificationService" -> ["customer", "notification", "service"].
     *
     * @return list<string>
     */
    public static function words(string $value): array
    {
        $value = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $value);
        $parts = preg_split('/[^A-Za-z0-9]+/', strtolower($value)) ?: [];

        return array_values(array_filter($parts, static fn (string $p): bool => $p !== ''));
    }

    /**
     * Very small English singularizer, good enough for keyword matching.
     */
    public static function singular(string $word): string
    {
        if (strlen($word) > 4 && str_ends_with($word, 'ies')) {
            return substr($word, 0, -3).'y';
        }
        if (strlen($word) > 4 && preg_match('/(ss|sh|ch|x)es$/', $word) === 1) {
            return substr($word, 0, -2);
        }
        if (strlen($word) > 3 && str_ends_with($word, 's') && ! str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }

        return $word;
    }
}

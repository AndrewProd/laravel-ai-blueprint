<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Support;

final class Version
{
    public const PACKAGE = '0.1.0';

    /** Blueprint schema version written by this release. */
    public const SCHEMA = '1.0';

    /** Schema major versions this release can read. */
    public const SUPPORTED_SCHEMA_MAJOR = 1;

    /** Bump when generated templates change so manifests record which template produced a file. */
    public const TEMPLATES = '1';

    public static function supportsSchema(string $version): bool
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $version, $m) !== 1) {
            return false;
        }

        return (int) $m[1] === self::SUPPORTED_SCHEMA_MAJOR;
    }
}

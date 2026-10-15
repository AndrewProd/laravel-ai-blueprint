<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Blueprint;

use RuntimeException;

final class InvalidBlueprint extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly array $errors,
    ) {
        parent::__construct(implode('; ', $errors));
    }

    public static function corrupt(string ...$errors): self
    {
        return new self('BP001', array_values($errors));
    }

    public static function unsupportedVersion(string $version): self
    {
        return new self('BP008', ["Blueprint schema version [{$version}] is not supported by this package version."]);
    }
}

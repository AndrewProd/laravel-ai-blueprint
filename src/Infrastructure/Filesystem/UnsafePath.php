<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Filesystem;

use RuntimeException;

final class UnsafePath extends RuntimeException
{
    public static function because(string $path, string $reason): self
    {
        return new self("Refusing to write [{$path}]: {$reason}.");
    }
}

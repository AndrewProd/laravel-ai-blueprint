<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Tasks;

enum TaskType: string
{
    case Feature = 'feature';
    case Bugfix = 'bugfix';
    case Refactor = 'refactor';
    case ApiEndpoint = 'api-endpoint';
    case Integration = 'integration';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}

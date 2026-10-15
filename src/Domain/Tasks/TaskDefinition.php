<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Tasks;

final class TaskDefinition
{
    /**
     * @param  list<string>  $acceptanceCriteria
     * @param  list<string>  $constraints
     */
    public function __construct(
        public readonly string $title,
        public readonly string $slug,
        public readonly TaskType $type,
        public readonly string $description = '',
        public readonly array $acceptanceCriteria = [],
        public readonly array $constraints = [],
    ) {}
}

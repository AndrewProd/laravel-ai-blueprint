<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Contracts;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;

interface Rule
{
    public function id(): string;

    public function description(): string;

    /**
     * @return iterable<Violation>
     */
    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable;
}

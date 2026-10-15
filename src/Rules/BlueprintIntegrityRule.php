<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\RequiresValidBlueprint;
use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;

/**
 * BP001: structural problems are raised while loading; this rule adds referential integrity.
 */
final class BlueprintIntegrityRule implements RequiresValidBlueprint, Rule
{
    public function id(): string
    {
        return 'BP001';
    }

    public function description(): string
    {
        return 'Blueprint is readable, valid and internally consistent.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        foreach ($blueprint->relationships as $relationship) {
            foreach ([$relationship->from, $relationship->to] as $id) {
                if ($blueprint->component($id) === null) {
                    yield new Violation($this->id(), Severity::Error, "Relationship [{$relationship->key()}] references unknown component [{$id}].", null, 'Run `php artisan ai-blueprint:scan` to rebuild the blueprint.');
                }
            }
        }
    }
}

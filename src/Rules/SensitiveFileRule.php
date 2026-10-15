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
 * BP007: reports files inside scanned directories that match security exclusions.
 * Only the path is reported; contents are never read.
 */
final class SensitiveFileRule implements RequiresValidBlueprint, Rule
{
    public function id(): string
    {
        return 'BP007';
    }

    public function description(): string
    {
        return 'No sensitive files inside scanned source directories.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        foreach ($blueprint->excludedSensitive as $path) {
            yield new Violation($this->id(), Severity::Info, 'File matches a security exclusion and was not read. Make sure it is not committed by accident.', $path);
        }
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;
use AndrewProd\AiBlueprint\Support\Version;

/**
 * BP008: state files use a schema version this package understands.
 * (An unsupported blueprint.json is reported while loading it.)
 */
final class SchemaVersionRule implements Rule
{
    public function id(): string
    {
        return 'BP008';
    }

    public function description(): string
    {
        return 'Blueprint state files use a supported schema version.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        $version = $context->decisionsFile['schema_version'] ?? null;
        if ($context->decisionsFile !== null && (! is_string($version) || ! Version::supportsSchema($version))) {
            yield new Violation($this->id(), Severity::Error, 'decisions.json has a missing or unsupported schema_version.', $context->statePath('decisions.json'));
        }
    }
}

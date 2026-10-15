<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\ManagedSection;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\Manifest;

/**
 * BP003: managed files edited by hand (or deleted) need an explicit decision.
 */
final class ManagedFileConflictRule implements Rule
{
    public function id(): string
    {
        return 'BP003';
    }

    public function description(): string
    {
        return 'Managed files match the content last generated for them.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        foreach ($context->manifest as $path => $entry) {
            $content = $context->read($path);
            if ($content === null) {
                yield new Violation($this->id(), Severity::Warning, 'Managed file is missing.', $path, 'Run `php artisan ai-blueprint:sync` to recreate it.');

                continue;
            }
            $tracked = ManagedSection::trackedContent($content, $entry);
            if ($tracked === null) {
                yield new Violation($this->id(), Severity::Warning, 'The managed Blueprint section was removed from this file.', $path, 'Run `php artisan ai-blueprint:sync` to append it again.');

                continue;
            }
            if (Manifest::hash($tracked) !== ($entry['hash'] ?? null)) {
                yield new Violation($this->id(), Severity::Warning, 'Managed file was edited manually; sync will not overwrite it.', $path, 'Keep your edits (no action), or run `php artisan ai-blueprint:sync --force` to regenerate it.');
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\RequiresValidBlueprint;
use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\ManagedSection;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\Manifest;

/**
 * BP009: the blueprint and generated files are up to date.
 */
final class OutdatedArtifactRule implements RequiresValidBlueprint, Rule
{
    public function id(): string
    {
        return 'BP009';
    }

    public function description(): string
    {
        return 'Blueprint evidence exists and managed files reflect the current blueprint.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        $missing = [];
        foreach ($blueprint->components as $component) {
            foreach ($component->evidence as $evidence) {
                if (! isset($missing[$evidence->path]) && ! $context->exists($evidence->path) && ! $context->exists(rtrim($evidence->path, '/').'/.')) {
                    $missing[$evidence->path] = true;
                    yield new Violation($this->id(), Severity::Warning, 'Blueprint references a file that no longer exists; the blueprint is stale.', $evidence->path, 'Run `php artisan ai-blueprint:scan`.');
                }
            }
        }

        foreach ($context->expectedArtifacts as $path => $expected) {
            $entry = $context->manifest[$path] ?? null;
            $file = $context->read($path);
            $current = $entry === null || $file === null ? null : ManagedSection::trackedContent($file, $entry);
            if ($entry === null || $current === null || Manifest::hash($current) !== $entry['hash']) {
                continue; // not managed, missing or edited: BP003's concern
            }
            if (Manifest::hash($expected) !== $entry['hash']) {
                yield new Violation($this->id(), Severity::Info, 'Managed file is out of date with the blueprint.', $path, 'Run `php artisan ai-blueprint:sync`.');
            }
        }
    }
}

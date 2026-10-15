<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\RequiresValidBlueprint;
use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;

/**
 * BP005: nothing may be presented as confirmed unless a human recorded the decision.
 */
final class UnconfirmedDecisionRule implements RequiresValidBlueprint, Rule
{
    public function id(): string
    {
        return 'BP005';
    }

    public function description(): string
    {
        return 'Only human-recorded decisions are presented as confirmed.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        $recorded = [];
        foreach ((array) ($context->decisionsFile['decisions'] ?? []) as $i => $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = (string) ($entry['key'] ?? "#{$i}");
            if (($entry['status'] ?? null) === 'confirmed') {
                if (trim((string) ($entry['answer'] ?? '')) === '') {
                    yield new Violation($this->id(), Severity::Error, "Decision [{$key}] is marked confirmed but has no answer.", $context->statePath('decisions.json'), 'Answer it with `php artisan ai-blueprint:review` or remove the entry.');

                    continue;
                }
                $recorded[$key] = true;
            }
        }

        foreach ($blueprint->decisions as $decision) {
            if (! isset($recorded[$decision->key])) {
                yield new Violation($this->id(), Severity::Error, "Blueprint lists [{$decision->key}] as confirmed, but no confirmed decision is recorded.", $context->statePath('blueprint.json'), 'Run `php artisan ai-blueprint:scan` to rebuild the blueprint from recorded decisions.');
            }
        }

        foreach ($blueprint->components as $component) {
            if ($component->status === Status::Confirmed) {
                yield new Violation($this->id(), Severity::Error, "Component [{$component->id}] is marked confirmed, but analyzers can only observe or infer.", $component->path, 'Record the decision with `ai-blueprint:review` instead of editing blueprint.json.');
            }
        }
    }
}

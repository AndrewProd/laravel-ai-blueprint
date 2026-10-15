<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Workflows;

/**
 * A declarative process template used to build a task brief. Not an autonomous agent.
 */
final class WorkflowDefinition
{
    /**
     * @param  list<string>  $steps  suggested order of work
     * @param  list<string>  $acceptanceCriteria  default criteria to refine
     * @param  list<string>  $constraints
     * @param  array<string, list<string>>  $checklist  section => items
     * @param  list<string>  $relevantTypes  component types worth listing in context.md
     * @param  list<string>  $decisionKeys  architecture questions that matter for this workflow
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $summary,
        public readonly array $steps,
        public readonly array $acceptanceCriteria,
        public readonly array $constraints,
        public readonly array $checklist,
        public readonly array $relevantTypes,
        public readonly array $decisionKeys,
    ) {}
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Tasks;

final class TaskBrief
{
    /** Sections every task.md must contain (checked by BP004). */
    public const REQUIRED_SECTIONS = [
        'Goal',
        'Context',
        'Acceptance criteria',
        'Constraints',
        'Open questions',
        'Expected result',
    ];

    /**
     * @param  array<string, string>  $files  file name => content (task.md, context.md, checklist.md)
     */
    public function __construct(
        public readonly TaskDefinition $task,
        public readonly array $files,
    ) {}
}

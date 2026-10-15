<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;
use AndrewProd\AiBlueprint\Domain\Tasks\TaskBrief;

/**
 * BP004: every task.md keeps the required sections.
 */
final class TaskBriefSectionsRule implements Rule
{
    public function id(): string
    {
        return 'BP004';
    }

    public function description(): string
    {
        return 'Task briefs contain all required sections.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        foreach ($context->taskBriefs as $path) {
            $content = $context->read($path) ?? '';
            preg_match_all('/^##\s+(.+?)\s*$/m', $content, $matches);
            $headings = array_map('strtolower', $matches[1]);

            foreach (TaskBrief::REQUIRED_SECTIONS as $section) {
                if (! in_array(strtolower($section), $headings, true)) {
                    yield new Violation($this->id(), Severity::Error, "Task brief is missing the \"## {$section}\" section.", $path, 'Add the section (it may say "None").');
                }
            }

            foreach (['context.md', 'checklist.md'] as $sibling) {
                $siblingPath = dirname($path).'/'.$sibling;
                if (! $context->exists($siblingPath)) {
                    yield new Violation($this->id(), Severity::Warning, "Task brief is missing {$sibling}.", $siblingPath);
                }
            }
        }
    }
}

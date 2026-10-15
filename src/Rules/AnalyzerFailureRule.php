<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\RequiresValidBlueprint;
use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;

/**
 * BP006: surfaces files and analyzers that could not be processed.
 */
final class AnalyzerFailureRule implements RequiresValidBlueprint, Rule
{
    public function id(): string
    {
        return 'BP006';
    }

    public function description(): string
    {
        return 'All analyzers processed their files.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        foreach ($blueprint->analyzers as $report) {
            if ($report->status === AnalyzerReport::FAILED) {
                yield new Violation($this->id(), Severity::Warning, "Analyzer [{$report->analyzer}] failed: ".implode(' ', $report->notes), null, "Fix the cause or disable it in config('ai-blueprint.analyzers').");
            }
        }

        foreach ($blueprint->findings as $finding) {
            if ($finding->code === 'BP006') {
                yield new Violation($this->id(), Severity::Warning, $finding->message, $finding->path);
            }
        }
    }
}

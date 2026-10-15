<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Contracts;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Analyzers\ProjectContext;

/**
 * Reads project files and returns facts. Analyzers never write to the project.
 */
interface Analyzer
{
    public function name(): string;

    public function supports(ProjectContext $project): bool;

    public function analyze(ProjectContext $project): AnalysisResult;
}

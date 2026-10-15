<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers;

use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Relationship;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;

final class AnalysisResult
{
    /**
     * @param  list<Component>  $components
     * @param  list<Relationship>  $relationships
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $facts  project-level facts (framework_version, frontend, tools, ...)
     */
    public function __construct(
        public readonly AnalyzerReport $report,
        public readonly array $components = [],
        public readonly array $relationships = [],
        public readonly array $findings = [],
        public readonly array $facts = [],
    ) {}
}

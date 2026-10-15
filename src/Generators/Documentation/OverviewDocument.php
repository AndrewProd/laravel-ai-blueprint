<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Generators\Documentation;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Generators\ProjectFacts;

final class OverviewDocument extends DocumentGenerator
{
    public function name(): string
    {
        return 'docs.overview';
    }

    public function relativePath(): string
    {
        return 'architecture/overview.md';
    }

    protected function blocks(Blueprint $blueprint, ProjectFacts $facts, ArtifactContext $context): array
    {
        $project = $blueprint->project;

        $counts = [];
        foreach ($blueprint->componentCounts() as $type => $count) {
            $counts[] = [$type, (string) $count];
        }

        $coverage = array_map(static fn (AnalyzerReport $r): array => [
            $r->analyzer,
            $r->status,
            implode(' ', $r->notes),
        ], $blueprint->analyzers);

        return [
            '# Architecture overview',
            self::PRELIMINARY,
            "## Project\n\n".Markdown::table(['Property', 'Value'], [
                ['Name', $project->name],
                ['Framework', $facts->framework()],
                ['PHP constraint', $project->php ?? 'unknown'],
                ['Frontend', $facts->frontend()],
                ['Tooling', $project->tools === [] ? 'none detected' : implode(', ', $project->tools)],
                ['Notable packages', $project->packages === [] ? 'none detected' : implode(', ', array_keys($project->packages))],
            ]),
            "## Discovered components\n\n".($counts === [] ? '_No components discovered._' : Markdown::table(['Type', 'Count'], $counts))
                ."\n\nSee [modules.md](modules.md) for the full map and `".$context->outputDirectory.'/blueprint.json` for evidence.',
            "## Confirmed decisions\n\n".Markdown::bullets(
                $facts->confirmedDecisionLines(),
                '_No architectural decisions have been confirmed yet. Run `php artisan ai-blueprint:review`._',
            ),
            "## Open questions\n\nThese cannot be determined reliably from source code. Do not assume an answer; ask a developer.\n\n"
                .Markdown::bullets($facts->unknownLines()),
            "## Analysis coverage\n\nAnalysis complete: **".($blueprint->isComplete() ? 'yes' : 'no — some areas were only partially analyzed')."**\n\n"
                .Markdown::table(['Analyzer', 'Status', 'Notes'], $coverage),
        ];
    }
}

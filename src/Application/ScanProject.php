<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Analyzers\ProjectContext;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use Throwable;

/**
 * Runs analyzers and builds a normalized Blueprint. One failing analyzer does
 * not stop the scan: it is recorded as failed and the blueprint is marked incomplete.
 */
final class ScanProject
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly BuildBlueprint $builder = new BuildBlueprint,
    ) {}

    public function run(bool $useCache = true): Blueprint
    {
        $files = $this->workspace->files();
        $cache = $this->workspace->cache($useCache);
        $project = new ProjectContext($files, $cache);
        $results = [];

        foreach ($this->workspace->analyzers() as $analyzer) {
            if (! $analyzer->supports($project)) {
                $results[] = new AnalysisResult(new AnalyzerReport($analyzer->name(), AnalyzerReport::SKIPPED, ['Nothing to analyze in this project.']));

                continue;
            }

            try {
                $results[] = $analyzer->analyze($project);
            } catch (Throwable $e) {
                // Report the analyzer and exception type only; messages may quote file contents.
                $results[] = new AnalysisResult(
                    new AnalyzerReport($analyzer->name(), AnalyzerReport::FAILED, [
                        'Analyzer crashed ('.$e::class.'). Disable it in config(\'ai-blueprint.analyzers\') if this persists.',
                    ]),
                    findings: [new Finding('BP006', Severity::Error, "Analyzer [{$analyzer->name()}] failed with ".$e::class.'.', null, $analyzer->name())],
                );
            }
        }

        $unreadable = array_values(array_unique($files->unreadable()));
        if ($unreadable !== []) {
            $results[] = new AnalysisResult(
                new AnalyzerReport('filesystem', AnalyzerReport::PARTIAL, ['Some files were too large or unreadable and were skipped.']),
                findings: array_map(
                    static fn (string $path): Finding => new Finding('BP006', Severity::Warning, 'File was too large or unreadable and was skipped.', $path, 'filesystem'),
                    $unreadable,
                ),
            );
        }

        try {
            $cache->persist();
        } catch (Throwable) {
            // The cache is an optimization; failing to write it must not fail the scan.
        }

        return $this->builder->build(
            $this->workspace->projectName(),
            $project->isLaravel(),
            $results,
            $this->workspace->store->loadDecisions(),
            $files->sensitiveSkipped(),
            $this->workspace->now(),
        );
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Tests;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Analyzers\ProjectContext;
use AndrewProd\AiBlueprint\Contracts\Analyzer;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Evidence;
use AndrewProd\AiBlueprint\Domain\Components\Relationship;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Infrastructure\Parsers\PhpFileParser;
use AndrewProd\AiBlueprint\Support\Str;
use ParseError;

/**
 * Discovers PHPUnit/Pest test files and frontend test files.
 * Links to application classes only through explicit imports.
 */
final class TestsAnalyzer implements Analyzer
{
    private const FRONTEND_TEST_PATTERN = '/\.(test|spec)\.(js|jsx|ts|tsx|mjs|vue)$/';

    public function name(): string
    {
        return 'tests';
    }

    public function supports(ProjectContext $project): bool
    {
        return true;
    }

    public function analyze(ProjectContext $project): AnalysisResult
    {
        $parser = new PhpFileParser;
        $components = [];
        $relationships = [];
        $findings = [];
        $suites = [];
        $partial = false;

        foreach ($project->files->in('tests', ['php']) as $path) {
            if (! str_ends_with($path, 'Test.php')) {
                continue;
            }

            $suite = $this->suite($path);
            $suites[$suite] = true;
            $id = 'test.'.implode('.', array_map([Str::class, 'snake'], explode('/', substr($path, strlen('tests/'), -4))));
            $components[] = new Component($id, 'test', basename($path, '.php'), $path, Status::Observed, [new Evidence($path)], ['suite' => $suite, 'language' => 'php']);

            $code = $project->files->read($path);
            if ($code === null) {
                continue;
            }
            try {
                $symbols = $parser->parse($code);
            } catch (ParseError $e) {
                $partial = true;
                $findings[] = new Finding('BP006', Severity::Warning, "Test file could not be parsed (syntax error near line {$e->getLine()}).", $path, $this->name());

                continue;
            }

            foreach ($symbols->useLines as $imported => $line) {
                if (! str_starts_with($imported, 'Tests\\') && ! str_starts_with($imported, 'Illuminate\\') && ! str_starts_with($imported, 'PHPUnit\\')) {
                    $relationships[] = new Relationship($id, 'class:'.$imported, 'tests', Status::Inferred, [new Evidence($path, $line)]);
                }
            }
        }

        foreach ($project->files->all() as $path) {
            if (preg_match(self::FRONTEND_TEST_PATTERN, $path) === 1) {
                $suites['frontend'] = true;
                $components[] = new Component('test.frontend.'.str_replace('/', '.', $path), 'test', basename($path), $path, Status::Observed, [new Evidence($path)], ['suite' => 'frontend', 'language' => 'js']);
            }
        }

        $facts = ['test_suites' => array_keys($suites)];
        if ($project->files->exists('tests/Pest.php')) {
            $facts['pest'] = true;
        }

        return new AnalysisResult(
            new AnalyzerReport($this->name(), $partial ? AnalyzerReport::PARTIAL : AnalyzerReport::COMPLETE, [
                'Tests are linked to classes through imports only; missing links do not mean missing coverage.',
            ]),
            $components,
            $relationships,
            $findings,
            $facts,
        );
    }

    private function suite(string $path): string
    {
        $parts = explode('/', $path);

        return isset($parts[2]) ? strtolower($parts[1]) : 'root';
    }
}

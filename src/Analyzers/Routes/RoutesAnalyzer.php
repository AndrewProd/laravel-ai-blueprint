<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Routes;

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
use ParseError;

final class RoutesAnalyzer implements Analyzer
{
    public function name(): string
    {
        return 'routes';
    }

    public function supports(ProjectContext $project): bool
    {
        return $project->files->in('routes', ['php']) !== [];
    }

    public function analyze(ProjectContext $project): AnalysisResult
    {
        $parser = new RouteFileParser;
        $components = [];
        $relationships = [];
        $findings = [];
        $partial = false;

        foreach ($project->files->in('routes', ['php']) as $path) {
            $code = $project->files->read($path);
            if ($code === null) {
                continue;
            }

            try {
                $routes = $parser->parse($code);
            } catch (ParseError $e) {
                $partial = true;
                $findings[] = new Finding('BP006', Severity::Warning, "Routes file could not be parsed (syntax error near line {$e->getLine()}).", $path, $this->name());

                continue;
            }

            foreach ($parser->unrecognized() as $item) {
                $partial = true;
                $findings[] = new Finding('ROUTE_DYNAMIC', Severity::Info, "Route definition not statically analyzable ({$item['reason']}) at line {$item['line']}.", $path, $this->name());
            }

            foreach ($routes as $route) {
                $id = 'route.'.strtolower($route['method']).':'.$route['uri'];
                $attributes = array_filter([
                    'method' => $route['method'],
                    'uri' => $route['uri'],
                    'action' => $route['action'],
                    'controller' => $route['controller'],
                    'controller_method' => $route['controller_method'],
                    'route_name' => $route['name'],
                    'middleware' => $route['middleware'] ?: null,
                    'file' => $path,
                    // Laravel registers routes/api.php under the "api" prefix by default; this is configurable in bootstrap/app.php.
                    'assumed_prefix' => $path === 'routes/api.php' ? 'api' : null,
                ], static fn (mixed $v): bool => $v !== null);

                $components[] = new Component(
                    $id,
                    'route',
                    $route['method'].' '.$route['uri'],
                    $path,
                    Status::Observed,
                    [new Evidence($path, $route['line'])],
                    $attributes,
                );

                if ($route['controller'] !== null) {
                    $relationships[] = new Relationship(
                        $id,
                        'class:'.$route['controller'],
                        'handled_by',
                        $route['resolved'] ? Status::Observed : Status::Inferred,
                        [new Evidence($path, $route['line'])],
                    );
                }
            }
        }

        $notes = [
            'Routes are read statically from routes/*.php; the application is not booted.',
            'Routes registered by packages, service providers or loops are not discovered.',
        ];

        return new AnalysisResult(
            new AnalyzerReport($this->name(), $partial ? AnalyzerReport::PARTIAL : AnalyzerReport::COMPLETE, $notes),
            $components,
            $relationships,
            $findings,
        );
    }
}

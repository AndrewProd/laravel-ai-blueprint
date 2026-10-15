<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Frontend;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Analyzers\ProjectContext;
use AndrewProd\AiBlueprint\Contracts\Analyzer;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Evidence;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;

/**
 * Detects the frontend stack from package.json, config files and directories.
 * It classifies the stack; it does not parse frontend source code.
 */
final class FrontendAnalyzer implements Analyzer
{
    /** @var array<string, string> npm package => stack label */
    private const PACKAGES = [
        'vue' => 'vue',
        'react' => 'react',
        '@angular/core' => 'angular',
        'svelte' => 'svelte',
        'next' => 'next',
        'nuxt' => 'nuxt',
        '@inertiajs/vue3' => 'inertia',
        '@inertiajs/react' => 'inertia',
        '@inertiajs/svelte' => 'inertia',
        '@inertiajs/inertia' => 'inertia',
        'vite' => 'vite',
        'laravel-mix' => 'laravel-mix',
        'typescript' => 'typescript',
        'tailwindcss' => 'tailwind',
        'alpinejs' => 'alpine',
    ];

    /** @var array<string, string> npm package => test tool */
    private const TEST_TOOLS = [
        'vitest' => 'vitest',
        'jest' => 'jest',
        '@playwright/test' => 'playwright',
        'cypress' => 'cypress',
        '@vue/test-utils' => 'vue-test-utils',
        '@testing-library/react' => 'testing-library',
    ];

    /** @var array<string, string> config file glob prefix => stack label */
    private const CONFIG_FILES = [
        'vite.config' => 'vite',
        'nuxt.config' => 'nuxt',
        'next.config' => 'next',
        'angular.json' => 'angular',
        'webpack.mix.js' => 'laravel-mix',
        'tsconfig.json' => 'typescript',
        'tailwind.config' => 'tailwind',
    ];

    private const LOCK_FILES = [
        'package-lock.json' => 'npm',
        'yarn.lock' => 'yarn',
        'pnpm-lock.yaml' => 'pnpm',
        'bun.lockb' => 'bun',
        'bun.lock' => 'bun',
    ];

    private const EXTENSIONS = ['', '.js', '.ts', '.mjs', '.cjs', '.mts'];

    public function name(): string
    {
        return 'frontend';
    }

    public function supports(ProjectContext $project): bool
    {
        return true;
    }

    public function analyze(ProjectContext $project): AnalysisResult
    {
        $stack = [];
        $tools = [];
        $components = [];
        $findings = [];
        $evidence = [];

        if ($project->files->exists('package.json')) {
            $package = $project->files->readJson('package.json');
            if ($package === null) {
                $findings[] = new Finding('BP006', Severity::Warning, 'package.json could not be decoded.', 'package.json', $this->name());
            } else {
                $evidence[] = new Evidence('package.json');
                $deps = array_merge((array) ($package['dependencies'] ?? []), (array) ($package['devDependencies'] ?? []));
                foreach (self::PACKAGES as $name => $label) {
                    if (isset($deps[$name])) {
                        $stack[] = $label;
                    }
                }
                foreach (self::TEST_TOOLS as $name => $tool) {
                    if (isset($deps[$name])) {
                        $tools[] = $tool;
                    }
                }
            }
        }

        foreach (self::CONFIG_FILES as $file => $label) {
            foreach (self::EXTENSIONS as $extension) {
                if ($project->files->exists($file.$extension)) {
                    $stack[] = $label;
                    $evidence[] = new Evidence($file.$extension);
                }
            }
        }

        foreach (self::LOCK_FILES as $file => $manager) {
            if ($project->files->exists($file)) {
                $tools[] = $manager;
            }
        }

        $views = $project->files->in('resources/views', ['.blade.php']);
        if ($views !== []) {
            $stack[] = 'blade';
            $components[] = new Component('frontend.views', 'frontend_area', 'Blade views', 'resources/views', Status::Observed, [new Evidence('resources/views')], ['files' => count($views)]);
        }

        foreach (['resources/js', 'resources/ts', 'resources/css'] as $directory) {
            $files = $project->files->in($directory);
            if ($files !== []) {
                $components[] = new Component('frontend.'.basename($directory), 'frontend_area', $directory, $directory, Status::Observed, [new Evidence($directory)], ['files' => count($files)]);
            }
        }

        foreach (['resources/js/Pages', 'resources/js/pages', 'resources/ts/Pages'] as $pages) {
            $files = $project->files->in($pages);
            if ($files !== []) {
                $components[] = new Component('frontend.pages', 'frontend_area', 'Page components', $pages, Status::Inferred, [new Evidence($pages)], ['files' => count($files)]);
                break;
            }
        }

        $stack = array_values(array_unique($stack));
        sort($stack);
        $tools = array_values(array_unique($tools));
        sort($tools);

        $notes = ['Frontend is classified from configuration and directories only; source files are not parsed.'];
        if ($stack === []) {
            $notes[] = 'No frontend stack detected.';
        }

        return new AnalysisResult(
            new AnalyzerReport($this->name(), AnalyzerReport::COMPLETE, $notes),
            $components,
            findings: $findings,
            facts: ['frontend' => $stack, 'frontend_tools' => $tools],
        );
    }
}

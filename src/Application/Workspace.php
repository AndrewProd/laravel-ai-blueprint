<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Adapters\ClaudeCode\ClaudeCodeAdapter;
use AndrewProd\AiBlueprint\Adapters\Codex\CodexAdapter;
use AndrewProd\AiBlueprint\Adapters\Cursor\CursorAdapter;
use AndrewProd\AiBlueprint\Adapters\Generic\GenericAdapter;
use AndrewProd\AiBlueprint\Analyzers\Composer\ComposerAnalyzer;
use AndrewProd\AiBlueprint\Analyzers\Database\MigrationsAnalyzer;
use AndrewProd\AiBlueprint\Analyzers\Frontend\FrontendAnalyzer;
use AndrewProd\AiBlueprint\Analyzers\Php\PhpClassAnalyzer;
use AndrewProd\AiBlueprint\Analyzers\Routes\RoutesAnalyzer;
use AndrewProd\AiBlueprint\Analyzers\Tests\TestsAnalyzer;
use AndrewProd\AiBlueprint\Contracts\AgentAdapter;
use AndrewProd\AiBlueprint\Contracts\Analyzer;
use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Generators\ArtifactContext;
use AndrewProd\AiBlueprint\Generators\Documentation\AdrReadme;
use AndrewProd\AiBlueprint\Generators\Documentation\CodingStandardsDocument;
use AndrewProd\AiBlueprint\Generators\Documentation\DocumentGenerator;
use AndrewProd\AiBlueprint\Generators\Documentation\ModulesDocument;
use AndrewProd\AiBlueprint\Generators\Documentation\OverviewDocument;
use AndrewProd\AiBlueprint\Generators\Documentation\SecurityDocument;
use AndrewProd\AiBlueprint\Generators\Documentation\SpecsReadme;
use AndrewProd\AiBlueprint\Generators\Documentation\TestingDocument;
use AndrewProd\AiBlueprint\Infrastructure\Cache\AnalysisCache;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\BlueprintStore;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\ProjectFiles;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\SafeFileWriter;
use AndrewProd\AiBlueprint\Rules\AnalyzerFailureRule;
use AndrewProd\AiBlueprint\Rules\BlueprintIntegrityRule;
use AndrewProd\AiBlueprint\Rules\ManagedFileConflictRule;
use AndrewProd\AiBlueprint\Rules\MissingReferencedFileRule;
use AndrewProd\AiBlueprint\Rules\OutdatedArtifactRule;
use AndrewProd\AiBlueprint\Rules\SchemaVersionRule;
use AndrewProd\AiBlueprint\Rules\SensitiveFileRule;
use AndrewProd\AiBlueprint\Rules\TaskBriefSectionsRule;
use AndrewProd\AiBlueprint\Rules\UnconfirmedDecisionRule;
use Closure;
use InvalidArgumentException;

/**
 * Composition root for one project: configuration, filesystem access and the
 * registered analyzers, generators, adapters and rules.
 */
final class Workspace
{
    public readonly SafeFileWriter $writer;

    public readonly BlueprintStore $store;

    /** @var Closure(): string */
    private readonly Closure $clock;

    /**
     * @param  (Closure(): string)|null  $clock  returns an ISO-8601 UTC timestamp
     */
    public function __construct(
        public readonly string $root,
        public readonly BlueprintConfig $config,
        ?Closure $clock = null,
    ) {
        $this->writer = new SafeFileWriter($root);
        $this->store = new BlueprintStore($this->writer, $config->outputDirectory);
        $this->clock = $clock ?? static fn (): string => gmdate('Y-m-d\TH:i:s\Z');
    }

    public function now(): string
    {
        return ($this->clock)();
    }

    public function files(): ProjectFiles
    {
        return new ProjectFiles(
            $this->root,
            $this->config->include,
            // The package's own output is never analyzed as project source.
            array_merge($this->config->exclude, [$this->config->outputDirectory]),
            $this->config->sensitive,
        );
    }

    public function cache(bool $enabled = true): AnalysisCache
    {
        if (! $enabled || ! $this->config->cache) {
            return AnalysisCache::inMemory();
        }

        return new AnalysisCache($this->writer->resolve($this->store->cachePath()));
    }

    /**
     * @return list<Analyzer>
     */
    public function analyzers(): array
    {
        $all = [
            'composer' => new ComposerAnalyzer,
            'php' => new PhpClassAnalyzer,
            'routes' => new RoutesAnalyzer,
            'migrations' => new MigrationsAnalyzer,
            'tests' => new TestsAnalyzer,
            'frontend' => new FrontendAnalyzer,
        ];

        return array_values(array_filter($all, fn (string $name): bool => $this->config->analyzerEnabled($name), ARRAY_FILTER_USE_KEY));
    }

    /**
     * @return list<DocumentGenerator>
     */
    public function documentGenerators(): array
    {
        if (! $this->config->documentation) {
            return [];
        }

        return [
            new OverviewDocument,
            new ModulesDocument,
            new CodingStandardsDocument,
            new TestingDocument,
            new SecurityDocument,
            new SpecsReadme,
            new AdrReadme,
        ];
    }

    /**
     * @param  list<string>|null  $names  explicit selection; null uses configuration
     * @return list<AgentAdapter>
     *
     * @throws InvalidArgumentException for unknown adapter names
     */
    public function adapters(?array $names = null): array
    {
        $all = [
            'generic' => new GenericAdapter,
            'codex' => new CodexAdapter,
            'claude' => new ClaudeCodeAdapter,
            'cursor' => new CursorAdapter,
        ];

        $names ??= $this->config->enabledAdapters();
        $unknown = array_diff($names, array_keys($all));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown adapter(s): '.implode(', ', $unknown).'. Available: '.implode(', ', array_keys($all)).'.');
        }

        return array_values(array_intersect_key($all, array_flip($names)));
    }

    /**
     * @return list<Rule>
     */
    public function rules(): array
    {
        $rules = [
            new BlueprintIntegrityRule,
            new MissingReferencedFileRule,
            new ManagedFileConflictRule,
            new TaskBriefSectionsRule,
            new UnconfirmedDecisionRule,
            new AnalyzerFailureRule,
            new SensitiveFileRule,
            new SchemaVersionRule,
            new OutdatedArtifactRule,
        ];

        return array_values(array_filter($rules, fn (Rule $rule): bool => ! in_array($rule->id(), $this->config->disabledRules, true)));
    }

    public function artifactContext(?string $tasksDirectory = null): ArtifactContext
    {
        return new ArtifactContext(
            $this->config->outputDirectory,
            $this->config->docsDirectory,
            $tasksDirectory ?? $this->config->tasksDirectory,
            $this->config->documentation,
            array_map(fn (DocumentGenerator $g): string => $this->config->docsDirectory.'/'.$g->relativePath(), $this->documentGenerators()),
        );
    }

    public function projectName(): string
    {
        return basename($this->root);
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Generators\AgentContext;
use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;
use RuntimeException;

/**
 * Renders every managed document and agent instruction file for a blueprint.
 */
final class GenerateArtifacts
{
    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @param  list<string>|null  $adapters  null uses configuration
     * @return array<string, GeneratedArtifact> keyed by path
     */
    public function generate(Blueprint $blueprint, ?array $adapters = null): array
    {
        $context = $this->workspace->artifactContext();
        $artifacts = [];

        foreach ($this->workspace->documentGenerators() as $generator) {
            $this->add($artifacts, $generator->generate($blueprint, $context));
        }

        $agentContext = new AgentContext($blueprint, $context);
        foreach ($this->workspace->adapters($adapters) as $adapter) {
            foreach ($adapter->render($agentContext) as $artifact) {
                $this->add($artifacts, $artifact);
            }
        }

        ksort($artifacts);

        return $artifacts;
    }

    /**
     * @param  array<string, GeneratedArtifact>  $artifacts
     */
    private function add(array &$artifacts, GeneratedArtifact $artifact): void
    {
        $existing = $artifacts[$artifact->path] ?? null;
        if ($existing !== null && $existing->content !== $artifact->content) {
            throw new RuntimeException("Two generators produced different content for [{$artifact->path}] ({$existing->generator}, {$artifact->generator}).");
        }
        $artifacts[$artifact->path] = $artifact;
    }
}

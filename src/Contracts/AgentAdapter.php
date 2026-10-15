<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Contracts;

use AndrewProd\AiBlueprint\Generators\AgentContext;
use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;

/**
 * Renders instruction files for one AI coding tool. Adapters only produce file
 * contents; they never assume a programmatic API for controlling the agent.
 */
interface AgentAdapter
{
    public function name(): string;

    /**
     * @return list<string> project-relative paths this adapter may write
     */
    public function supportedFiles(): array;

    /**
     * @return iterable<GeneratedArtifact>
     */
    public function render(AgentContext $context): iterable;
}

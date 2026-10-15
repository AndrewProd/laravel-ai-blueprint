<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Adapters\Codex;

use AndrewProd\AiBlueprint\Adapters\Generic\GenericAdapter;
use AndrewProd\AiBlueprint\Contracts\AgentAdapter;
use AndrewProd\AiBlueprint\Generators\AgentContext;
use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;
use AndrewProd\AiBlueprint\Support\Version;

/**
 * Codex reads AGENTS.md from the repository, so this adapter produces the same
 * file as the generic adapter. Enabling both writes AGENTS.md once.
 */
final class CodexAdapter implements AgentAdapter
{
    public function name(): string
    {
        return 'codex';
    }

    public function supportedFiles(): array
    {
        return ['AGENTS.md'];
    }

    public function render(AgentContext $context): iterable
    {
        yield new GeneratedArtifact('AGENTS.md', GenericAdapter::agentsFile($context), 'agents.md', 'instructions', Version::TEMPLATES);
    }
}

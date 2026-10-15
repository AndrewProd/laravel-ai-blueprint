<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Adapters\Generic;

use AndrewProd\AiBlueprint\Contracts\AgentAdapter;
use AndrewProd\AiBlueprint\Generators\AgentContext;
use AndrewProd\AiBlueprint\Generators\AgentInstructions\AgentInstructions;
use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;
use AndrewProd\AiBlueprint\Generators\Markdown;
use AndrewProd\AiBlueprint\Support\Version;

/**
 * AGENTS.md — the tool-neutral instruction file read by many coding agents.
 */
class GenericAdapter implements AgentAdapter
{
    public function name(): string
    {
        return 'generic';
    }

    public function supportedFiles(): array
    {
        return ['AGENTS.md'];
    }

    public function render(AgentContext $context): iterable
    {
        yield new GeneratedArtifact('AGENTS.md', self::agentsFile($context), 'agents.md', 'instructions', Version::TEMPLATES);
    }

    public static function agentsFile(AgentContext $context): string
    {
        return Markdown::document([
            Markdown::header('agents.md'),
            '# Agent instructions',
            'Instructions for AI coding agents working in this repository, generated from the project blueprint.',
            ...(new AgentInstructions($context))->all(),
        ]);
    }
}

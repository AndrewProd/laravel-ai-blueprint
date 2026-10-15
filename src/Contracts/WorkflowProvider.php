<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Contracts;

use AndrewProd\AiBlueprint\Domain\Workflows\WorkflowDefinition;

interface WorkflowProvider
{
    /**
     * @return iterable<WorkflowDefinition>
     */
    public function workflows(): iterable;

    /**
     * @throws \InvalidArgumentException for unknown workflows
     */
    public function get(string $name): WorkflowDefinition;
}

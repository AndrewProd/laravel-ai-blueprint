<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Rules;

use AndrewProd\AiBlueprint\Domain\Findings\Severity;

/**
 * One failed check reported by a Rule.
 */
final class Violation
{
    public function __construct(
        public readonly string $ruleId,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly ?string $path = null,
        public readonly ?string $hint = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'rule' => $this->ruleId,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'path' => $this->path,
            'hint' => $this->hint,
        ], static fn (?string $v): bool => $v !== null);
    }
}

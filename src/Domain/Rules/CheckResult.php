<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Rules;

use AndrewProd\AiBlueprint\Domain\Findings\Severity;

final class CheckResult
{
    /**
     * @param  list<Violation>  $violations
     * @param  list<string>  $rulesRun
     */
    public function __construct(
        public readonly array $violations,
        public readonly array $rulesRun,
    ) {}

    public function count(Severity $severity): int
    {
        return count(array_filter($this->violations, static fn (Violation $v): bool => $v->severity === $severity));
    }

    public function passes(bool $strict): bool
    {
        if ($this->count(Severity::Error) > 0) {
            return false;
        }

        return ! $strict || $this->count(Severity::Warning) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $strict): array
    {
        return [
            'passed' => $this->passes($strict),
            'strict' => $strict,
            'summary' => [
                'errors' => $this->count(Severity::Error),
                'warnings' => $this->count(Severity::Warning),
                'info' => $this->count(Severity::Info),
            ],
            'rules' => $this->rulesRun,
            'violations' => array_map(static fn (Violation $v): array => $v->toArray(), $this->violations),
        ];
    }
}

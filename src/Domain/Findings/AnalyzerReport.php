<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Findings;

/**
 * How completely an analyzer covered its area.
 */
final class AnalyzerReport
{
    public const COMPLETE = 'complete';

    public const PARTIAL = 'partial';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    /**
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $analyzer,
        public readonly string $status,
        public readonly array $notes = [],
    ) {}

    /**
     * @return array{analyzer: string, status: string, notes: list<string>}
     */
    public function toArray(): array
    {
        return ['analyzer' => $this->analyzer, 'status' => $this->status, 'notes' => $this->notes];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self((string) $data['analyzer'], (string) $data['status'], array_values($data['notes'] ?? []));
    }
}

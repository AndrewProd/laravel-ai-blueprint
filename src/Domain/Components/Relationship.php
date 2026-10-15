<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Components;

/**
 * A directed link between two components, e.g. route -> controller.
 */
final class Relationship
{
    /**
     * @param  list<Evidence>  $evidence
     */
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $type,
        public readonly Status $status,
        public readonly array $evidence = [],
    ) {}

    public function key(): string
    {
        return $this->from.'|'.$this->type.'|'.$this->to;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'type' => $this->type,
            'status' => $this->status->value,
            'evidence' => array_map(static fn (Evidence $e): array => $e->toArray(), $this->evidence),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['from'],
            (string) $data['to'],
            (string) $data['type'],
            Status::from((string) $data['status']),
            array_map(static fn (array $e): Evidence => Evidence::fromArray($e), $data['evidence'] ?? []),
        );
    }
}

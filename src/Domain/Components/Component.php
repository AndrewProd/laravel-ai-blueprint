<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Components;

/**
 * A discovered element of the project: model, controller, route, migration, test, ...
 */
final class Component
{
    /**
     * @param  list<Evidence>  $evidence
     * @param  array<string, mixed>  $attributes  JSON-serializable details (namespace, uri, columns, ...)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly ?string $path,
        public readonly Status $status,
        public readonly array $evidence = [],
        public readonly array $attributes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'path' => $this->path,
            'status' => $this->status->value,
            'evidence' => array_map(static fn (Evidence $e): array => $e->toArray(), $this->evidence),
        ];

        if ($this->attributes !== []) {
            $data['attributes'] = $this->attributes;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['id'],
            (string) $data['type'],
            (string) $data['name'],
            isset($data['path']) ? (string) $data['path'] : null,
            Status::from((string) $data['status']),
            array_map(static fn (array $e): Evidence => Evidence::fromArray($e), $data['evidence'] ?? []),
            $data['attributes'] ?? [],
        );
    }
}

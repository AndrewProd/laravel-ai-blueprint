<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Parsers;

/**
 * @phpstan-type MethodShape array{name: string, visibility: string, line: int, first_param_type: string|null}
 * @phpstan-type ClassShape array{kind: string, name: string, fqcn: string, line: int, abstract: bool, extends: string|null, implements: list<string>, traits: list<string>, methods: list<MethodShape>}
 */
final class PhpFileSymbols
{
    /**
     * @param  array<string, string>  $uses  lower-case alias => FQCN
     * @param  array<string, int>  $useLines  FQCN => line
     * @param  list<ClassShape>  $classes
     */
    public function __construct(
        public readonly string $namespace,
        public readonly array $uses,
        public readonly array $useLines,
        public readonly array $classes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'namespace' => $this->namespace,
            'uses' => $this->uses,
            'use_lines' => $this->useLines,
            'classes' => $this->classes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['namespace'], $data['uses'], $data['use_lines'], $data['classes']);
    }
}

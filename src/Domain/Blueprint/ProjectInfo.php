<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Blueprint;

final class ProjectInfo
{
    /**
     * @param  list<string>  $frontend  e.g. ["vue", "vite"]
     * @param  list<string>  $tools  e.g. ["phpunit", "phpstan"]
     * @param  array<string, string>  $packages  notable composer/npm packages => constraint
     */
    public function __construct(
        public readonly string $name,
        public readonly string $framework,
        public readonly ?string $frameworkVersion = null,
        public readonly ?string $php = null,
        public readonly array $frontend = [],
        public readonly array $tools = [],
        public readonly array $packages = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'framework' => $this->framework,
            'framework_version' => $this->frameworkVersion,
            'php' => $this->php,
            'frontend' => $this->frontend,
            'tools' => $this->tools,
            'packages' => (object) $this->packages,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['name'],
            (string) $data['framework'],
            isset($data['framework_version']) ? (string) $data['framework_version'] : null,
            isset($data['php']) ? (string) $data['php'] : null,
            array_values($data['frontend'] ?? []),
            array_values($data['tools'] ?? []),
            (array) ($data['packages'] ?? []),
        );
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Components;

final class Evidence
{
    public function __construct(
        public readonly string $path,
        public readonly ?int $line = null,
        public readonly ?string $symbol = null,
    ) {}

    /**
     * @return array{path: string, line?: int, symbol?: string}
     */
    public function toArray(): array
    {
        $data = ['path' => $this->path];
        if ($this->line !== null) {
            $data['line'] = $this->line;
        }
        if ($this->symbol !== null) {
            $data['symbol'] = $this->symbol;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['path'],
            isset($data['line']) ? (int) $data['line'] : null,
            isset($data['symbol']) ? (string) $data['symbol'] : null,
        );
    }
}

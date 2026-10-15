<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Findings;

/**
 * Something an analyzer noticed: a parse failure, an ambiguity, a skipped area.
 */
final class Finding
{
    public function __construct(
        public readonly string $code,
        public readonly Severity $severity,
        public readonly string $message,
        public readonly ?string $path = null,
        public readonly ?string $source = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'path' => $this->path,
            'source' => $this->source,
        ], static fn (?string $v): bool => $v !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['code'],
            Severity::from((string) $data['severity']),
            (string) $data['message'],
            isset($data['path']) ? (string) $data['path'] : null,
            isset($data['source']) ? (string) $data['source'] : null,
        );
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Architecture;

/**
 * A decision confirmed by a human via `ai-blueprint:review` (or by editing decisions.json).
 */
final class ArchitectureDecision
{
    public function __construct(
        public readonly string $key,
        public readonly string $question,
        public readonly string $answer,
        public readonly string $decidedAt,
    ) {}

    /**
     * @return array{key: string, question: string, answer: string, status: string, decided_at: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'question' => $this->question,
            'answer' => $this->answer,
            'status' => 'confirmed',
            'decided_at' => $this->decidedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['key'],
            (string) ($data['question'] ?? ''),
            (string) ($data['answer'] ?? ''),
            (string) ($data['decided_at'] ?? ''),
        );
    }
}

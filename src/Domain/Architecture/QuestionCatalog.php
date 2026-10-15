<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Domain\Architecture;

/**
 * Architectural questions that source code alone cannot answer reliably.
 * They stay "unknown" until a human confirms them.
 */
final class QuestionCatalog
{
    /**
     * @return array<string, string> key => question
     */
    public static function all(): array
    {
        return [
            'module_boundaries' => 'How is the application split into modules or bounded areas, and which code owns what?',
            'business_logic_location' => 'Where should business logic live (models, services, actions, jobs, ...)?',
            'transaction_ownership' => 'Which layer opens and owns database transactions?',
            'external_api_retry_policy' => 'How should calls to external APIs handle timeouts, retries and idempotency?',
            'authorization_strategy' => 'How is authorization enforced (policies, gates, middleware, ...)?',
            'api_versioning' => 'How are public API contracts versioned and kept backward compatible?',
            'queue_usage' => 'Which work must run on queues, and what are the retry/failure rules?',
            'testing_expectations' => 'Which tests are required for a change (unit, feature, browser, contract)?',
        ];
    }

    public static function question(string $key): string
    {
        return self::all()[$key] ?? $key;
    }
}

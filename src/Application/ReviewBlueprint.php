<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Domain\Architecture\ArchitectureDecision;
use AndrewProd\AiBlueprint\Domain\Architecture\QuestionCatalog;
use InvalidArgumentException;

/**
 * Records human-confirmed architecture decisions in decisions.json.
 */
final class ReviewBlueprint
{
    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @return array<string, string> key => question, for questions without a confirmed answer
     */
    public function pending(): array
    {
        $decided = array_map(static fn (ArchitectureDecision $d): string => $d->key, $this->workspace->store->loadDecisions());

        return array_diff_key(QuestionCatalog::all(), array_flip($decided));
    }

    /**
     * @return list<ArchitectureDecision>
     */
    public function confirmed(): array
    {
        return $this->workspace->store->loadDecisions();
    }

    /**
     * @param  array<string, string>  $answers  key => answer
     */
    public function record(array $answers): void
    {
        $catalog = QuestionCatalog::all();
        $decisions = [];
        foreach ($this->workspace->store->loadDecisions() as $decision) {
            $decisions[$decision->key] = $decision;
        }

        foreach ($answers as $key => $answer) {
            if (! isset($catalog[$key])) {
                throw new InvalidArgumentException("Unknown decision key [{$key}]. Available: ".implode(', ', array_keys($catalog)).'.');
            }
            $answer = trim($answer);
            if ($answer === '') {
                throw new InvalidArgumentException("Answer for [{$key}] must not be empty.");
            }
            $decisions[$key] = new ArchitectureDecision($key, $catalog[$key], $answer, $this->workspace->now());
        }

        $this->workspace->store->saveDecisions(array_values($decisions));
    }

    public function forget(string $key): void
    {
        $decisions = array_values(array_filter(
            $this->workspace->store->loadDecisions(),
            static fn (ArchitectureDecision $d): bool => $d->key !== $key,
        ));
        $this->workspace->store->saveDecisions($decisions);
    }
}

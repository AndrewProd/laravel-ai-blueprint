<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\ReviewBlueprint;
use AndrewProd\AiBlueprint\Application\Workspace;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;

final class ReviewCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:review
        {--set=* : Record an answer non-interactively, as key=answer}
        {--forget=* : Remove a confirmed decision by key}
        {--list : Only list confirmed and open decisions}';

    protected $description = 'Confirm architecture decisions that cannot be inferred from code';

    protected function perform(Workspace $workspace): int
    {
        $review = new ReviewBlueprint($workspace);
        $changed = false;

        foreach ((array) $this->option('forget') as $key) {
            $review->forget((string) $key);
            $this->components->info("Removed decision [{$key}].");
            $changed = true;
        }

        $answers = [];
        foreach ((array) $this->option('set') as $pair) {
            if (! str_contains((string) $pair, '=')) {
                $this->components->error("--set expects key=answer, got [{$pair}].");

                return self::FAILURE;
            }
            [$key, $answer] = explode('=', (string) $pair, 2);
            $answers[trim($key)] = $answer;
        }

        if ($answers === [] && ! $changed && ! $this->option('list') && $this->input->isInteractive()) {
            foreach ($review->pending() as $key => $question) {
                $this->newLine();
                $this->line("<options=bold>{$key}</>");
                $answer = $this->ask($question.' (leave empty to skip)');
                if (is_string($answer) && trim($answer) !== '') {
                    $answers[$key] = $answer;
                }
            }
        }

        if ($answers !== []) {
            $review->record($answers);
            $this->components->info(count($answers).' decision(s) recorded in '.$workspace->store->decisionsPath().'.');
            $changed = true;
        }

        if ($changed) {
            try {
                $blueprint = $workspace->store->loadBlueprint();
                $workspace->store->saveBlueprint($blueprint->withDecisions($review->confirmed()));
                $this->components->info('blueprint.json updated. Run `php artisan ai-blueprint:sync` to update generated documents.');
            } catch (InvalidBlueprint) {
                $this->components->warn('blueprint.json not found or invalid; run `php artisan ai-blueprint:scan` to include these decisions.');
            }
        }

        $this->newLine();
        foreach ($review->confirmed() as $decision) {
            $this->components->twoColumnDetail($decision->key, '<fg=green>'.$decision->answer.'</>');
        }
        foreach ($review->pending() as $key => $question) {
            $this->components->twoColumnDetail($key, '<fg=yellow>unknown</>');
        }

        return self::SUCCESS;
    }
}

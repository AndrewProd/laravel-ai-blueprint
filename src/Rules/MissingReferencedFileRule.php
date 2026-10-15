<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Rules;

use AndrewProd\AiBlueprint\Contracts\Rule;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;
use AndrewProd\AiBlueprint\Domain\Rules\CheckContext;
use AndrewProd\AiBlueprint\Domain\Rules\Violation;
use AndrewProd\AiBlueprint\Support\Paths;

/**
 * BP002: managed documents must not point at files that do not exist.
 * Checks markdown links and backticked project paths (containing "/").
 */
final class MissingReferencedFileRule implements Rule
{
    public function id(): string
    {
        return 'BP002';
    }

    public function description(): string
    {
        return 'Files referenced by managed documentation exist.';
    }

    public function evaluate(Blueprint $blueprint, CheckContext $context): iterable
    {
        foreach (array_keys($context->manifest) as $document) {
            if (! str_ends_with($document, '.md') && ! str_ends_with($document, '.mdc')) {
                continue;
            }
            $content = $context->read($document);
            if ($content === null) {
                continue; // reported by BP003
            }

            foreach ($this->references($content, $document) as $reference) {
                if (! $context->exists($reference) && ! $context->exists(rtrim($reference, '/').'/.')) {
                    yield new Violation($this->id(), Severity::Warning, "References missing file [{$reference}].", $document, 'Run `php artisan ai-blueprint:scan` and `ai-blueprint:sync`, or fix the reference.');
                }
            }
        }
    }

    /**
     * @return list<string> normalized project-relative paths
     */
    public function references(string $markdown, string $document): array
    {
        $found = [];

        // Fenced code blocks contain commands and examples, not references.
        $markdown = (string) preg_replace('/```.*?```/s', '', $markdown);

        preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $markdown, $links);
        foreach ($links[1] as $link) {
            if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $link) === 1 || str_starts_with($link, '#')) {
                continue;
            }
            $link = explode('#', $link)[0];
            $base = str_starts_with($link, '/') ? '' : (dirname($document) === '.' ? '' : dirname($document));
            $found[] = ltrim($base.'/'.ltrim($link, '/'), '/');
        }

        preg_match_all('/`([^`\s]+)`/', $markdown, $codes);
        foreach ($codes[1] as $code) {
            if (! str_contains($code, '/') || preg_match('/[<>*{}$?|]/', $code) === 1 || str_starts_with($code, '-') || str_contains($code, '::') || str_contains($code, '\\')) {
                continue;
            }
            if (preg_match('#^(https?:)?//#', $code) === 1 || preg_match('#^(vendor|node_modules)/#', $code) === 1) {
                continue;
            }
            $found[] = $code;
        }

        $paths = [];
        foreach ($found as $path) {
            try {
                $normalized = Paths::normalize($path);
            } catch (\InvalidArgumentException) {
                continue;
            }
            if ($normalized !== '') {
                $paths[$normalized] = true;
            }
        }

        return array_keys($paths);
    }
}

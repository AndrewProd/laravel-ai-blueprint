<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Filesystem;

/**
 * Registry of files written by the package, used to tell generated content
 * apart from manual edits.
 *
 * "section" entries track only the block between ManagedSection markers.
 *
 * @phpstan-type Entry array{type: string, generator: string, template_version: string, hash: string, generated_at: string, mode: string}
 */
final class Manifest
{
    /**
     * @param  array<string, Entry>  $files
     */
    public function __construct(private array $files = []) {}

    public static function hash(string $content): string
    {
        // Normalize line endings so git autocrlf does not look like a manual edit.
        return 'sha256:'.hash('sha256', str_replace("\r\n", "\n", $content));
    }

    /**
     * @return Entry|null
     */
    public function get(string $path): ?array
    {
        return $this->files[$path] ?? null;
    }

    public function has(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function record(string $path, string $type, string $generator, string $templateVersion, string $content, string $generatedAt, string $mode = 'file'): void
    {
        $this->files[$path] = [
            'type' => $type,
            'generator' => $generator,
            'template_version' => $templateVersion,
            'hash' => self::hash($content),
            'generated_at' => $generatedAt,
            'mode' => $mode,
        ];
        ksort($this->files);
    }

    public function forget(string $path): void
    {
        unset($this->files[$path]);
    }

    /**
     * @return array<string, Entry>
     */
    public function all(): array
    {
        return $this->files;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['schema_version' => '1.0', 'files' => (object) $this->files];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $files = [];
        foreach ((array) ($data['files'] ?? []) as $path => $entry) {
            if (is_string($path) && is_array($entry) && is_string($entry['hash'] ?? null)) {
                $files[$path] = [
                    'type' => (string) ($entry['type'] ?? 'unknown'),
                    'generator' => (string) ($entry['generator'] ?? 'unknown'),
                    'template_version' => (string) ($entry['template_version'] ?? ''),
                    'hash' => $entry['hash'],
                    'generated_at' => (string) ($entry['generated_at'] ?? ''),
                    'mode' => ($entry['mode'] ?? 'file') === 'section' ? 'section' : 'file',
                ];
            }
        }

        return new self($files);
    }
}

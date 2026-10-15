<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Cache;

/**
 * Per-file parse cache keyed by content hash and analyzer version, so a cache
 * entry is only reused for byte-identical input parsed by the same code.
 * It stores derived symbols only, never file contents.
 */
final class AnalysisCache
{
    /** @var array<string, array{hash: string, version: string, data: mixed}> */
    private array $entries = [];

    /** @var array<string, true> */
    private array $touched = [];

    private bool $dirty = false;

    public function __construct(private readonly ?string $file)
    {
        if ($file !== null && is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded) && ($decoded['format'] ?? null) === 1 && is_array($decoded['entries'] ?? null)) {
                $this->entries = $decoded['entries'];
            }
        }
    }

    public static function inMemory(): self
    {
        return new self(null);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return T|mixed
     */
    public function remember(string $analyzer, string $version, string $path, string $contents, callable $compute): mixed
    {
        $key = $analyzer.':'.$path;
        $hash = hash('sha256', $contents);
        $entry = $this->entries[$key] ?? null;
        $this->touched[$key] = true;

        if ($entry !== null && $entry['hash'] === $hash && $entry['version'] === $version) {
            return $entry['data'];
        }

        $data = $compute();
        $this->entries[$key] = ['hash' => $hash, 'version' => $version, 'data' => $data];
        $this->dirty = true;

        return $data;
    }

    /**
     * Persist, dropping entries for files that were not analyzed in this run.
     */
    public function persist(): void
    {
        if ($this->file === null) {
            return;
        }

        $before = count($this->entries);
        $this->entries = array_intersect_key($this->entries, $this->touched);
        $this->dirty = $this->dirty || $before !== count($this->entries);

        if (! $this->dirty) {
            return;
        }

        $directory = dirname($this->file);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        ksort($this->entries);
        file_put_contents($this->file, json_encode(['format' => 1, 'entries' => $this->entries], JSON_UNESCAPED_SLASHES));
        $this->dirty = false;
    }
}

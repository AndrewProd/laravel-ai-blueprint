<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Filesystem;

use AndrewProd\AiBlueprint\Domain\Architecture\ArchitectureDecision;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Blueprint\BlueprintValidator;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;

/**
 * Reads and writes the JSON state files in the output directory.
 */
final class BlueprintStore
{
    public function __construct(
        private readonly SafeFileWriter $writer,
        private readonly string $directory,
        private readonly BlueprintValidator $validator = new BlueprintValidator,
    ) {}

    public function blueprintPath(): string
    {
        return $this->directory.'/blueprint.json';
    }

    public function decisionsPath(): string
    {
        return $this->directory.'/decisions.json';
    }

    public function manifestPath(): string
    {
        return $this->directory.'/manifest.json';
    }

    public function cachePath(): string
    {
        return $this->directory.'/cache/analysis.json';
    }

    public function hasBlueprint(): bool
    {
        return $this->writer->exists($this->blueprintPath());
    }

    /**
     * @throws InvalidBlueprint when missing, unreadable, invalid or unsupported
     */
    public function loadBlueprint(): Blueprint
    {
        $contents = $this->writer->read($this->blueprintPath());
        if ($contents === null) {
            throw InvalidBlueprint::corrupt("{$this->blueprintPath()} not found. Run `php artisan ai-blueprint:init`.");
        }

        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw InvalidBlueprint::corrupt("{$this->blueprintPath()} is not valid JSON: ".json_last_error_msg().'.');
        }

        $this->validator->assertValid($data);

        return Blueprint::fromArray($data);
    }

    public function saveBlueprint(Blueprint $blueprint): void
    {
        $this->writer->write($this->blueprintPath(), self::encode($blueprint->toArray()));
    }

    /**
     * @return array<string, mixed>|null raw decoded decisions file, null when absent or invalid
     */
    public function rawDecisions(): ?array
    {
        $contents = $this->writer->read($this->decisionsPath());
        if ($contents === null) {
            return null;
        }
        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Confirmed decisions with a non-empty answer. Entries that claim to be
     * confirmed without an answer are ignored here and reported by BP005.
     *
     * @return list<ArchitectureDecision>
     */
    public function loadDecisions(): array
    {
        $decisions = [];
        foreach ((array) ($this->rawDecisions()['decisions'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['status'] ?? null) === 'confirmed' && is_string($entry['key'] ?? null) && trim((string) ($entry['answer'] ?? '')) !== '') {
                $decisions[] = ArchitectureDecision::fromArray($entry);
            }
        }

        return $decisions;
    }

    /**
     * @param  list<ArchitectureDecision>  $decisions
     */
    public function saveDecisions(array $decisions): void
    {
        usort($decisions, static fn (ArchitectureDecision $a, ArchitectureDecision $b): int => strcmp($a->key, $b->key));
        $this->writer->write($this->decisionsPath(), self::encode([
            'schema_version' => '1.0',
            'decisions' => array_map(static fn (ArchitectureDecision $d): array => $d->toArray(), $decisions),
        ]));
    }

    public function loadManifest(): Manifest
    {
        $contents = $this->writer->read($this->manifestPath());
        $data = $contents === null ? null : json_decode($contents, true);

        return Manifest::fromArray(is_array($data) ? $data : []);
    }

    public function hasValidManifest(): bool
    {
        $contents = $this->writer->read($this->manifestPath());

        return $contents !== null && is_array(json_decode($contents, true));
    }

    public function saveManifest(Manifest $manifest): void
    {
        $this->writer->write($this->manifestPath(), self::encode($manifest->toArray()));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }
}

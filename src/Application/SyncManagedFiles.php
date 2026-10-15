<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application;

use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\ManagedSection;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\Manifest;
use AndrewProd\AiBlueprint\Support\LineDiff;

/**
 * Writes generated files without destroying manual edits:
 * - new files are created;
 * - managed files untouched since generation are updated;
 * - existing Markdown files that Blueprint did not create (e.g. an AGENTS.md
 *   from Laravel Boost) get a delimited managed section appended; only that
 *   section is updated later and the rest of the file is never touched;
 * - edited managed content and other pre-existing files are reported as
 *   conflicts and only overwritten with $force;
 * - files are never deleted.
 */
final class SyncManagedFiles
{
    public function __construct(private readonly Workspace $workspace) {}

    /**
     * @param  array<string, GeneratedArtifact>  $artifacts
     * @return list<SyncItem>
     */
    public function plan(array $artifacts, Manifest $manifest): array
    {
        $items = [];
        foreach ($artifacts as $path => $artifact) {
            $current = $this->workspace->writer->read($path);
            $entry = $manifest->get($path);

            if ($current === null) {
                $items[] = new SyncItem($path, SyncItem::CREATE, $artifact);

                continue;
            }

            if ($entry !== null && $entry['mode'] === 'section') {
                $items[] = $this->planSection($path, $artifact, $current, $entry['hash']);

                continue;
            }

            if (Manifest::hash($current) === Manifest::hash($artifact->content)) {
                $items[] = new SyncItem($path, SyncItem::UNCHANGED, $artifact);

                continue;
            }

            $diff = LineDiff::unified($current, $artifact->content, $path.' (current)', $path.' (generated)');

            if ($entry === null) {
                $items[] = $this->supportsSection($path)
                    ? new SyncItem($path, SyncItem::APPEND, $artifact, null, mode: 'section', current: $current)
                    : new SyncItem($path, SyncItem::UNMANAGED, $artifact, $diff);
            } elseif (Manifest::hash($current) !== $entry['hash']) {
                $items[] = new SyncItem($path, SyncItem::CONFLICT, $artifact, $diff);
            } else {
                $items[] = new SyncItem($path, SyncItem::UPDATE, $artifact, $diff);
            }
        }

        return $items;
    }

    /**
     * @param  list<SyncItem>  $items
     */
    public function apply(array $items, Manifest $manifest, bool $force = false, bool $dryRun = false): void
    {
        $now = $this->workspace->now();
        $changed = false;

        foreach ($items as $item) {
            $artifact = $item->artifact;
            $write = in_array($item->action, [SyncItem::CREATE, SyncItem::UPDATE, SyncItem::APPEND], true) || ($force && $item->isConflict());

            if ($write) {
                if (! $dryRun) {
                    $this->workspace->writer->write($item->path, $this->contentToWrite($item));
                }
                $item->written = true;
            }

            $adopt = $item->action === SyncItem::UNCHANGED && ! $this->manifestMatches($manifest, $artifact);
            if ($write || $adopt) {
                $manifest->record($item->path, $artifact->type, $artifact->generator, $artifact->templateVersion, $artifact->content, $now, $item->mode);
                $changed = true;
            }
        }

        if ($changed && ! $dryRun) {
            $this->workspace->store->saveManifest($manifest);
        }
    }

    private function planSection(string $path, GeneratedArtifact $artifact, string $current, string $recordedHash): SyncItem
    {
        $section = ManagedSection::extract($current);

        if ($section === null) {
            // The user removed the block: put it back at the end, keeping everything else.
            return new SyncItem($path, SyncItem::APPEND, $artifact, null, mode: 'section', current: $current);
        }

        if (Manifest::hash($section) === Manifest::hash($artifact->content)) {
            return new SyncItem($path, SyncItem::UNCHANGED, $artifact, mode: 'section', current: $current);
        }

        $diff = LineDiff::unified($section, $artifact->content, $path.' (managed section)', $path.' (generated)');
        $action = Manifest::hash($section) === $recordedHash ? SyncItem::UPDATE : SyncItem::CONFLICT;

        return new SyncItem($path, $action, $artifact, $diff, mode: 'section', current: $current);
    }

    private function contentToWrite(SyncItem $item): string
    {
        if ($item->mode !== 'section' || $item->current === null) {
            return $item->artifact->content;
        }

        return $item->action === SyncItem::APPEND
            ? ManagedSection::append($item->current, $item->artifact->content)
            : ManagedSection::replace($item->current, $item->artifact->content);
    }

    /**
     * Only plain Markdown can safely host an appended section; files with front
     * matter (.mdc) or other formats are reported as conflicts instead.
     */
    private function supportsSection(string $path): bool
    {
        return str_ends_with($path, '.md');
    }

    private function manifestMatches(Manifest $manifest, GeneratedArtifact $artifact): bool
    {
        $entry = $manifest->get($artifact->path);

        return $entry !== null && $entry['hash'] === Manifest::hash($artifact->content) && $entry['template_version'] === $artifact->templateVersion;
    }
}

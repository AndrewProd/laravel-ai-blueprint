<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Application\SyncItem;
use AndrewProd\AiBlueprint\Application\SyncManagedFiles;
use AndrewProd\AiBlueprint\Generators\GeneratedArtifact;
use AndrewProd\AiBlueprint\Tests\TestCase;

final class SyncManagedFilesTest extends TestCase
{
    public function test_it_creates_updates_and_protects_manual_edits(): void
    {
        $root = $this->temporaryDirectory();
        $workspace = $this->workspace($root);
        $sync = new SyncManagedFiles($workspace);

        $first = $this->artifacts(['A.md' => "a1\n", 'B.md' => "b1\n", 'C.mdc' => "c1\n"]);
        $this->putFile($root, 'C.mdc', "user's own file\n");

        $manifest = $workspace->store->loadManifest();
        $items = $sync->plan($first, $manifest);
        $this->assertSame(['create', 'create', 'unmanaged'], $this->actions($items));
        $sync->apply($items, $manifest);

        $this->assertSame("a1\n", file_get_contents($root.'/A.md'));
        $this->assertSame("user's own file\n", file_get_contents($root.'/C.mdc'), 'pre-existing files are not overwritten');
        $this->assertFalse($workspace->store->loadManifest()->has('C.mdc'));

        // A.md edited by hand, B.md untouched, generated content changes for both.
        file_put_contents($root.'/A.md', "a1\nmy notes\n");
        $second = $this->artifacts(['A.md' => "a2\n", 'B.md' => "b2\n", 'C.mdc' => "c1\n"]);
        $manifest = $workspace->store->loadManifest();
        $items = $sync->plan($second, $manifest);
        $this->assertSame(['conflict', 'update', 'unmanaged'], $this->actions($items));
        $this->assertStringContainsString('+a2', (string) $items[0]->diff);
        $this->assertStringContainsString('-my notes', (string) $items[0]->diff);

        $sync->apply($items, $manifest, dryRun: true);
        $this->assertSame("b1\n", file_get_contents($root.'/B.md'), 'dry run writes nothing');

        $sync->apply($items, $manifest);
        $this->assertSame("a1\nmy notes\n", file_get_contents($root.'/A.md'));
        $this->assertSame("b2\n", file_get_contents($root.'/B.md'));

        // Unchanged generated content is a no-op; force overwrites conflicts.
        $manifest = $workspace->store->loadManifest();
        $items = $sync->plan($second, $manifest);
        $this->assertSame(['conflict', 'unchanged', 'unmanaged'], $this->actions($items));
        $sync->apply($items, $manifest, force: true);
        $this->assertSame("a2\n", file_get_contents($root.'/A.md'));
        $this->assertSame("c1\n", file_get_contents($root.'/C.mdc'));
        $this->assertTrue($workspace->store->loadManifest()->has('C.mdc'));
    }

    public function test_identical_existing_file_is_adopted_and_unknown_files_are_never_deleted(): void
    {
        $root = $this->temporaryDirectory();
        $workspace = $this->workspace($root);
        $sync = new SyncManagedFiles($workspace);
        $this->putFile($root, 'A.md', "same\n");
        $this->putFile($root, 'docs/notes.md', "mine\n");

        $manifest = $workspace->store->loadManifest();
        $items = $sync->plan($this->artifacts(['A.md' => "same\n"]), $manifest);
        $this->assertSame(['unchanged'], $this->actions($items));
        $sync->apply($items, $manifest);

        $this->assertTrue($workspace->store->loadManifest()->has('A.md'));
        $this->assertSame("mine\n", file_get_contents($root.'/docs/notes.md'));
    }

    /**
     * @param  array<string, string>  $files
     * @return array<string, GeneratedArtifact>
     */
    private function artifacts(array $files): array
    {
        $artifacts = [];
        foreach ($files as $path => $content) {
            $artifacts[$path] = new GeneratedArtifact($path, $content, 'test', 'documentation', '1');
        }

        return $artifacts;
    }

    /**
     * @param  list<SyncItem>  $items
     * @return list<string>
     */
    private function actions(array $items): array
    {
        return array_map(static fn (SyncItem $i): string => $i->action, $items);
    }
}

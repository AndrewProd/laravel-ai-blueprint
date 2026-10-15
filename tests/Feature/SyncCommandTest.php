<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Feature;

use AndrewProd\AiBlueprint\Tests\TestCase;

final class SyncCommandTest extends TestCase
{
    public function test_sync_updates_untouched_files_and_keeps_edited_ones(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->artisan('ai-blueprint:sync', ['--no-interaction' => true])
            ->expectsOutputToContain('up to date')
            ->assertSuccessful();

        file_put_contents($root.'/docs/architecture/overview.md', "# Ours\n");
        $this->putFile($root, 'app/Models/Invoice.php', "<?php\nnamespace App\\Models;\nuse Illuminate\\Database\\Eloquent\\Model;\nclass Invoice extends Model {}\n");
        $this->artisan('ai-blueprint:scan')->assertSuccessful();

        $this->artisan('ai-blueprint:sync', ['--dry-run' => true, '--no-interaction' => true])
            ->expectsOutputToContain('-# Ours')
            ->expectsOutputToContain('would update')
            ->assertSuccessful();
        $this->assertStringNotContainsString('Invoice', (string) file_get_contents($root.'/docs/architecture/modules.md'));

        $this->artisan('ai-blueprint:sync', ['--no-interaction' => true])
            ->expectsOutputToContain('skipped: edited manually')
            ->assertSuccessful();
        $this->assertStringContainsString('Invoice', (string) file_get_contents($root.'/docs/architecture/modules.md'));
        $this->assertSame("# Ours\n", file_get_contents($root.'/docs/architecture/overview.md'));

        $this->artisan('ai-blueprint:sync', ['--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('overwritten')
            ->assertSuccessful();
        $this->assertStringContainsString('# Architecture overview', (string) file_get_contents($root.'/docs/architecture/overview.md'));
    }

    public function test_interactive_confirmation_before_overwrite(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();
        file_put_contents($root.'/AGENTS.md', "mine\n");

        $this->artisan('ai-blueprint:sync')
            ->expectsConfirmation('AGENTS.md was edited manually. Overwrite it with the generated version? Your changes will be lost.', 'no')
            ->assertSuccessful();
        $this->assertSame("mine\n", file_get_contents($root.'/AGENTS.md'));

        $this->artisan('ai-blueprint:sync')
            ->expectsConfirmation('AGENTS.md was edited manually. Overwrite it with the generated version? Your changes will be lost.', 'yes')
            ->assertSuccessful();
        $this->assertStringContainsString('# Agent instructions', (string) file_get_contents($root.'/AGENTS.md'));
    }

    public function test_sync_without_blueprint_fails_clearly(): void
    {
        $this->useFixture();

        $this->artisan('ai-blueprint:sync')->expectsOutputToContain('ai-blueprint:init')->assertFailed();
    }
}

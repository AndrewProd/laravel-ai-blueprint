<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Feature;

use AndrewProd\AiBlueprint\Tests\TestCase;

final class TaskCommandTest extends TestCase
{
    public function test_it_creates_a_task_brief(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->artisan('ai-blueprint:task', [
            'title' => 'Stripe webhook retries',
            '--type' => 'integration',
            '--description' => 'Retry failed Stripe webhooks safely.',
            '--adapter' => 'codex',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $directory = $root.'/docs/tasks/stripe-webhook-retries';
        $task = (string) file_get_contents($directory.'/task.md');
        $this->assertStringContainsString('# Stripe webhook retries', $task);
        $this->assertStringContainsString('Retry failed Stripe webhooks safely.', $task);
        $this->assertStringContainsString('Webhooks verify signatures and are idempotent.', $task);
        $this->assertStringContainsString('AGENTS.md', $task);

        $context = (string) file_get_contents($directory.'/context.md');
        $this->assertStringContainsString('`app/Http/Controllers/StripeWebhookController.php`', $context);
        $this->assertStringContainsString('integration: **Stripe**', $context);
        $this->assertFileExists($directory.'/checklist.md');

        $this->artisan('ai-blueprint:task', ['title' => 'Stripe webhook retries', '--no-interaction' => true])
            ->expectsOutputToContain('already exists')
            ->assertFailed();

        $this->artisan('ai-blueprint:check', ['--no-interaction' => true])->assertSuccessful();
    }

    public function test_invalid_input_is_rejected(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:scan')->assertSuccessful();

        $this->artisan('ai-blueprint:task', ['title' => 'X', '--type' => 'epic'])
            ->expectsOutputToContain('Unknown task type [epic]')
            ->assertFailed();

        $this->artisan('ai-blueprint:task', ['title' => 'X', '--adapter' => 'copilot'])
            ->expectsOutputToContain('Unknown adapter [copilot]')
            ->assertFailed();

        $this->artisan('ai-blueprint:task', ['title' => 'X', '--output' => '../outside'])
            ->expectsOutputToContain('must not point outside the project root')
            ->assertFailed();

        $this->artisan('ai-blueprint:task', ['title' => 'X', '--output' => '/tmp'])
            ->expectsOutputToContain('relative to the project root')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($root.'/docs/tasks');
    }

    public function test_dry_run_and_custom_output(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:scan')->assertSuccessful();

        $this->artisan('ai-blueprint:task', ['title' => 'Fix order totals', '--type' => 'bugfix', '--dry-run' => true])
            ->expectsOutputToContain('=== docs/tasks/fix-order-totals/task.md ===')
            ->assertSuccessful();
        $this->assertDirectoryDoesNotExist($root.'/docs/tasks');

        $this->artisan('ai-blueprint:task', ['title' => 'Fix order totals', '--output' => 'work/briefs'])->assertSuccessful();
        $this->assertFileExists($root.'/work/briefs/fix-order-totals/task.md');
    }

    public function test_task_requires_a_blueprint(): void
    {
        $this->useFixture();

        $this->artisan('ai-blueprint:task', ['title' => 'Anything'])
            ->expectsOutputToContain('Run `php artisan ai-blueprint:init`')
            ->assertFailed();
    }
}

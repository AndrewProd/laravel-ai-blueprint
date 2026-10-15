<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Feature;

use AndrewProd\AiBlueprint\Tests\TestCase;

final class InitCommandTest extends TestCase
{
    private const EXPECTED = [
        '.ai-blueprint/.gitignore',
        '.ai-blueprint/blueprint.json',
        '.ai-blueprint/decisions.json',
        '.ai-blueprint/manifest.json',
        '.claude/rules/ai-blueprint-architecture.md',
        '.claude/rules/ai-blueprint-security.md',
        '.cursor/rules/ai-blueprint-testing.mdc',
        '.cursor/rules/ai-blueprint.mdc',
        'AGENTS.md',
        'CLAUDE.md',
        'docs/adr/README.md',
        'docs/architecture/modules.md',
        'docs/architecture/overview.md',
        'docs/engineering/coding-standards.md',
        'docs/engineering/security.md',
        'docs/engineering/testing.md',
        'docs/specs/README.md',
    ];

    public function test_init_creates_blueprint_docs_and_instructions(): void
    {
        $root = $this->useFixture();
        $before = array_keys($this->allFiles($root));

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->expectsOutputToContain('Next step: php artisan ai-blueprint:review')
            ->assertSuccessful();

        $created = array_values(array_diff(array_keys($this->allFiles($root)), $before));
        $created = array_values(array_filter($created, static fn (string $p): bool => ! str_starts_with($p, '.ai-blueprint/cache/')));
        sort($created);
        $this->assertSame(self::EXPECTED, $created);

        $manifest = $this->readJson($root.'/.ai-blueprint/manifest.json');
        $this->assertCount(13, (array) $manifest['files']);

        $overview = (string) file_get_contents($root.'/docs/architecture/overview.md');
        $this->assertStringContainsString('Preliminary map, automatically discovered from the code', $overview);
        $this->assertStringContainsString('Laravel 12.10.0', $overview);
        $this->assertStringContainsString('**transaction_ownership**', $overview);

        $modules = (string) file_get_contents($root.'/docs/architecture/modules.md');
        $this->assertStringContainsString('| GET | /api/orders | OrderController@index |', $modules);
        $this->assertStringContainsString('OrderPlaced → SendOrderConfirmation', $modules);
        $this->assertStringContainsString('customer_id → customers (inferred)', $modules);

        $cursor = (string) file_get_contents($root.'/.cursor/rules/ai-blueprint.mdc');
        $this->assertStringStartsWith("---\ndescription: ", $cursor);
        $this->assertStringContainsString("alwaysApply: true\n---\n", $cursor);

        $agents = (string) file_get_contents($root.'/AGENTS.md');
        $this->assertStringContainsString('`php artisan test`', $agents);
        $this->assertStringContainsString('`vendor/bin/pint --test`', $agents);
        $this->assertStringContainsString('Never claim tests passed unless you actually ran them.', $agents);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $root = $this->useFixture();
        $before = $this->allFiles($root);

        $this->artisan('ai-blueprint:init', ['--dry-run' => true, '--no-interaction' => true])
            ->expectsOutputToContain('would create')
            ->assertSuccessful();

        $after = array_filter($this->allFiles($root), static fn (string $p): bool => ! str_starts_with($p, '.ai-blueprint/cache/'), ARRAY_FILTER_USE_KEY);
        $this->assertSame($before, $after);
    }

    public function test_rerun_preserves_manual_edits_and_existing_files(): void
    {
        $root = $this->useFixture();
        $this->putFile($root, 'CLAUDE.md', "# Our hand-written Claude instructions\n");

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->expectsOutputToContain('appended managed section')
            ->assertSuccessful();
        $claude = (string) file_get_contents($root.'/CLAUDE.md');
        $this->assertStringStartsWith("# Our hand-written Claude instructions\n\n<!-- ai-blueprint:start", $claude);
        $this->assertStringEndsWith("<!-- ai-blueprint:end -->\n", $claude);

        file_put_contents($root.'/AGENTS.md', "\nTeam note.\n", FILE_APPEND);
        $edited = file_get_contents($root.'/AGENTS.md');

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->expectsOutputToContain('edited manually')
            ->assertSuccessful();
        $this->assertSame($edited, file_get_contents($root.'/AGENTS.md'));
    }

    public function test_adapter_selection(): void
    {
        $root = $this->useFixture();

        $this->artisan('ai-blueprint:init', ['--adapter' => ['claude'], '--no-interaction' => true])->assertSuccessful();

        $this->assertFileExists($root.'/CLAUDE.md');
        $this->assertFileDoesNotExist($root.'/AGENTS.md');
        $this->assertFileDoesNotExist($root.'/.cursor/rules/ai-blueprint.mdc');

        $this->artisan('ai-blueprint:init', ['--adapter' => ['copilot'], '--no-interaction' => true])
            ->expectsOutputToContain('Unknown adapter(s): copilot')
            ->assertFailed();
    }

    public function test_disabled_adapters_and_documentation_in_config(): void
    {
        $root = $this->useFixture();
        $this->bindWorkspace($root, ['adapters' => ['claude' => false, 'cursor' => false], 'documentation' => ['enabled' => false]]);

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->assertFileExists($root.'/AGENTS.md');
        $this->assertFileDoesNotExist($root.'/CLAUDE.md');
        $this->assertDirectoryDoesNotExist($root.'/docs');
        $this->assertStringNotContainsString('docs/architecture', (string) file_get_contents($root.'/AGENTS.md'));
    }

    public function test_init_on_non_laravel_project(): void
    {
        $root = $this->useFixture('plain-php');

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->expectsOutputToContain('does not look like a Laravel application')
            ->assertSuccessful();

        $blueprint = $this->readJson($root.'/.ai-blueprint/blueprint.json');
        $this->assertSame('unknown', $blueprint['project']['framework']);
        $this->assertSame([], $blueprint['components']);
    }

    public function test_init_requires_composer_json(): void
    {
        $root = $this->temporaryDirectory();
        $this->bindWorkspace($root);

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->expectsOutputToContain('composer.json not found')
            ->assertFailed();
        $this->assertSame([], $this->allFiles($root));
    }
}

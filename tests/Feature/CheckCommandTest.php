<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Feature;

use AndrewProd\AiBlueprint\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class CheckCommandTest extends TestCase
{
    public function test_clean_project_passes(): void
    {
        $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->artisan('ai-blueprint:check', ['--strict' => true])
            ->expectsOutputToContain('No problems found')
            ->assertSuccessful();
    }

    public function test_json_output_lists_violations(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();
        file_put_contents($root.'/docs/engineering/testing.md', "edited\n");

        $exit = Artisan::call('ai-blueprint:check', ['--format' => 'json']);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exit, 'warnings do not fail by default');
        $this->assertTrue($report['passed']);
        $this->assertSame(['BP001', 'BP002', 'BP003', 'BP004', 'BP005', 'BP006', 'BP007', 'BP008', 'BP009'], $report['rules']);
        $this->assertContains(
            ['rule' => 'BP003', 'severity' => 'warning', 'message' => 'Managed file was edited manually; sync will not overwrite it.', 'path' => 'docs/engineering/testing.md', 'hint' => 'Keep your edits (no action), or run `php artisan ai-blueprint:sync --force` to regenerate it.'],
            $report['violations'],
        );

        $this->artisan('ai-blueprint:check', ['--strict' => true])->assertFailed();
    }

    public function test_corrupt_and_unsupported_blueprints(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        file_put_contents($root.'/.ai-blueprint/blueprint.json', '{"schema_version": "1.0", "components": "oops"');
        $this->artisan('ai-blueprint:check')->expectsOutputToContain('BP001')->assertFailed();

        file_put_contents($root.'/.ai-blueprint/blueprint.json', '{"schema_version": "9.0"}');
        $this->artisan('ai-blueprint:check')->expectsOutputToContain('BP008')->assertFailed();
    }

    public function test_task_sections_missing_references_and_fake_confirmations(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->putFile($root, 'docs/tasks/broken/task.md', "# Broken\n\n## Goal\n\nx\n");
        $this->putFile($root, '.ai-blueprint/decisions.json', json_encode(['schema_version' => '1.0', 'decisions' => [
            ['key' => 'queue_usage', 'status' => 'confirmed', 'answer' => ''],
        ]]) ?: '');
        $blueprint = $this->readJson($root.'/.ai-blueprint/blueprint.json');
        $blueprint['architecture']['confirmed_decisions'][] = ['key' => 'module_boundaries', 'question' => 'q', 'answer' => 'Made up', 'status' => 'confirmed', 'decided_at' => 'x'];
        file_put_contents($root.'/.ai-blueprint/blueprint.json', json_encode($blueprint));
        unlink($root.'/app/Models/Order.php');

        Artisan::call('ai-blueprint:check', ['--format' => 'json']);
        $report = json_decode(Artisan::output(), true);
        $byRule = [];
        foreach ($report['violations'] as $violation) {
            $byRule[$violation['rule']][] = $violation['message'];
        }

        $this->assertFalse($report['passed']);
        $this->assertContains('Task brief is missing the "## Acceptance criteria" section.', $byRule['BP004']);
        $this->assertContains('Decision [queue_usage] is marked confirmed but has no answer.', $byRule['BP005']);
        $this->assertContains('Blueprint lists [module_boundaries] as confirmed, but no confirmed decision is recorded.', $byRule['BP005']);
        $this->assertContains('Blueprint references a file that no longer exists; the blueprint is stale.', $byRule['BP009']);
        $this->assertContains('References missing file [app/Models/Order.php].', $byRule['BP002']);
    }

    public function test_broken_php_file_is_reported_not_hidden(): void
    {
        $root = $this->useFixture();
        $this->putFile($root, 'app/Services/Broken.php', "<?php\nclass Broken {\n  public function x( {\n}\n");

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->expectsOutputToContain('partially analyzed')
            ->assertSuccessful();

        $blueprint = $this->readJson($root.'/.ai-blueprint/blueprint.json');
        $this->assertFalse($blueprint['analysis']['complete']);

        $this->artisan('ai-blueprint:check')
            ->expectsOutputToContain('app/Services/Broken.php')
            ->assertSuccessful();
        $this->artisan('ai-blueprint:check', ['--strict' => true])->assertFailed();
    }

    public function test_changed_requires_git(): void
    {
        $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->artisan('ai-blueprint:check', ['--changed' => true])
            ->expectsOutputToContain('--changed requires a git repository')
            ->assertFailed();

        $this->artisan('ai-blueprint:check', ['--changed' => true, '--base' => '--upload-pack=evil'])
            ->expectsOutputToContain('Invalid base revision')
            ->assertFailed();
    }

    public function test_changed_limits_file_specific_violations(): void
    {
        if (! $this->gitAvailable()) {
            $this->markTestSkipped('git is not installed');
        }

        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();
        $this->git($root, 'init', '-q');
        $this->git($root, 'add', '-A');
        $this->git($root, '-c', 'user.email=t@example.com', '-c', 'user.name=t', 'commit', '-q', '-m', 'init');

        file_put_contents($root.'/docs/engineering/testing.md', "edited\n");
        file_put_contents($root.'/docs/engineering/security.md', "edited\n");
        $this->git($root, '-c', 'user.email=t@example.com', '-c', 'user.name=t', 'commit', '-q', '-am', 'edit');
        file_put_contents($root.'/docs/engineering/security.md', "edited again\n");

        Artisan::call('ai-blueprint:check', ['--format' => 'json', '--changed' => true]);
        $report = json_decode(Artisan::output(), true);
        $this->assertSame(['docs/engineering/security.md'], array_values(array_unique(array_column($report['violations'], 'path'))));

        Artisan::call('ai-blueprint:check', ['--format' => 'json', '--changed' => true, '--base' => 'HEAD~1']);
        $report = json_decode(Artisan::output(), true);
        $paths = array_values(array_unique(array_column($report['violations'], 'path')));
        sort($paths);
        $this->assertSame(['docs/engineering/security.md', 'docs/engineering/testing.md'], $paths);
    }

    private function gitAvailable(): bool
    {
        exec('git --version 2>/dev/null', $output, $code);

        return $code === 0;
    }

    private function git(string $root, string ...$args): void
    {
        $command = array_merge(['git', '-C', $root], $args);
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($process), (string) $error);
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Feature;

use AndrewProd\AiBlueprint\Tests\TestCase;

final class SecurityTest extends TestCase
{
    public function test_secrets_never_reach_generated_files_cache_or_output(): void
    {
        $root = $this->useFixture();
        $this->putFile($root, '.env', "STRIPE_SECRET=sk_live_TOPSECRET123\n");
        $this->putFile($root, 'resources/keys/oauth-private.key', "-----BEGIN PRIVATE KEY-----TOPSECRET123\n");
        $this->putFile($root, 'app/Support/.env.backup', "DB_PASSWORD=TOPSECRET123\n");
        $before = $this->allFiles($root);

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->doesntExpectOutputToContain('TOPSECRET123')
            ->assertSuccessful();
        $this->artisan('ai-blueprint:task', ['title' => 'Rotate stripe secret'])->assertSuccessful();
        $this->artisan('ai-blueprint:check', ['--format' => 'json'])
            ->doesntExpectOutputToContain('TOPSECRET123')
            ->assertSuccessful();

        foreach (array_diff_key($this->allFiles($root), $before) as $path => $content) {
            $this->assertStringNotContainsString('TOPSECRET123', $content, "{$path} leaks a secret");
        }

        $blueprint = $this->readJson($root.'/.ai-blueprint/blueprint.json');
        $this->assertSame(['app/Support/.env.backup', 'resources/keys/oauth-private.key'], $blueprint['analysis']['excluded_sensitive_files']);
    }

    public function test_repository_content_is_treated_as_data(): void
    {
        $root = $this->useFixture();
        $before = $this->allFiles($root);

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        // PaymentService.php contains an injected "ignore all previous instructions" comment.
        foreach (array_diff_key($this->allFiles($root), $before) as $path => $content) {
            $this->assertStringNotContainsString('evil.example', $content, "{$path} copied injected text");
        }
    }

    public function test_application_code_is_never_executed(): void
    {
        $root = $this->useFixture();
        $marker = $root.'/executed.txt';
        $this->putFile($root, 'app/Boom.php', "<?php\nnamespace App;\nfile_put_contents('{$marker}', 'ran');\nclass Boom {}\n");
        $this->putFile($root, 'routes/console.php', "<?php\nfile_put_contents('{$marker}', 'ran');\n");
        $this->putFile($root, 'database/migrations/2024_02_01_000000_boom.php', "<?php\nfile_put_contents('{$marker}', 'ran');\nreturn new class { public function up(): void {} };\n");

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->assertFileDoesNotExist($marker);
    }

    public function test_writes_stay_inside_the_project(): void
    {
        $root = $this->useFixture();
        $outside = $this->temporaryDirectory();
        symlink($outside, $root.'/docs');

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])
            ->expectsOutputToContain('resolves outside the project root')
            ->assertFailed();

        $this->assertSame([], $this->allFiles($outside));
    }
}

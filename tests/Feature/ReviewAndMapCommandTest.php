<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Feature;

use AndrewProd\AiBlueprint\Domain\Architecture\QuestionCatalog;
use AndrewProd\AiBlueprint\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class ReviewAndMapCommandTest extends TestCase
{
    public function test_non_interactive_review_records_decisions(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->artisan('ai-blueprint:review', ['--set' => ['transaction_ownership=Service layer opens transactions'], '--no-interaction' => true])
            ->expectsOutputToContain('1 decision(s) recorded')
            ->assertSuccessful();

        $decisions = $this->readJson($root.'/.ai-blueprint/decisions.json');
        $this->assertSame('Service layer opens transactions', $decisions['decisions'][0]['answer']);
        $this->assertSame(self::NOW, $decisions['decisions'][0]['decided_at']);

        $blueprint = $this->readJson($root.'/.ai-blueprint/blueprint.json');
        $this->assertSame('transaction_ownership', $blueprint['architecture']['confirmed_decisions'][0]['key']);
        $this->assertNotContains('transaction_ownership', array_column($blueprint['unknowns'], 'key'));

        $this->artisan('ai-blueprint:sync', ['--no-interaction' => true])->assertSuccessful();
        $this->assertStringContainsString('**transaction_ownership** — Service layer opens transactions', (string) file_get_contents($root.'/AGENTS.md'));

        $this->artisan('ai-blueprint:check')->assertSuccessful();

        $this->artisan('ai-blueprint:review', ['--forget' => ['transaction_ownership'], '--no-interaction' => true])->assertSuccessful();
        $this->assertSame([], $this->readJson($root.'/.ai-blueprint/decisions.json')['decisions']);
    }

    public function test_interactive_review(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:scan')->assertSuccessful();

        $command = $this->artisan('ai-blueprint:review');
        foreach (QuestionCatalog::all() as $key => $question) {
            $command->expectsQuestion($question.' (leave empty to skip)', $key === 'queue_usage' ? 'Mail and webhooks run on Redis queues' : '');
        }
        $command->assertSuccessful()->run();

        $decisions = $this->readJson($root.'/.ai-blueprint/decisions.json')['decisions'];
        $this->assertSame(['queue_usage'], array_column($decisions, 'key'));
    }

    public function test_review_rejects_unknown_keys(): void
    {
        $this->useFixture();

        $this->artisan('ai-blueprint:review', ['--set' => ['made_up=yes'], '--no-interaction' => true])
            ->expectsOutputToContain('Unknown decision key [made_up]')
            ->assertFailed();
    }

    public function test_map_outputs_table_and_json(): void
    {
        $this->useFixture();
        $this->artisan('ai-blueprint:scan')->assertSuccessful();

        $this->artisan('ai-blueprint:map', ['--type' => 'model'])
            ->expectsOutputToContain('app/Models/Customer.php')
            ->assertSuccessful();

        Artisan::call('ai-blueprint:map', ['--format' => 'json', '--type' => 'table']);
        $map = json_decode(Artisan::output(), true);
        $this->assertSame(['table.customers', 'table.orders'], array_column($map['components'], 'id'));
        $customers = $map['components'][0]['attributes']['columns'];
        $this->assertSame(['id', 'email', 'created_at', 'updated_at', 'full_name'], array_column($customers, 'name'));

        $this->artisan('ai-blueprint:map', ['--format' => 'xml'])->assertFailed();
    }

    public function test_scan_reports_changes_and_does_not_touch_docs(): void
    {
        $root = $this->useFixture();
        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();
        $docs = (string) file_get_contents($root.'/docs/architecture/modules.md');

        $this->putFile($root, 'app/Jobs/SendInvoice.php', "<?php\nnamespace App\\Jobs;\nclass SendInvoice implements \\Illuminate\\Contracts\\Queue\\ShouldQueue {}\n");

        $this->artisan('ai-blueprint:scan', ['-v' => true])
            ->expectsOutputToContain('+ job.send_invoice')
            ->assertSuccessful();
        $this->assertSame($docs, file_get_contents($root.'/docs/architecture/modules.md'));

        $this->artisan('ai-blueprint:check')->assertSuccessful();
    }

    public function test_doctor(): void
    {
        $this->useFixture();

        $this->artisan('ai-blueprint:doctor')
            ->expectsOutputToContain('not found. Run `php artisan ai-blueprint:init`')
            ->assertFailed();

        $this->artisan('ai-blueprint:init', ['--no-interaction' => true])->assertSuccessful();

        $this->artisan('ai-blueprint:doctor')
            ->expectsOutputToContain('No blocking problems found.')
            ->assertSuccessful();
    }
}

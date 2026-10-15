<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Application\BuildBlueprint;
use AndrewProd\AiBlueprint\Domain\Architecture\ArchitectureDecision;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Evidence;
use AndrewProd\AiBlueprint\Domain\Components\Relationship;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use PHPUnit\Framework\TestCase;

final class BuildBlueprintTest extends TestCase
{
    public function test_normalization(): void
    {
        $report = new AnalyzerReport('test', AnalyzerReport::COMPLETE);
        $a = new Component('model.user', 'model', 'User', 'app/Models/User.php', Status::Observed, [new Evidence('app/Models/User.php', 5)], ['class' => 'App\Models\User']);
        $sameAgain = new Component('model.user', 'model', 'User', 'app/Models/User.php', Status::Observed, [new Evidence('app/Models/User.php', 9)], ['class' => 'App\Models\User']);
        $conflicting = new Component('model.user', 'model', 'User', 'modules/User.php', Status::Observed, [new Evidence('modules/User.php')]);
        $table = new Component('table.users', 'table', 'users', 'database/migrations/x.php', Status::Observed);

        $blueprint = (new BuildBlueprint)->build('demo', true, [
            new AnalysisResult($report, [$a, $table], [
                new Relationship('route.get:x', 'class:App\Missing', 'handled_by', Status::Observed, [new Evidence('routes/web.php', 3)]),
            ]),
            new AnalysisResult($report, [$sameAgain, $conflicting], [
                new Relationship('model.user', 'class:App\Models\User', 'imports', Status::Observed),
                new Relationship('model.user', 'class:Illuminate\Support\Str', 'imports', Status::Observed),
            ]),
        ], [new ArchitectureDecision('queue_usage', 'q', 'Everything slow goes to Redis queues', 'now')], [], '2026-01-01T00:00:00Z');

        $ids = array_map(static fn (Component $c): string => $c->id, $blueprint->components);
        $this->assertSame(['model.user', 'model.user~2', 'table.users'], $ids);

        $merged = $blueprint->component('model.user');
        $this->assertNotNull($merged);
        $this->assertCount(2, $merged->evidence, 'evidence from identical facts is merged');

        $this->assertSame(['DUPLICATE_COMPONENT', 'ROUTE_HANDLER_NOT_FOUND'], array_map(static fn ($f) => $f->code, $blueprint->findings));

        // Self links and vendor classes are dropped; only convention-based model->table links remain.
        $this->assertSame(
            ['model.user|uses_table|table.users', 'model.user~2|uses_table|table.users'],
            array_map(static fn (Relationship $r): string => $r->key(), $blueprint->relationships),
        );
        $this->assertSame(Status::Inferred, $blueprint->relationships[0]->status);

        $this->assertNotContains('queue_usage', $blueprint->unknowns);
        $this->assertContains('transaction_ownership', $blueprint->unknowns);
    }
}

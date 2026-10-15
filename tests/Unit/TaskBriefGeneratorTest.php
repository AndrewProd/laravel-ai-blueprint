<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Application\ScanProject;
use AndrewProd\AiBlueprint\Application\Workflows\BuiltInWorkflows;
use AndrewProd\AiBlueprint\Domain\Tasks\TaskBrief;
use AndrewProd\AiBlueprint\Domain\Tasks\TaskDefinition;
use AndrewProd\AiBlueprint\Domain\Tasks\TaskType;
use AndrewProd\AiBlueprint\Generators\TaskBrief\TaskBriefGenerator;
use AndrewProd\AiBlueprint\Tests\TestCase;

final class TaskBriefGeneratorTest extends TestCase
{
    public function test_brief_has_required_sections_and_relevant_references(): void
    {
        $root = $this->useFixture();
        $workspace = $this->workspace($root);
        $blueprint = (new ScanProject($workspace))->run(false);

        $brief = (new TaskBriefGenerator)->build(
            new TaskDefinition('Add customer notifications', 'add-customer-notifications', TaskType::Feature),
            (new BuiltInWorkflows)->get('feature'),
            $blueprint,
            $workspace->artifactContext(),
            'claude',
        );

        $this->assertSame(['task.md', 'context.md', 'checklist.md'], array_keys($brief->files));
        foreach (TaskBrief::REQUIRED_SECTIONS as $section) {
            $this->assertStringContainsString("## {$section}", $brief->files['task.md']);
        }

        $context = $brief->files['context.md'];
        $this->assertStringContainsString('`app/Models/Customer.php`', $context);
        $this->assertStringContainsString('`app/Http/Controllers/CustomerController.php`', $context);
        $this->assertStringContainsString('`tests/Feature/CustomerTest.php`', $context);
        $this->assertStringContainsString('**module_boundaries**: not confirmed', $context);
        $this->assertStringNotContainsString('return Customer::all()', $context, 'source code is referenced, not copied');
        $this->assertStringContainsString('CLAUDE.md', $brief->files['task.md']);
    }

    public function test_every_task_type_has_a_workflow(): void
    {
        $workflows = new BuiltInWorkflows;
        foreach (TaskType::cases() as $type) {
            $this->assertSame($type->value, $workflows->get($type->value)->name);
        }
    }

    public function test_keywords_ignore_stopwords_and_plurals(): void
    {
        $this->assertSame(['customer', 'notification'], (new TaskBriefGenerator)->keywords('Add the Customer notifications'));
    }
}

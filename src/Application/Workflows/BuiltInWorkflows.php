<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Application\Workflows;

use AndrewProd\AiBlueprint\Contracts\WorkflowProvider;
use AndrewProd\AiBlueprint\Domain\Workflows\WorkflowDefinition;
use InvalidArgumentException;

final class BuiltInWorkflows implements WorkflowProvider
{
    /** @var array<string, WorkflowDefinition>|null */
    private ?array $workflows = null;

    public function workflows(): iterable
    {
        return array_values($this->load());
    }

    public function get(string $name): WorkflowDefinition
    {
        return $this->load()[$name] ?? throw new InvalidArgumentException(
            "Unknown workflow [{$name}]. Available: ".implode(', ', array_keys($this->load())).'.'
        );
    }

    /**
     * @return array<string, WorkflowDefinition>
     */
    private function load(): array
    {
        if ($this->workflows !== null) {
            return $this->workflows;
        }

        $common = [
            'Testing' => [
                'New or changed behavior is covered by automated tests.',
                'The relevant test suites were run and the real results are reported.',
            ],
            'Security' => [
                'No secrets, tokens or personal data in code, logs, tests or fixtures.',
                'Input is validated and actions are authorized.',
            ],
            'Documentation' => [
                'Specs/docs updated if behavior or contracts changed.',
                '`php artisan ai-blueprint:scan` and `ai-blueprint:check` run after structural changes.',
            ],
        ];

        $list = [
            new WorkflowDefinition(
                'feature',
                'Feature',
                'New functionality built on existing project conventions.',
                ['Clarify the expected behavior and acceptance criteria.', 'Find similar existing features and follow their structure.', 'Implement in small steps with tests.', 'Verify and report.'],
                ['The feature behaves as described in the goal.', 'Existing behavior is unchanged unless stated otherwise.', 'Automated tests cover the main path and important edge cases.'],
                ['Follow existing patterns in the affected area.', 'No public contract changes without agreement.'],
                ['Implementation' => ['Reused existing services/components where possible.', 'No unrelated refactoring.']] + $common + ['Backward compatibility' => ['Existing routes, responses and events keep working.']],
                ['model', 'controller', 'service', 'action', 'route', 'table', 'job', 'event', 'listener', 'form_request', 'api_resource', 'frontend_area'],
                ['module_boundaries', 'business_logic_location', 'testing_expectations'],
            ),
            new WorkflowDefinition(
                'bugfix',
                'Bug fix',
                'Fix a defect with a regression test and minimal side effects.',
                ['Reproduce the bug and describe the actual vs. expected behavior.', 'Write a failing test that captures the bug.', 'Fix the root cause, not the symptom.', 'Run the related tests and report.'],
                ['The described defect no longer occurs.', 'A regression test fails before the fix and passes after it.', 'No unrelated behavior changes.'],
                ['Keep the fix minimal.', 'Do not change public contracts to work around the bug.'],
                ['Diagnosis' => ['Root cause identified and described in the report.']] + $common + ['Backward compatibility' => ['Data written before the fix is still handled correctly.']],
                ['model', 'controller', 'service', 'action', 'route', 'table', 'job', 'listener'],
                ['business_logic_location', 'testing_expectations'],
            ),
            new WorkflowDefinition(
                'refactor',
                'Refactoring',
                'Improve structure while preserving observable behavior.',
                ['Identify the behavior that must be preserved and the tests that protect it.', 'Add characterization tests where coverage is missing.', 'Refactor in small, verifiable steps.', 'Confirm the test suite is green and report.'],
                ['Observable behavior is unchanged.', 'Existing tests pass without modification of their assertions.', 'The structural goal stated in the task is achieved.'],
                ['No behavior changes.', 'No public contract changes.', 'Do not mix refactoring with feature work.'],
                ['Safety net' => ['Behavior is covered by tests before refactoring.']] + $common,
                ['service', 'action', 'repository', 'model', 'controller', 'interface'],
                ['module_boundaries', 'business_logic_location'],
            ),
            new WorkflowDefinition(
                'api-endpoint',
                'API endpoint',
                'Add or change an HTTP API endpoint with validation, authorization and a stable contract.',
                ['Define the request, response and error contract.', 'Follow existing routes, controllers, form requests and resources.', 'Implement validation and authorization.', 'Add feature tests for success, validation and authorization failures.'],
                ['The endpoint follows the agreed request/response contract.', 'Invalid input returns validation errors in the project\'s standard format.', 'Unauthorized requests are rejected.', 'Feature tests cover success and failure cases.'],
                ['Follow existing API versioning and response conventions.', 'Do not change existing endpoints\' contracts without agreement.'],
                ['Contract' => ['Request and response documented in the task or a spec.', 'Status codes match existing endpoints.']] + $common,
                ['route', 'controller', 'form_request', 'api_resource', 'policy', 'middleware', 'model', 'table'],
                ['api_versioning', 'authorization_strategy', 'business_logic_location'],
            ),
            new WorkflowDefinition(
                'integration',
                'External integration',
                'Integrate an external API or webhook safely and idempotently.',
                ['Document the external API, authentication and failure modes.', 'Isolate the client behind an existing or new integration boundary.', 'Handle timeouts, retries and idempotency.', 'Test with fakes; never call the real service from tests.'],
                ['The integration works against the documented API contract.', 'Timeouts and failures are handled without data corruption.', 'Webhooks verify signatures and are idempotent.', 'Tests use HTTP fakes or mocks only.'],
                ['Credentials come from configuration, never from code.', 'No real external calls in tests.', 'Long-running or retryable work runs on queues if that is the project convention.'],
                ['Resilience' => ['Timeouts configured.', 'Retry and idempotency rules applied.', 'Failures are logged without secrets.']] + $common,
                ['integration', 'service', 'job', 'listener', 'route', 'controller', 'event'],
                ['external_api_retry_policy', 'queue_usage', 'transaction_ownership'],
            ),
        ];

        $this->workflows = [];
        foreach ($list as $workflow) {
            $this->workflows[$workflow->name] = $workflow;
        }

        return $this->workflows;
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Application\ScanProject;
use AndrewProd\AiBlueprint\Domain\Blueprint\Blueprint;
use AndrewProd\AiBlueprint\Domain\Blueprint\BlueprintValidator;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\BlueprintStore;
use AndrewProd\AiBlueprint\Tests\TestCase;
use JsonSchema\Validator;

final class BlueprintSchemaTest extends TestCase
{
    public function test_scanned_blueprint_matches_json_schema_and_round_trips(): void
    {
        $root = $this->useFixture();
        $blueprint = (new ScanProject($this->workspace($root)))->run(false);
        $json = BlueprintStore::encode($blueprint->toArray());

        $this->assertSchemaValid($json);

        $data = json_decode($json, true);
        (new BlueprintValidator)->assertValid($data);
        $this->assertSame($json, BlueprintStore::encode(Blueprint::fromArray($data)->toArray()));
    }

    public function test_scans_are_deterministic(): void
    {
        $root = $this->useFixture();
        $first = (new ScanProject($this->workspace($root)))->run(false)->toArray();
        $second = (new ScanProject($this->workspace($root)))->run(true)->toArray();
        $third = (new ScanProject($this->workspace($root)))->run(true)->toArray(); // served from cache

        $this->assertSame(BlueprintStore::encode($first), BlueprintStore::encode($second));
        $this->assertSame(BlueprintStore::encode($first), BlueprintStore::encode($third));
    }

    public function test_validator_rejects_corrupt_data(): void
    {
        $validator = new BlueprintValidator;

        $this->assertNotEmpty($validator->errors('nope'));
        $this->assertNotEmpty($validator->errors(['schema_version' => '1.0']));
        $errors = $validator->errors([
            'schema_version' => '1.0',
            'generated_at' => 'x',
            'project' => ['name' => 'a', 'framework' => 'laravel'],
            'components' => [
                ['id' => 'model.a', 'type' => 'model', 'name' => 'A', 'status' => 'guessed'],
                ['id' => 'model.a', 'type' => 'model', 'name' => 'A', 'status' => 'observed'],
            ],
            'architecture' => ['confirmed_decisions' => [['key' => 'x', 'status' => 'inferred']]],
        ]);

        $this->assertContains('Component #0 has an invalid [status].', $errors);
        $this->assertContains('Duplicate component id [model.a].', $errors);
        $this->assertContains('Confirmed decision #0 must have a [key] and status [confirmed].', $errors);
    }

    public function test_unsupported_schema_version_is_bp008(): void
    {
        try {
            (new BlueprintValidator)->assertValid(['schema_version' => '2.0']);
            $this->fail('Expected InvalidBlueprint');
        } catch (InvalidBlueprint $e) {
            $this->assertSame('BP008', $e->ruleId);
        }
    }

    private function assertSchemaValid(string $json): void
    {
        $schema = json_decode((string) file_get_contents(__DIR__.'/../../resources/schemas/blueprint.schema.json'));
        $data = json_decode($json);
        $validator = new Validator;
        $validator->validate($data, $schema);

        $this->assertTrue($validator->isValid(), json_encode($validator->getErrors(), JSON_PRETTY_PRINT) ?: '');
    }
}

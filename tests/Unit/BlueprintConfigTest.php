<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Application\BlueprintConfig;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BlueprintConfigTest extends TestCase
{
    public function test_defaults_are_safe(): void
    {
        $config = BlueprintConfig::fromArray([]);

        $this->assertSame('.ai-blueprint', $config->outputDirectory);
        $this->assertContains('vendor', $config->exclude);
        $this->assertContains('.git', $config->exclude);
        $this->assertContains('.env', $config->sensitive);
        $this->assertSame(['generic', 'codex', 'claude', 'cursor'], $config->enabledAdapters());
    }

    public function test_user_config_cannot_remove_built_in_protections(): void
    {
        $config = BlueprintConfig::fromArray(['paths' => ['exclude' => []], 'sensitive' => ['*.secret']]);

        $this->assertContains('vendor', $config->exclude);
        $this->assertContains('node_modules', $config->exclude);
        $this->assertContains('.env', $config->sensitive);
        $this->assertContains('*.secret', $config->sensitive);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    #[DataProvider('invalid')]
    public function test_invalid_values_are_rejected(array $config, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        BlueprintConfig::fromArray($config);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): array
    {
        return [
            'absolute output' => [['output_directory' => '/tmp/x'], 'output_directory'],
            'escaping output' => [['output_directory' => '../x'], 'output_directory'],
            'unknown adapter' => [['adapters' => ['copilot' => true]], 'unknown entry [copilot]'],
            'include not list' => [['paths' => ['include' => 'app']], 'paths.include'],
            'escaping docs' => [['documentation' => ['directory' => '../docs']], 'documentation.directory'],
        ];
    }
}

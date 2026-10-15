<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests;

use AndrewProd\AiBlueprint\AiBlueprintServiceProvider;
use AndrewProd\AiBlueprint\Application\BlueprintConfig;
use AndrewProd\AiBlueprint\Application\Workspace;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const NOW = '2026-01-01T00:00:00Z';

    /** @var list<string> */
    private array $temporary = [];

    protected function getPackageProviders($app): array
    {
        return [AiBlueprintServiceProvider::class];
    }

    protected function tearDown(): void
    {
        $filesystem = new Filesystem;
        foreach ($this->temporary as $directory) {
            $filesystem->deleteDirectory($directory);
        }
        parent::tearDown();
    }

    /**
     * Copy a fixture into a fresh temporary directory and point the package at it.
     */
    protected function useFixture(string $fixture = 'laravel-app'): string
    {
        $root = $this->temporaryDirectory();
        (new Filesystem)->copyDirectory(__DIR__.'/Fixtures/'.$fixture, $root);
        $this->bindWorkspace($root);

        return $root;
    }

    /**
     * @param  array<string, mixed>  $config  overrides merged into the package config
     */
    protected function bindWorkspace(string $root, array $config = []): void
    {
        $this->app->bind(Workspace::class, fn () => $this->workspace($root, $config));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function workspace(string $root, array $config = []): Workspace
    {
        $base = require __DIR__.'/../config/ai-blueprint.php';

        return new Workspace($root, BlueprintConfig::fromArray(array_replace_recursive($base, $config)), static fn (): string => self::NOW);
    }

    protected function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/ai-blueprint-'.bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        $directory = (string) realpath($directory);
        $this->temporary[] = $directory;

        return $directory;
    }

    protected function putFile(string $root, string $relative, string $content): void
    {
        $path = $root.'/'.$relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
    }

    /**
     * @return array<string, mixed>
     */
    protected function readJson(string $path): array
    {
        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Every file under $root, relative, excluding the fixture's own sources.
     *
     * @return array<string, string> path => contents
     */
    protected function allFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[substr($file->getPathname(), strlen($root) + 1)] = (string) file_get_contents($file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint;

use AndrewProd\AiBlueprint\Application\BlueprintConfig;
use AndrewProd\AiBlueprint\Application\Workspace;
use AndrewProd\AiBlueprint\Console\Commands\CheckCommand;
use AndrewProd\AiBlueprint\Console\Commands\DoctorCommand;
use AndrewProd\AiBlueprint\Console\Commands\InitCommand;
use AndrewProd\AiBlueprint\Console\Commands\MapCommand;
use AndrewProd\AiBlueprint\Console\Commands\ReviewCommand;
use AndrewProd\AiBlueprint\Console\Commands\ScanCommand;
use AndrewProd\AiBlueprint\Console\Commands\SyncCommand;
use AndrewProd\AiBlueprint\Console\Commands\TaskCommand;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

final class AiBlueprintServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-blueprint.php', 'ai-blueprint');

        $this->app->bind(Workspace::class, function ($app): Workspace {
            return new Workspace(
                $app->basePath(),
                BlueprintConfig::fromArray((array) $app['config']->get('ai-blueprint', [])),
                static fn (): string => Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z'),
            );
        });
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/ai-blueprint.php' => $this->app->configPath('ai-blueprint.php'),
        ], 'ai-blueprint-config');

        $this->commands([
            InitCommand::class,
            ScanCommand::class,
            MapCommand::class,
            ReviewCommand::class,
            TaskCommand::class,
            CheckCommand::class,
            SyncCommand::class,
            DoctorCommand::class,
        ]);
    }
}

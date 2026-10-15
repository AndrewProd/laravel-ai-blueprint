<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Composer;

use AndrewProd\AiBlueprint\Analyzers\AnalysisResult;
use AndrewProd\AiBlueprint\Analyzers\ProjectContext;
use AndrewProd\AiBlueprint\Contracts\Analyzer;
use AndrewProd\AiBlueprint\Domain\Components\Component;
use AndrewProd\AiBlueprint\Domain\Components\Evidence;
use AndrewProd\AiBlueprint\Domain\Components\Status;
use AndrewProd\AiBlueprint\Domain\Findings\AnalyzerReport;
use AndrewProd\AiBlueprint\Domain\Findings\Finding;
use AndrewProd\AiBlueprint\Domain\Findings\Severity;

/**
 * Reads composer.json / composer.lock for framework, PHP and tooling facts.
 */
final class ComposerAnalyzer implements Analyzer
{
    /** @var array<string, string> package => tool name */
    private const TOOLS = [
        'phpunit/phpunit' => 'phpunit',
        'pestphp/pest' => 'pest',
        'phpstan/phpstan' => 'phpstan',
        'larastan/larastan' => 'larastan',
        'nunomaduro/larastan' => 'larastan',
        'vimeo/psalm' => 'psalm',
        'laravel/pint' => 'pint',
        'friendsofphp/php-cs-fixer' => 'php-cs-fixer',
        'squizlabs/php_codesniffer' => 'phpcs',
        'rector/rector' => 'rector',
        'laravel/dusk' => 'dusk',
        'mockery/mockery' => 'mockery',
    ];

    /** Packages worth mentioning to an agent. */
    private const NOTABLE = [
        'laravel/sanctum', 'laravel/passport', 'laravel/fortify', 'laravel/jetstream', 'laravel/breeze',
        'laravel/horizon', 'laravel/octane', 'laravel/telescope', 'laravel/pulse', 'laravel/reverb',
        'laravel/cashier', 'laravel/scout', 'laravel/socialite', 'laravel/pennant', 'laravel/folio',
        'livewire/livewire', 'livewire/volt', 'inertiajs/inertia-laravel', 'filament/filament',
        'spatie/laravel-permission', 'spatie/laravel-data', 'spatie/laravel-query-builder',
        'nwidart/laravel-modules', 'lorisleiva/laravel-actions', 'predis/predis', 'guzzlehttp/guzzle',
    ];

    /** SDKs that usually mean an external integration. */
    private const INTEGRATIONS = [
        'stripe/stripe-php' => 'Stripe',
        'laravel/cashier' => 'Stripe/Paddle billing (Cashier)',
        'paypal/paypal-checkout-sdk' => 'PayPal',
        'aws/aws-sdk-php' => 'AWS',
        'google/apiclient' => 'Google APIs',
        'twilio/sdk' => 'Twilio',
        'pusher/pusher-php-server' => 'Pusher',
        'sentry/sentry-laravel' => 'Sentry',
        'mailgun/mailgun-php' => 'Mailgun',
        'symfony/mailgun-mailer' => 'Mailgun',
        'symfony/postmark-mailer' => 'Postmark',
        'resend/resend-laravel' => 'Resend',
        'openai-php/laravel' => 'OpenAI',
        'anthropic-ai/sdk' => 'Anthropic',
        'saloonphp/saloon' => 'HTTP integrations (Saloon)',
        'algolia/algoliasearch-client-php' => 'Algolia',
        'meilisearch/meilisearch-php' => 'Meilisearch',
        'elasticsearch/elasticsearch' => 'Elasticsearch',
        'league/flysystem-aws-s3-v3' => 'Amazon S3',
    ];

    public function name(): string
    {
        return 'composer';
    }

    public function supports(ProjectContext $project): bool
    {
        return $project->files->exists('composer.json');
    }

    public function analyze(ProjectContext $project): AnalysisResult
    {
        $composer = $project->files->readJson('composer.json');
        if ($composer === null) {
            return new AnalysisResult(
                new AnalyzerReport($this->name(), AnalyzerReport::FAILED, ['composer.json is not valid JSON.']),
                findings: [new Finding('BP006', Severity::Error, 'composer.json could not be decoded.', 'composer.json', $this->name())],
            );
        }

        $require = array_merge((array) ($composer['require'] ?? []), (array) ($composer['require-dev'] ?? []));
        $lock = $project->files->readJson('composer.lock');
        $locked = [];
        foreach (array_merge((array) ($lock['packages'] ?? []), (array) ($lock['packages-dev'] ?? [])) as $package) {
            if (is_array($package) && isset($package['name'], $package['version'])) {
                $locked[(string) $package['name']] = ltrim((string) $package['version'], 'v');
            }
        }

        $tools = [];
        foreach (self::TOOLS as $package => $tool) {
            if (isset($require[$package]) || isset($locked[$package])) {
                $tools[] = $tool;
            }
        }

        $packages = [];
        foreach (self::NOTABLE as $package) {
            if (isset($require[$package])) {
                $packages[$package] = (string) ($locked[$package] ?? $require[$package]);
            }
        }

        $components = [];
        foreach (self::INTEGRATIONS as $package => $label) {
            if (isset($require[$package])) {
                $components[] = new Component(
                    'integration.'.str_replace('/', '.', $package),
                    'integration',
                    $label,
                    'composer.json',
                    // The SDK is observed; that the app actively integrates with the service is an inference.
                    Status::Inferred,
                    [new Evidence('composer.json', null, $package)],
                    ['package' => $package, 'version' => $locked[$package] ?? (string) $require[$package]],
                );
            }
        }

        $frameworkVersion = $locked['laravel/framework'] ?? (isset($require['laravel/framework']) ? (string) $require['laravel/framework'] : null);

        $notes = [];
        if ($lock === null) {
            $notes[] = 'composer.lock not found; versions are constraints, not installed versions.';
        }

        return new AnalysisResult(
            new AnalyzerReport($this->name(), AnalyzerReport::COMPLETE, $notes),
            $components,
            facts: array_filter([
                'name' => isset($composer['name']) ? (string) $composer['name'] : null,
                'framework' => isset($require['laravel/framework']) ? 'laravel' : null,
                'framework_version' => $frameworkVersion,
                'php' => isset($composer['require']['php']) ? (string) $composer['require']['php'] : null,
                'tools' => $tools,
                'packages' => $packages,
                'livewire' => isset($require['livewire/livewire']),
                'inertia' => isset($require['inertiajs/inertia-laravel']),
            ], static fn (mixed $v): bool => $v !== null),
        );
    }
}

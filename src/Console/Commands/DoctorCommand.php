<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Console\Commands;

use AndrewProd\AiBlueprint\Application\Workspace;
use AndrewProd\AiBlueprint\Domain\Blueprint\InvalidBlueprint;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\ManagedSection;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\Manifest;
use AndrewProd\AiBlueprint\Support\Version;

/**
 * Read-only diagnostics. Never installs software or runs project scripts.
 */
final class DoctorCommand extends BlueprintCommand
{
    protected $signature = 'ai-blueprint:doctor';

    protected $description = 'Diagnose the Laravel AI Blueprint installation and state';

    private int $failures = 0;

    protected function perform(Workspace $workspace): int
    {
        $this->failures = 0;
        $this->components->info('Laravel AI Blueprint '.Version::PACKAGE.' (schema '.Version::SCHEMA.')');

        $this->report('PHP version', PHP_VERSION, true);
        $this->report('Tokenizer extension', extension_loaded('tokenizer') ? 'loaded' : 'missing', extension_loaded('tokenizer'));
        $this->report('composer.json', $workspace->writer->exists('composer.json') ? 'found' : 'missing', $workspace->writer->exists('composer.json'));
        $this->report('Output directory writable', $workspace->config->outputDirectory, $workspace->writer->isWritable($workspace->config->outputDirectory.'/blueprint.json'));

        foreach ($workspace->config->include as $directory) {
            $exists = is_dir($workspace->root.'/'.$directory);
            $this->report("Scan path {$directory}/", $exists ? 'present' : 'not present (skipped)', true, ! $exists);
        }

        try {
            $blueprint = $workspace->store->loadBlueprint();
            $this->report('blueprint.json', 'valid, generated '.$blueprint->generatedAt, true);
            $this->report('Analysis complete', $blueprint->isComplete() ? 'yes' : 'partial — see ai-blueprint:check', true, ! $blueprint->isComplete());
        } catch (InvalidBlueprint $e) {
            $this->report('blueprint.json', "[{$e->ruleId}] ".$e->getMessage(), false);
        }

        $decisions = $workspace->store->rawDecisions();
        $decisionsExists = $workspace->writer->exists($workspace->store->decisionsPath());
        $this->report('decisions.json', match (true) {
            ! $decisionsExists => 'none yet (run ai-blueprint:review)',
            $decisions === null => 'invalid JSON',
            default => count((array) ($decisions['decisions'] ?? [])).' decision(s)',
        }, ! $decisionsExists || $decisions !== null, ! $decisionsExists);

        if (! $workspace->writer->exists($workspace->store->manifestPath())) {
            $this->report('manifest.json', 'not found (run ai-blueprint:init)', true, true);
        } elseif (! $workspace->store->hasValidManifest()) {
            $this->report('manifest.json', 'invalid JSON', false);
        } else {
            $manifest = $workspace->store->loadManifest();
            $missing = 0;
            $edited = 0;
            foreach ($manifest->all() as $path => $entry) {
                $content = $workspace->writer->read($path);
                if ($content === null) {
                    $missing++;
                } elseif (($tracked = ManagedSection::trackedContent($content, $entry)) === null || Manifest::hash($tracked) !== $entry['hash']) {
                    $edited++;
                }
            }
            $this->report('Managed files', count($manifest->all())." tracked, {$missing} missing, {$edited} edited manually", true, $missing + $edited > 0);
        }

        $this->report('Enabled adapters', implode(', ', $workspace->config->enabledAdapters()) ?: 'none', true);

        foreach (['phpunit' => 'vendor/bin/phpunit', 'pest' => 'vendor/bin/pest', 'phpstan' => 'vendor/bin/phpstan', 'pint' => 'vendor/bin/pint'] as $tool => $binary) {
            $found = is_file($workspace->root.'/'.$binary);
            $this->report("Tool: {$tool}", $found ? $binary : 'not installed', true, ! $found);
        }
        $this->report('Git repository', is_dir($workspace->root.'/.git') ? 'detected' : 'not detected (--changed unavailable)', true, ! is_dir($workspace->root.'/.git'));

        $this->newLine();
        if ($this->failures > 0) {
            $this->components->error("{$this->failures} problem(s) found.");

            return self::FAILURE;
        }
        $this->components->info('No blocking problems found.');

        return self::SUCCESS;
    }

    private function report(string $label, string $value, bool $ok, bool $warn = false): void
    {
        if (! $ok) {
            $this->failures++;
        }
        $color = ! $ok ? 'red' : ($warn ? 'yellow' : 'green');
        $this->components->twoColumnDetail($label, "<fg={$color}>{$value}</>");
    }
}

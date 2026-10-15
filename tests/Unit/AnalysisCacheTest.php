<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Infrastructure\Cache\AnalysisCache;
use AndrewProd\AiBlueprint\Tests\TestCase;

final class AnalysisCacheTest extends TestCase
{
    public function test_entries_are_keyed_by_content_hash_and_version(): void
    {
        $file = $this->temporaryDirectory().'/cache/analysis.json';
        $calls = 0;
        $compute = static function () use (&$calls): array {
            $calls++;

            return ['n' => $calls];
        };

        $cache = new AnalysisCache($file);
        $cache->remember('php', '1', 'a.php', 'v1', $compute);
        $cache->persist();

        $cache = new AnalysisCache($file);
        $this->assertSame(['n' => 1], $cache->remember('php', '1', 'a.php', 'v1', $compute));
        $this->assertSame(['n' => 2], $cache->remember('php', '1', 'a.php', 'v2', $compute), 'content change invalidates');
        $this->assertSame(['n' => 3], $cache->remember('php', '2', 'a.php', 'v2', $compute), 'analyzer version change invalidates');
        $cache->persist();

        $this->assertStringNotContainsString('v2', (string) file_get_contents($file), 'file contents are never cached');
    }

    public function test_entries_for_files_no_longer_seen_are_pruned(): void
    {
        $file = $this->temporaryDirectory().'/analysis.json';
        $cache = new AnalysisCache($file);
        $cache->remember('php', '1', 'a.php', 'x', static fn (): int => 1);
        $cache->remember('php', '1', 'b.php', 'x', static fn (): int => 2);
        $cache->persist();

        $cache = new AnalysisCache($file);
        $cache->remember('php', '1', 'a.php', 'x', static fn (): int => 1);
        $cache->persist();

        $this->assertSame(['php:a.php'], array_keys(json_decode((string) file_get_contents($file), true)['entries']));
    }
}

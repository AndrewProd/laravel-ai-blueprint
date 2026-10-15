<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Support\LineDiff;
use PHPUnit\Framework\TestCase;

final class LineDiffTest extends TestCase
{
    public function test_unified_diff(): void
    {
        $diff = LineDiff::unified("a\nb\nc\n", "a\nB\nc\n", 'old', 'new');

        $this->assertSame("--- old\n+++ new\n a\n-b\n+B\n c\n \n", $diff);
        $this->assertSame('', LineDiff::unified("same\n", "same\n"));
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Support;

/**
 * Minimal unified-style line diff (LCS), good enough to show conflicts in a terminal.
 */
final class LineDiff
{
    private const MAX_CELLS = 4_000_000;

    public static function unified(string $old, string $new, string $oldLabel = 'current', string $newLabel = 'generated', int $context = 2): string
    {
        $a = explode("\n", str_replace("\r\n", "\n", $old));
        $b = explode("\n", str_replace("\r\n", "\n", $new));

        if (count($a) * count($b) > self::MAX_CELLS) {
            return "--- {$oldLabel}\n+++ {$newLabel}\n(files too large to diff: ".count($a).' vs '.count($b)." lines)\n";
        }

        $ops = self::ops($a, $b);
        $changed = array_keys(array_filter($ops, static fn (array $op): bool => $op[0] !== ' '));
        if ($changed === []) {
            return '';
        }

        $show = [];
        foreach ($changed as $index) {
            for ($i = max(0, $index - $context); $i <= min(count($ops) - 1, $index + $context); $i++) {
                $show[$i] = true;
            }
        }

        $out = ["--- {$oldLabel}", "+++ {$newLabel}"];
        $previous = -1;
        foreach ($ops as $i => [$sign, $line]) {
            if (! isset($show[$i])) {
                continue;
            }
            if ($previous !== -1 && $i !== $previous + 1) {
                $out[] = '@@';
            }
            $out[] = $sign.$line;
            $previous = $i;
        }

        return implode("\n", $out)."\n";
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<array{0: string, 1: string}>
     */
    private static function ops(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = [' ', $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $ops[] = ['-', $a[$i++]];
            } else {
                $ops[] = ['+', $b[$j++]];
            }
        }
        while ($i < $n) {
            $ops[] = ['-', $a[$i++]];
        }
        while ($j < $m) {
            $ops[] = ['+', $b[$j++]];
        }

        return $ops;
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Git;

use RuntimeException;

/**
 * Lists files changed relative to a base revision using fixed git commands.
 * Arguments are passed as an array (no shell); the base revision is validated.
 */
final class ChangedFiles
{
    public function __construct(private readonly string $root) {}

    /**
     * @return list<string> project-relative paths
     *
     * @throws RuntimeException with a user-facing message when git or the base is unavailable
     */
    public function since(string $base = 'HEAD'): array
    {
        if (preg_match('/^[A-Za-z0-9._\/~^@{}-]+$/', $base) !== 1 || str_starts_with($base, '-')) {
            throw new RuntimeException("Invalid base revision [{$base}].");
        }

        [$code] = $this->git(['rev-parse', '--is-inside-work-tree']);
        if ($code !== 0) {
            throw new RuntimeException('--changed requires a git repository, but git is not available or this is not a work tree.');
        }

        [$code] = $this->git(['rev-parse', '--verify', '--quiet', $base.'^{commit}']);
        if ($code !== 0) {
            throw new RuntimeException("--changed could not resolve base revision [{$base}]. Make a first commit or pass --base=<commit>.");
        }

        $files = [];
        foreach ([
            ['diff', '--name-only', '--no-renames', '--relative', $base, '--'],
            ['ls-files', '--others', '--exclude-standard'],
        ] as $args) {
            [$code, $output] = $this->git($args);
            if ($code !== 0) {
                throw new RuntimeException('git failed while listing changed files.');
            }
            foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
                if ($line !== '') {
                    $files[$line] = true;
                }
            }
        }

        $list = array_keys($files);
        sort($list);

        return $list;
    }

    /**
     * @param  list<string>  $args
     * @return array{0: int, 1: string}
     */
    private function git(array $args): array
    {
        $process = @proc_open(
            array_merge(['git', '-C', $this->root], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (! is_resource($process)) {
            return [127, ''];
        }

        $output = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}

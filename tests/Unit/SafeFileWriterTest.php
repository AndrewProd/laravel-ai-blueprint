<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Infrastructure\Filesystem\SafeFileWriter;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\UnsafePath;
use AndrewProd\AiBlueprint\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SafeFileWriterTest extends TestCase
{
    #[DataProvider('unsafePaths')]
    public function test_it_refuses_unsafe_paths(string $path): void
    {
        $root = $this->temporaryDirectory();
        $writer = new SafeFileWriter($root);

        $this->expectException(UnsafePath::class);
        $writer->write($path, 'x');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafePaths(): array
    {
        return [
            'traversal' => ['docs/../../evil.md'],
            'absolute' => ['/tmp/evil.md'],
            'windows absolute' => ['C:\\evil.md'],
            'git internals' => ['.git/hooks/pre-commit'],
            'vendor' => ['vendor/autoload.php'],
            'node_modules' => ['node_modules/x/index.js'],
            'root itself' => ['.'],
            'empty' => [''],
        ];
    }

    public function test_it_refuses_symlink_escapes(): void
    {
        $root = $this->temporaryDirectory();
        $outside = $this->temporaryDirectory();
        symlink($outside, $root.'/docs');
        touch($outside.'/target.md');
        symlink($outside.'/target.md', $root.'/AGENTS.md');

        $writer = new SafeFileWriter($root);

        try {
            $writer->write('docs/architecture/overview.md', 'x');
            $this->fail('Expected UnsafePath for symlinked directory');
        } catch (UnsafePath) {
        }

        try {
            $writer->write('AGENTS.md', 'x');
            $this->fail('Expected UnsafePath for symlinked file');
        } catch (UnsafePath) {
        }

        $this->assertSame([], array_values(array_diff(scandir($outside) ?: [], ['.', '..', 'target.md'])));
        $this->assertSame('', file_get_contents($outside.'/target.md'));
    }

    public function test_it_writes_atomically_inside_the_project(): void
    {
        $root = $this->temporaryDirectory();
        $writer = new SafeFileWriter($root);
        $writer->write('docs/a/b.md', 'hello');

        $this->assertSame('hello', file_get_contents($root.'/docs/a/b.md'));
        $this->assertSame(['b.md'], array_values(array_diff(scandir($root.'/docs/a') ?: [], ['.', '..'])));
    }
}

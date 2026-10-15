<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Support\Paths;
use AndrewProd\AiBlueprint\Support\Str;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathsTest extends TestCase
{
    public function test_normalize(): void
    {
        $this->assertSame('docs/a.md', Paths::normalize('./docs//a.md'));
        $this->assertSame('docs/a.md', Paths::normalize('docs\\x\\..\\a.md'));
        $this->assertSame('', Paths::normalize('./'));
    }

    public function test_normalize_rejects_escaping_paths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Paths::normalize('docs/../../etc/passwd');
    }

    public function test_relative_and_within(): void
    {
        $this->assertSame('app/X.php', Paths::relativeTo('/p', '/p/app/X.php'));
        $this->assertNull(Paths::relativeTo('/p', '/project/app/X.php'));
        $this->assertTrue(Paths::isWithin('vendor/a/b.php', 'vendor'));
        $this->assertFalse(Paths::isWithin('vendors/a.php', 'vendor'));
        $this->assertTrue(Paths::isAbsolute('C:\\x'));
        $this->assertTrue(Paths::isAbsolute('/x'));
        $this->assertFalse(Paths::isAbsolute('x/y'));
    }

    #[DataProvider('globs')]
    public function test_glob_matching(string $path, string $pattern, bool $expected): void
    {
        $this->assertSame($expected, Paths::matches($path, $pattern));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function globs(): array
    {
        return [
            'env basename' => ['resources/.env', '.env', true],
            'env variants' => ['.env.production', '.env.*', true],
            'not env example dir' => ['environment/x.php', '.env', false],
            'key extension' => ['storage/oauth-private.key', '*.key', true],
            'double star' => ['public/build/assets/app.js', 'public/build/**', true],
            'single star stays in segment' => ['public/build/assets/app.js', 'public/*/app.js', false],
        ];
    }

    public function test_string_helpers(): void
    {
        $this->assertSame('add-customer-notifications', Str::slug('Add customer notifications!'));
        $this->assertSame('user_controller', Str::snake('UserController'));
        $this->assertSame(['customer', 'notification', 'service'], Str::words('CustomerNotificationService'));
        $this->assertSame('category', Str::singular('categories'));
        $this->assertSame('notification', Str::singular('notifications'));
        $this->assertSame('address', Str::singular('address'));
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Application\BlueprintConfig;
use AndrewProd\AiBlueprint\Infrastructure\Filesystem\ProjectFiles;
use AndrewProd\AiBlueprint\Tests\TestCase;

final class ProjectFilesTest extends TestCase
{
    public function test_excluded_sensitive_binary_and_escaping_files_are_never_read(): void
    {
        $root = $this->temporaryDirectory();
        $outside = $this->temporaryDirectory();
        file_put_contents($outside.'/secret.txt', 'OUTSIDE');

        $this->putFile($root, 'app/Ok.php', '<?php class Ok {}');
        $this->putFile($root, 'app/.env', 'APP_KEY=base64:SECRET');
        $this->putFile($root, 'app/keys/oauth-private.key', 'PRIVATE');
        $this->putFile($root, 'app/vendor/lib.php', '<?php');
        $this->putFile($root, 'app/cache/x.php', '<?php');
        $this->putFile($root, 'app/image.php', "\0\0binary");
        $this->putFile($root, '.env', 'DB_PASSWORD=hunter2');
        $this->putFile($root, 'composer.json', '{}');
        symlink($outside.'/secret.txt', $root.'/app/link.php');
        symlink($outside, $root.'/app/linked-dir');

        $files = new ProjectFiles($root, ['app', '../outside'], ['vendor', 'app/cache'], BlueprintConfig::BUILTIN_SENSITIVE);

        $this->assertSame(['app/Ok.php', 'app/image.php'], $files->all());
        $this->assertSame(['app/.env', 'app/keys/oauth-private.key'], $files->sensitiveSkipped());

        $this->assertNull($files->read('.env'));
        $this->assertNull($files->read('app/.env'));
        $this->assertNull($files->read('app/link.php'));
        $this->assertNull($files->read('app/linked-dir/secret.txt'));
        $this->assertNull($files->read('../'.basename($outside).'/secret.txt'));
        $this->assertNull($files->read('app/image.php'));
        $this->assertNull($files->read('app/vendor/lib.php'));
        $this->assertSame('{}', $files->read('composer.json'));
    }
}

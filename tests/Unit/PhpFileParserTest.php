<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Infrastructure\Parsers\PhpFileParser;
use ParseError;
use PHPUnit\Framework\TestCase;

final class PhpFileParserTest extends TestCase
{
    public function test_it_extracts_classes_imports_and_methods(): void
    {
        $symbols = (new PhpFileParser)->parse(<<<'PHP'
        <?php
        namespace App\Listeners;

        use App\Events\{OrderPlaced, OrderShipped as Shipped};
        use Illuminate\Contracts\Queue\ShouldQueue;
        use function strlen;

        abstract class SendMail extends Base implements ShouldQueue, \Countable
        {
            use Queueable, \App\Concerns\Logs;

            public function handle(OrderPlaced $event): void
            {
                $x = function () use ($event) { return new class {}; };
                $name = Shipped::class;
            }

            private static function secret(): void {}
            public function count(): int { return 0; }
        }

        interface Marker extends ShouldQueue {}
        enum Status: string { case A = 'a'; public function label(): string { return 'A'; } }
        PHP);

        $this->assertSame('App\Listeners', $symbols->namespace);
        $this->assertSame('App\Events\OrderShipped', $symbols->uses['shipped']);
        $this->assertSame('App\Events\OrderPlaced', $symbols->uses['orderplaced']);
        $this->assertArrayNotHasKey('strlen', $symbols->uses);

        $this->assertCount(3, $symbols->classes);
        [$class, $interface, $enum] = $symbols->classes;

        $this->assertSame('App\Listeners\SendMail', $class['fqcn']);
        $this->assertTrue($class['abstract']);
        $this->assertSame('App\Listeners\Base', $class['extends']);
        $this->assertSame(['Illuminate\Contracts\Queue\ShouldQueue', 'Countable'], $class['implements']);
        $this->assertSame(['App\Listeners\Queueable', 'App\Concerns\Logs'], $class['traits']);
        $this->assertSame(['handle', 'secret', 'count'], array_column($class['methods'], 'name'));
        $this->assertSame('App\Events\OrderPlaced', $class['methods'][0]['first_param_type']);
        $this->assertSame('private', $class['methods'][1]['visibility']);

        $this->assertSame('interface', $interface['kind']);
        $this->assertSame(['Illuminate\Contracts\Queue\ShouldQueue'], $interface['implements']);

        $this->assertSame('enum', $enum['kind']);
        $this->assertSame(['label'], array_column($enum['methods'], 'name'));
    }

    public function test_invalid_php_throws_parse_error(): void
    {
        $this->expectException(ParseError::class);
        (new PhpFileParser)->parse("<?php class {\n");
    }
}

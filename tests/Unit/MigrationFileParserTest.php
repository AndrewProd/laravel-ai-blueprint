<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Analyzers\Database\MigrationFileParser;
use PHPUnit\Framework\TestCase;

final class MigrationFileParserTest extends TestCase
{
    public function test_it_reads_schema_operations_from_up_only(): void
    {
        $parser = new MigrationFileParser;
        $ops = $parser->parse(<<<'PHP'
        <?php
        return new class extends Migration {
            public function up(): void
            {
                Schema::create('posts', function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('author_id')->constrained('users');
                    $t->foreignId('category_id')->constrained();
                    $t->string('title')->nullable()->index();
                    $t->morphs('taggable');
                    $t->unique(['title', 'author_id']);
                    $t->foreign('editor_id')->references('id')->on('users');
                    $t->timestamps();
                });
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn(['legacy', 'old']);
                    $table->renameColumn('name', 'full_name');
                });
                Schema::rename('tags', 'labels');
                Schema::create($name, function (Blueprint $table) {});
                DB::statement('ALTER TABLE x');
            }
            public function down(): void
            {
                Schema::dropIfExists('posts');
            }
        };
        PHP);

        $this->assertSame(['create', 'table', 'rename'], array_column($ops, 'op'));

        $posts = $ops[0];
        $this->assertSame(
            ['id', 'author_id', 'category_id', 'title', 'taggable_id', 'taggable_type', 'created_at', 'updated_at'],
            array_column($posts['columns'], 'name'),
        );
        $this->assertTrue($posts['columns'][3]['nullable']);
        $this->assertSame([
            ['column' => 'author_id', 'references' => 'id', 'on' => 'users', 'inferred' => false],
            ['column' => 'category_id', 'references' => 'id', 'on' => 'categories', 'inferred' => true],
            ['column' => 'editor_id', 'references' => 'id', 'on' => 'users', 'inferred' => false],
        ], $posts['foreign_keys']);
        $this->assertContains(['type' => 'unique', 'columns' => ['title', 'author_id']], $posts['indexes']);

        $this->assertSame(['legacy', 'old'], $ops[1]['dropped']);
        $this->assertSame(['name' => 'full_name'], $ops[1]['renamed']);
        $this->assertSame('labels', $ops[2]['to']);

        $this->assertSame(['dynamic table name'], array_column($parser->unrecognized(), 'reason'));
    }
}

<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Tests\Unit;

use AndrewProd\AiBlueprint\Analyzers\Routes\RouteFileParser;
use PHPUnit\Framework\TestCase;

final class RouteFileParserTest extends TestCase
{
    public function test_common_route_definitions(): void
    {
        $parser = new RouteFileParser;
        $routes = $parser->parse(<<<'PHP'
        <?php
        use App\Http\Controllers\PhotoController;
        use App\Http\Controllers\Admin;
        use Illuminate\Support\Facades\Route;

        Route::get('/', fn () => 'home');
        Route::match(['get', 'post'], '/search', [PhotoController::class, 'search'])->name('search');
        Route::resource('photos', PhotoController::class)->except(['create', 'edit']);
        Route::apiResource('users.posts', Admin\PostController::class);
        Route::prefix('admin')->middleware(['auth', 'verified'])->group(function () {
            Route::prefix('v1')->group(function () {
                Route::delete('/photos/{photo}', [PhotoController::class, 'destroy']);
            });
            Route::put('settings', 'SettingsController@update');
        });
        Route::group(['prefix' => 'legacy'], function () {
            Route::get('ping', PhotoController::class);
        });
        Route::get('/after', [PhotoController::class, 'after']);
        Route::get($dynamic, [PhotoController::class, 'x']);
        PHP);

        $summary = array_map(static fn (array $r): string => $r['method'].' '.$r['uri'].' '.($r['controller'] ?? $r['action']).'@'.($r['controller_method'] ?? ''), $routes);

        $this->assertSame([
            'GET / closure@',
            'GET search App\Http\Controllers\PhotoController@search',
            'POST search App\Http\Controllers\PhotoController@search',
            'GET photos App\Http\Controllers\PhotoController@index',
            'POST photos App\Http\Controllers\PhotoController@store',
            'GET photos/{photo} App\Http\Controllers\PhotoController@show',
            'PUT photos/{photo} App\Http\Controllers\PhotoController@update',
            'DELETE photos/{photo} App\Http\Controllers\PhotoController@destroy',
            'GET users/{user}/posts App\Http\Controllers\Admin\PostController@index',
            'POST users/{user}/posts App\Http\Controllers\Admin\PostController@store',
            'GET users/{user}/posts/{post} App\Http\Controllers\Admin\PostController@show',
            'PUT users/{user}/posts/{post} App\Http\Controllers\Admin\PostController@update',
            'DELETE users/{user}/posts/{post} App\Http\Controllers\Admin\PostController@destroy',
            'DELETE admin/v1/photos/{photo} App\Http\Controllers\PhotoController@destroy',
            'PUT admin/settings SettingsController@update',
            'GET legacy/ping App\Http\Controllers\PhotoController@__invoke',
            'GET after App\Http\Controllers\PhotoController@after',
        ], $summary);

        $this->assertSame('search', $routes[1]['name']);
        $this->assertFalse($routes[14]['resolved'], 'legacy string actions cannot be resolved statically');
        $this->assertCount(1, $parser->unrecognized());
        $this->assertSame('dynamic route URI or methods', $parser->unrecognized()[0]['reason']);
    }
}

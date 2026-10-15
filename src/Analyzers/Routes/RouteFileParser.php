<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Analyzers\Routes;

use AndrewProd\AiBlueprint\Infrastructure\Parsers\PhpFileParser;
use AndrewProd\AiBlueprint\Infrastructure\Parsers\PhpFileSymbols;
use AndrewProd\AiBlueprint\Infrastructure\Parsers\TokenStream;
use AndrewProd\AiBlueprint\Support\Str;

/**
 * Statically recognizes common Laravel Router calls in a routes file:
 * Route::get/post/put/patch/delete/options/any/match/view/redirect,
 * Route::resource/apiResource, and prefix(...)->group(function () {...}).
 *
 * Routes registered dynamically (loops, packages, runtime providers) are not found.
 */
final class RouteFileParser
{
    private const VERBS = ['get', 'post', 'put', 'patch', 'delete', 'options', 'any'];

    private const RESOURCE_ACTIONS = [
        'index' => ['GET', ''],
        'create' => ['GET', '/create'],
        'store' => ['POST', ''],
        'show' => ['GET', '/{param}'],
        'edit' => ['GET', '/{param}/edit'],
        'update' => ['PUT', '/{param}'],
        'destroy' => ['DELETE', '/{param}'],
    ];

    /** @var list<array{line: int, reason: string}> */
    private array $unrecognized = [];

    /**
     * @return list<array{method: string, uri: string, action: string|null, controller: string|null, controller_method: string|null, name: string|null, middleware: list<string>, line: int, resolved: bool}>
     *
     * @throws \ParseError
     */
    public function parse(string $code): array
    {
        $this->unrecognized = [];
        $s = TokenStream::fromCode($code);
        $symbols = (new PhpFileParser)->parse($code);
        $routes = [];
        $prefixStack = []; // [depth, prefix]
        $pendingPrefix = null;
        $depth = 0;
        $count = $s->count();

        for ($i = 0; $i < $count; $i++) {
            $text = $s->text($i);

            if ($text === '{' || $s->is($i, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES)) {
                $depth++;
                if ($pendingPrefix !== null) {
                    $prefixStack[] = [$depth, $pendingPrefix];
                    $pendingPrefix = null;
                }

                continue;
            }
            if ($text === '}') {
                while ($prefixStack !== [] && end($prefixStack)[0] >= $depth) {
                    array_pop($prefixStack);
                }
                $depth--;

                continue;
            }

            if (! $this->isRouteFacade($s, $i, $symbols->uses) || ! $s->is($i + 1, T_DOUBLE_COLON)) {
                continue;
            }

            $outer = implode('/', array_filter(array_map(static fn (array $p): string => $p[1], $prefixStack)));
            [$chainRoutes, $next, $groupPrefix] = $this->parseChain($s, $i + 2, $outer, $symbols);
            array_push($routes, ...$chainRoutes);

            if ($groupPrefix !== null) {
                $pendingPrefix = $groupPrefix; // applied to the closure body that follows
            }
            $i = $next;
        }

        return $routes;
    }

    /**
     * @return list<array{line: int, reason: string}>
     */
    public function unrecognized(): array
    {
        return $this->unrecognized;
    }

    /**
     * @param  array<string, string>  $uses
     */
    private function isRouteFacade(TokenStream $s, int $i, array $uses): bool
    {
        if (! $s->is($i, T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED)) {
            return false;
        }
        $name = ltrim($s->text($i), '\\');
        if (strcasecmp($name, 'Route') === 0) {
            return ! isset($uses['route']) || in_array($uses['route'], ['Illuminate\Support\Facades\Route', 'Route'], true);
        }

        return $name === 'Illuminate\Support\Facades\Route';
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int, 2: string|null}
     */
    private function parseChain(TokenStream $s, int $i, string $outerPrefix, PhpFileSymbols $symbols): array
    {
        $calls = [];
        $count = $s->count();

        while ($i < $count && $s->is($i, T_STRING) && $s->text($i + 1) === '(') {
            $name = strtolower($s->text($i));
            $open = $i + 1;

            if ($name === 'group') {
                $args = $s->arguments($open);
                $prefix = null;
                foreach ($calls as $call) {
                    if ($call['name'] === 'prefix') {
                        $prefix = $s->literalString($call['args'][0] ?? null);
                        if ($prefix === null) {
                            $this->unrecognized[] = ['line' => $call['line'], 'reason' => 'dynamic route group prefix'];
                        }
                    }
                }
                $hasClosure = false;
                foreach ($args as $arg) {
                    if ($s->is($arg[0], T_FUNCTION) || ($s->is($arg[0], T_STATIC) && $s->is($arg[0] + 1, T_FUNCTION))) {
                        $hasClosure = true;
                    } elseif ($s->text($arg[0]) === '[') {
                        // Legacy Route::group(['prefix' => 'admin'], function () {...})
                        foreach ($s->arguments($arg[0]) as $item) {
                            if ($s->literalString([$item[0], $item[0]]) === 'prefix' && $s->is($item[0] + 1, T_DOUBLE_ARROW)) {
                                $prefix = $s->literalString([$item[0] + 2, $item[1]]);
                            }
                        }
                    }
                }

                if (! $hasClosure) {
                    $this->unrecognized[] = ['line' => $s->line($i), 'reason' => 'route group loading another file or callable'];

                    return [[], $s->matching($open), null];
                }

                // Routes inside the closure are scanned by the main loop; only the prefix is recorded here.
                return [[], $open, trim((string) $prefix, '/')];
            }

            $calls[] = ['name' => $name, 'args' => $s->arguments($open), 'line' => $s->line($i)];
            $i = $s->matching($open) + 1;

            if ($s->is($i, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR)) {
                $i++;

                continue;
            }
            break;
        }

        return [$this->routesFromCalls($s, $calls, $outerPrefix, $symbols), $i, null];
    }

    /**
     * @param  list<array{name: string, args: list<array{0: int, 1: int}>, line: int}>  $calls
     * @return list<array<string, mixed>>
     */
    private function routesFromCalls(TokenStream $s, array $calls, string $outerPrefix, PhpFileSymbols $symbols): array
    {
        $prefix = $outerPrefix;
        $name = null;
        $middleware = [];
        $only = null;
        $except = null;

        foreach ($calls as $call) {
            if ($call['name'] === 'prefix') {
                $prefix = $this->joinUri($prefix, (string) $s->literalString($call['args'][0] ?? null));
            } elseif ($call['name'] === 'name' && ($value = $s->literalString($call['args'][0] ?? null)) !== null) {
                $name = ($name ?? '').$value;
            } elseif ($call['name'] === 'middleware') {
                foreach ($call['args'] as $arg) {
                    array_push($middleware, ...($s->literalStringList($arg) ?? []));
                }
            } elseif ($call['name'] === 'only') {
                $only = $s->literalStringList($call['args'][0] ?? null);
            } elseif ($call['name'] === 'except') {
                $except = $s->literalStringList($call['args'][0] ?? null);
            }
        }

        $routes = [];
        foreach ($calls as $call) {
            $verb = $call['name'];
            $args = $call['args'];

            if (in_array($verb, self::VERBS, true) || in_array($verb, ['view', 'redirect', 'permanentredirect'], true)) {
                $uri = $s->literalString($args[0] ?? null);
                $methods = match ($verb) {
                    'any' => ['ANY'],
                    'view', 'redirect', 'permanentredirect' => ['GET'],
                    default => [strtoupper($verb)],
                };
                $action = in_array($verb, self::VERBS, true) ? $this->action($s, $args[1] ?? null, $symbols) : ['type' => $verb];
            } elseif ($verb === 'match') {
                $methods = array_map('strtoupper', $s->literalStringList($args[0] ?? null) ?? []);
                $uri = $s->literalString($args[1] ?? null);
                $action = $this->action($s, $args[2] ?? null, $symbols);
            } elseif ($verb === 'resource' || $verb === 'apiresource') {
                $resource = $s->literalString($args[0] ?? null);
                $controller = $this->classReference($s, $args[1] ?? null, $symbols);
                if ($resource === null || $controller === null) {
                    $this->unrecognized[] = ['line' => $call['line'], 'reason' => 'dynamic resource route'];

                    continue;
                }
                array_push($routes, ...$this->expandResource($resource, $controller, $verb === 'apiresource', $only, $except, $prefix, $middleware, $call['line']));

                continue;
            } else {
                continue;
            }

            if ($uri === null || $methods === []) {
                $this->unrecognized[] = ['line' => $call['line'], 'reason' => 'dynamic route URI or methods'];

                continue;
            }

            foreach ($methods as $method) {
                $routes[] = [
                    'method' => $method,
                    'uri' => $this->joinUri($prefix, $uri),
                    'action' => $action['type'],
                    'controller' => $action['controller'] ?? null,
                    'controller_method' => $action['method'] ?? null,
                    'name' => $name,
                    'middleware' => array_values(array_unique($middleware)),
                    'line' => $call['line'],
                    'resolved' => $action['resolved'] ?? true,
                ];
            }
        }

        return $routes;
    }

    /**
     * @param  array{0: int, 1: int}|null  $range
     * @return array{type: string, controller?: string, method?: string|null, resolved?: bool}
     */
    private function action(TokenStream $s, ?array $range, PhpFileSymbols $symbols): array
    {
        if ($range === null) {
            return ['type' => 'none'];
        }

        if ($s->is($range[0], T_FUNCTION, T_FN, T_STATIC)) {
            return ['type' => 'closure'];
        }

        if ($s->text($range[0]) === '[' && $s->matching($range[0]) === $range[1]) {
            $items = $s->arguments($range[0]);
            $class = $this->classReference($s, $items[0] ?? null, $symbols);
            $method = $s->literalString($items[1] ?? null);
            if ($class !== null) {
                return ['type' => 'controller', 'controller' => $class, 'method' => $method, 'resolved' => $method !== null];
            }
        }

        $class = $this->classReference($s, $range, $symbols);
        if ($class !== null) {
            return ['type' => 'controller', 'controller' => $class, 'method' => '__invoke'];
        }

        $string = $s->literalString($range);
        if ($string !== null && str_contains($string, '@')) {
            [$controller, $method] = explode('@', $string, 2);

            // Legacy string syntax: the namespace depends on runtime configuration.
            return ['type' => 'controller', 'controller' => ltrim($controller, '\\'), 'method' => $method, 'resolved' => false];
        }

        return ['type' => 'unknown', 'resolved' => false];
    }

    /**
     * Resolves `Foo::class` to a FQCN.
     *
     * @param  array{0: int, 1: int}|null  $range
     */
    private function classReference(TokenStream $s, ?array $range, PhpFileSymbols $symbols): ?string
    {
        if ($range === null || $range[1] - $range[0] !== 2) {
            return null;
        }
        if (! $s->is($range[0], T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED) || ! $s->is($range[0] + 1, T_DOUBLE_COLON) || strtolower($s->text($range[1])) !== 'class') {
            return null;
        }

        return (new PhpFileParser)->resolve($s->text($range[0]), $symbols->namespace, $symbols->uses);
    }

    /**
     * @param  list<string>|null  $only
     * @param  list<string>|null  $except
     * @param  list<string>  $middleware
     * @return list<array<string, mixed>>
     */
    private function expandResource(string $resource, string $controller, bool $api, ?array $only, ?array $except, string $prefix, array $middleware, int $line): array
    {
        $segments = explode('.', $resource);
        $uri = '';
        foreach ($segments as $index => $segment) {
            $uri .= '/'.$segment;
            if ($index < count($segments) - 1) {
                $uri .= '/{'.$this->parameter($segment).'}';
            }
        }
        $param = $this->parameter((string) end($segments));

        $routes = [];
        foreach (self::RESOURCE_ACTIONS as $action => [$method, $suffix]) {
            if ($api && in_array($action, ['create', 'edit'], true)) {
                continue;
            }
            if ($only !== null && ! in_array($action, $only, true)) {
                continue;
            }
            if ($except !== null && in_array($action, $except, true)) {
                continue;
            }

            $routes[] = [
                'method' => $method,
                'uri' => $this->joinUri($prefix, $uri.str_replace('{param}', '{'.$param.'}', $suffix)),
                'action' => 'controller',
                'controller' => $controller,
                'controller_method' => $action,
                'name' => $resource.'.'.$action,
                'middleware' => array_values(array_unique($middleware)),
                'line' => $line,
                'resolved' => true,
            ];
        }

        return $routes;
    }

    private function parameter(string $segment): string
    {
        return str_replace('-', '_', Str::singular($segment));
    }

    private function joinUri(string $prefix, string $uri): string
    {
        $joined = trim(trim($prefix, '/').'/'.trim($uri, '/'), '/');

        return $joined === '' ? '/' : $joined;
    }
}

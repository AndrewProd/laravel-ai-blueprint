<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Parsers;

/**
 * Extracts namespaces, imports and class-like declarations from a PHP file
 * using the PHP tokenizer. It never executes or includes the file.
 */
final class PhpFileParser
{
    /**
     * @throws \ParseError when the file is not valid PHP
     */
    public function parse(string $code): PhpFileSymbols
    {
        $s = TokenStream::fromCode($code);

        $namespace = '';
        /** @var array<string, string> $uses alias => FQCN */
        $uses = [];
        /** @var array<string, int> $useLines */
        $useLines = [];
        $classes = [];
        $count = $s->count();
        $depth = 0;
        $classStack = []; // [classIndex, bodyDepth]

        for ($i = 0; $i < $count; $i++) {
            $token = $s->at($i);
            if ($token === null) {
                break;
            }

            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;

                continue;
            }
            if ($token->text === '}') {
                $depth--;
                if ($classStack !== [] && $depth < end($classStack)[1]) {
                    array_pop($classStack);
                }

                continue;
            }

            if ($token->is(T_NAMESPACE) && $s->is($i + 1, T_STRING, T_NAME_QUALIFIED)) {
                $namespace = $s->text($i + 1);
                $uses = [];

                continue;
            }

            // Top-level imports only; `use` inside a class body imports traits.
            if ($token->is(T_USE) && $classStack === [] && ! $s->is($i - 1, ')')) {
                $i = $this->parseUse($s, $i, $uses, $useLines);

                continue;
            }

            if ($token->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                if ($s->is($i - 1, T_DOUBLE_COLON, T_NEW) || ! $s->is($i + 1, T_STRING)) {
                    continue; // Foo::class, new class {...}, enum used as identifier
                }

                $kind = strtolower($token->text);
                $name = $s->text($i + 1);
                $abstract = $s->is($i - 1, T_ABSTRACT) || ($s->is($i - 1, T_READONLY, T_FINAL) && $s->is($i - 2, T_ABSTRACT));
                $extends = [];
                $implements = [];
                $j = $i + 2;
                $mode = null;
                while ($j < $count && $s->text($j) !== '{') {
                    if ($s->is($j, T_EXTENDS)) {
                        $mode = 'extends';
                    } elseif ($s->is($j, T_IMPLEMENTS)) {
                        $mode = 'implements';
                    } elseif ($s->is($j, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED) && $mode !== null) {
                        $resolved = $this->resolve($s->text($j), $namespace, $uses);
                        $mode === 'extends' ? $extends[] = $resolved : $implements[] = $resolved;
                    }
                    $j++;
                }

                $classes[] = [
                    'kind' => $kind,
                    'name' => $name,
                    'fqcn' => ltrim($namespace.'\\'.$name, '\\'),
                    'line' => $token->line,
                    'abstract' => $abstract,
                    'extends' => $kind === 'interface' ? null : ($extends[0] ?? null),
                    'implements' => $kind === 'interface' ? $extends : $implements,
                    'traits' => [],
                    'methods' => [],
                ];
                $i = $j - 1; // the "{" is handled by the depth tracking on the next iteration
                $classStack[] = [count($classes) - 1, $depth + 1];

                continue;
            }

            if ($classStack === []) {
                continue;
            }

            [$classIndex, $bodyDepth] = end($classStack);
            if ($depth !== $bodyDepth) {
                continue;
            }

            if ($token->is(T_USE)) {
                for ($j = $i + 1; $j < $count && ! in_array($s->text($j), [';', '{'], true); $j++) {
                    if ($s->is($j, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED)) {
                        $classes[$classIndex]['traits'][] = $this->resolve($s->text($j), $namespace, $uses);
                    }
                }

                continue;
            }

            if ($token->is(T_FUNCTION) && $s->is($i + 1, T_STRING)) {
                $visibility = 'public';
                for ($k = $i - 1; $k >= 0 && $s->is($k, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_ABSTRACT, T_FINAL); $k--) {
                    if ($s->is($k, T_PROTECTED)) {
                        $visibility = 'protected';
                    } elseif ($s->is($k, T_PRIVATE)) {
                        $visibility = 'private';
                    }
                }

                $method = ['name' => $s->text($i + 1), 'visibility' => $visibility, 'line' => $token->line, 'first_param_type' => null];
                if ($s->text($i + 2) === '(' && $s->is($i + 3, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED) && $s->is($i + 4, T_VARIABLE)) {
                    $method['first_param_type'] = $this->resolve($s->text($i + 3), $namespace, $uses);
                }
                $classes[$classIndex]['methods'][] = $method;
            }
        }

        return new PhpFileSymbols($namespace, $uses, $useLines, $classes);
    }

    /**
     * @param  array<string, string>  $uses
     * @param  array<string, int>  $useLines
     */
    private function parseUse(TokenStream $s, int $i, array &$uses, array &$useLines): int
    {
        $count = $s->count();
        $line = $s->line($i);
        $j = $i + 1;

        if ($s->is($j, T_FUNCTION, T_CONST)) {
            while ($j < $count && $s->text($j) !== ';') {
                $j++;
            }

            return $j;
        }

        // Collect [name, alias] pairs first, then register them.
        $prefix = '';
        $entries = [];
        $current = null;
        $alias = null;

        for (; $j < $count; $j++) {
            $text = $s->text($j);
            if ($text === ';' || $text === ',' || ($text === '}' && $prefix !== '')) {
                if ($current !== null) {
                    $entries[] = [$prefix.$current, $alias];
                }
                $current = null;
                $alias = null;
                if ($text === ';') {
                    break;
                }
            } elseif ($text === '{') {
                $prefix = rtrim((string) $current, '\\').'\\';
                $current = null;
            } elseif ($s->is($j, T_AS)) {
                $alias = $s->text($j + 1);
                $j++;
            } elseif ($s->is($j, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED)) {
                $current = $s->text($j);
            } elseif ($s->is($j, T_NS_SEPARATOR)) {
                $current .= '\\';
            }
        }

        foreach ($entries as [$name, $entryAlias]) {
            $fqcn = ltrim($name, '\\');
            $short = $entryAlias ?? substr($fqcn, (int) strrpos('\\'.$fqcn, '\\'));
            $uses[strtolower($short)] = $fqcn;
            $useLines[$fqcn] = $line;
        }

        return $j;
    }

    /**
     * @param  array<string, string>  $uses
     */
    public function resolve(string $name, string $namespace, array $uses): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        if (in_array(strtolower($name), ['self', 'static', 'parent', 'string', 'int', 'float', 'bool', 'array', 'mixed', 'object', 'callable', 'iterable', 'void', 'null', 'never', 'false', 'true'], true)) {
            return $name;
        }

        $parts = explode('\\', $name, 2);
        $first = strtolower($parts[0]);
        if (isset($uses[$first])) {
            return $uses[$first].(isset($parts[1]) ? '\\'.$parts[1] : '');
        }

        return ltrim($namespace.'\\'.$name, '\\');
    }
}

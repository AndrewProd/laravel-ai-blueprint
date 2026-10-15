<?php

declare(strict_types=1);

namespace AndrewProd\AiBlueprint\Infrastructure\Parsers;

use PhpToken;

/**
 * Thin helper over PhpToken arrays with whitespace/comments stripped.
 */
final class TokenStream
{
    /** @var list<PhpToken> */
    public readonly array $tokens;

    /**
     * @throws \ParseError on invalid PHP
     */
    public static function fromCode(string $code): self
    {
        $tokens = PhpToken::tokenize($code, TOKEN_PARSE);

        return new self(array_values(array_filter(
            $tokens,
            static fn (PhpToken $t): bool => ! $t->isIgnorable(),
        )));
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function at(int $i): ?PhpToken
    {
        return $this->tokens[$i] ?? null;
    }

    public function is(int $i, int|string ...$kinds): bool
    {
        $token = $this->tokens[$i] ?? null;

        return $token !== null && $token->is($kinds);
    }

    public function text(int $i): string
    {
        return $this->tokens[$i]->text ?? '';
    }

    public function line(int $i): int
    {
        return $this->tokens[$i]->line ?? 0;
    }

    /**
     * Index of the token closing the bracket opened at $open ("(", "[" or "{").
     */
    public function matching(int $open): int
    {
        $pairs = ['(' => ')', '[' => ']', '{' => '}'];
        $openText = $this->text($open);
        $close = $pairs[$openText] ?? null;
        if ($close === null) {
            return $open;
        }

        $depth = 0;
        $count = $this->count();
        for ($i = $open; $i < $count; $i++) {
            $text = $this->text($i);
            if ($text === $openText || ($openText === '{' && $this->is($i, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES))) {
                $depth++;
            } elseif ($text === $close) {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return $count - 1;
    }

    /**
     * Split the tokens between $open and its matching bracket into top-level
     * comma-separated arguments.
     *
     * @return list<array{0: int, 1: int}> inclusive [start, end] index ranges
     */
    public function arguments(int $open): array
    {
        $close = $this->matching($open);
        $args = [];
        $start = $open + 1;
        $depth = 0;

        for ($i = $open + 1; $i < $close; $i++) {
            $text = $this->text($i);
            if (in_array($text, ['(', '[', '{'], true) || $this->is($i, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES)) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($text === ',' && $depth === 0) {
                $args[] = [$start, $i - 1];
                $start = $i + 1;
            }
        }

        if ($start <= $close - 1) {
            $args[] = [$start, $close - 1];
        }

        return $args;
    }

    /**
     * The literal value of a single-token string argument, or null when the
     * argument is dynamic (variables, concatenation, interpolation, ...).
     *
     * @param  array{0: int, 1: int}|null  $range
     */
    public function literalString(?array $range): ?string
    {
        if ($range === null || $range[0] !== $range[1] || ! $this->is($range[0], T_CONSTANT_ENCAPSED_STRING)) {
            return null;
        }

        return self::unquote($this->text($range[0]));
    }

    /**
     * List of literal strings from an array argument like ['a', 'b'].
     *
     * @param  array{0: int, 1: int}|null  $range
     * @return list<string>|null
     */
    public function literalStringList(?array $range): ?array
    {
        if ($range === null) {
            return null;
        }
        if ($this->literalString($range) !== null) {
            return [(string) $this->literalString($range)];
        }
        if ($this->text($range[0]) !== '[' || $this->matching($range[0]) !== $range[1]) {
            return null;
        }

        $values = [];
        foreach ($this->arguments($range[0]) as $item) {
            $value = $this->literalString($item);
            if ($value === null) {
                return null;
            }
            $values[] = $value;
        }

        return $values;
    }

    public static function unquote(string $literal): string
    {
        $quote = $literal[0] ?? '';
        $inner = substr($literal, 1, -1);

        return $quote === "'"
            ? str_replace(["\\'", '\\\\'], ["'", '\\'], $inner)
            : stripcslashes($inner);
    }
}

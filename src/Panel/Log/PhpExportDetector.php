<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use ParseError;
use PhpToken;

use function array_pop;
use function array_slice;
use function end;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_contains;
use function str_repeat;
use function strlen;
use function trim;

use const T_ARRAY;
use const T_CLOSE_TAG;
use const T_WHITESPACE;
use const TOKEN_PARSE;

/**
 * Detects a log message that is the `var_export()` or `VarDumper::export()` output of an array or object and returns it
 * in short array syntax.
 *
 * A message qualifies when, trimmed, it starts like an export (`array (`, `[`, `(object) array(`, or
 * `\<Class>::__set_state(`) and is exactly one syntactically valid PHP expression, within {@see PrintRParser::MAX_BYTES}
 * bytes. The check tokenizes the message with the PHP parser and never evaluates it. Parser diagnostics are muted
 * while it runs: a no-op error handler keeps a deprecation (such as a `(boolean)` cast) away from the application's
 * error handler, and the `@` operator keeps a compile warning (such as an `"\400"` octal overflow), which no user
 * handler can catch, out of the response.
 *
 * The returned source writes every `array(...)` as `[...]`, moves a value that `var_export()` prints on the line after
 * its `=>` up to the key, indents each line by four spaces per open bracket, and writes an empty array as `[]`. Tokens
 * keep their text, so string contents never change; a short-syntax export such as `VarDumper::export()` output keeps its
 * layout.
 *
 * Usage example:
 * ```php
 * $code = PhpExportDetector::detect(var_export(['user' => ['id' => 7]], true));
 * // "[\n    'user' => [\n        'id' => 7,\n    ],\n]"
 * ```
 */
final class PhpExportDetector
{
    /**
     * Indentation of one bracket level.
     */
    private const string INDENT = '    ';
    /**
     * Leading text of a PHP value export: an `array (` or short `[` array, a `stdClass` cast, or a `__set_state()` call.
     */
    private const string OPENER = '/^(?:array \(|\[|\(object\) array\(|\\\\[\w\\\\\x80-\xff]+::__set_state\()/';

    /**
     * Returns a PHP value export in short array syntax.
     *
     * @param string $message Log message to inspect.
     *
     * @return string|null Export source in short array syntax, or `null` when the message is not a single export
     * expression within the size bound.
     */
    public static function detect(string $message): string|null
    {
        if (strlen($message) > PrintRParser::MAX_BYTES) {
            return null;
        }

        $code = trim($message);

        if (preg_match(self::OPENER, $code) !== 1) {
            return null;
        }

        $tokens = self::tokenize($code);

        return $tokens === null ? null : self::shortArrays($tokens);
    }

    /**
     * Lays out the whitespace between two tokens.
     *
     * Whitespace without a line break stays as is. A line break after `=>` becomes one space, a line break inside an
     * empty array disappears, and any other line break starts a line indented to the bracket depth, one level less
     * before a closing bracket.
     *
     * @param string $space Whitespace text, or `''` when the tokens are adjacent.
     * @param string $previous Text of the token before the whitespace; `[` for a rewritten `array(`.
     * @param bool $closes Whether the token after the whitespace closes an array.
     * @param int $depth Number of arrays open before the token after the whitespace.
     */
    private static function layout(string $space, string $previous, bool $closes, int $depth): string
    {
        if (str_contains($space, "\n") === false) {
            return $space;
        }

        if ($previous === '=>') {
            return ' ';
        }

        if ($closes) {
            return $previous === '[' ? '' : "\n" . str_repeat(self::INDENT, $depth - 1);
        }

        return "\n" . str_repeat(self::INDENT, $depth);
    }

    /**
     * Rebuilds the expression from its tokens with `array(...)` written as `[...]`.
     *
     * A stack of the open parentheses tells the `)` closing an `array(` from the one closing a call such as
     * `__set_state(`.
     *
     * @param array<PhpToken> $tokens Tokens of the expression, in source order.
     *
     * @return string Expression source in short array syntax.
     */
    private static function shortArrays(array $tokens): string
    {
        $code = '';
        $depth = 0;
        $keyword = false;
        $parentheses = [];
        $previous = '';
        $space = '';

        foreach ($tokens as $token) {
            if ($token->is(T_WHITESPACE)) {
                $space = $token->text;

                continue;
            }

            $text = $token->text;

            if ($keyword) {
                $keyword = false;

                if ($text === '(') {
                    $parentheses[] = true;
                    $depth++;
                    $code .= '[';
                    $previous = '[';
                    $space = '';

                    continue;
                }

                $code .= 'array';
            }

            $closes = $text === ']' || ($text === ')' && end($parentheses));
            $code .= self::layout($space, $previous, $closes, $depth);
            $previous = $text;
            $space = '';

            if ($token->is(T_ARRAY)) {
                $keyword = true;

                continue;
            }

            if ($text === '(') {
                $parentheses[] = false;
            }

            if ($text === ')') {
                array_pop($parentheses);
            }

            if ($text === '[') {
                $depth++;
            }

            if ($closes) {
                $depth--;
            }

            $code .= $closes ? ']' : $text;
        }

        return $code;
    }

    /**
     * Tokenizes the source as the operand of a `return` statement.
     *
     * A closing tag or any statement terminator besides the appended one means the text smuggles further statements
     * or inline output after the expression. The error handler is restored on every path.
     *
     * @return array<PhpToken>|null Tokens of the expression alone, or `null` when the source is not exactly one valid
     * expression.
     */
    private static function tokenize(string $code): array|null
    {
        set_error_handler(static fn(): bool => true);

        try {
            $tokens = @PhpToken::tokenize("<?php return {$code};", TOKEN_PARSE);
        } catch (ParseError) {
            return null;
        } finally {
            restore_error_handler();
        }

        $terminators = 0;

        foreach ($tokens as $token) {
            if ($token->is(T_CLOSE_TAG)) {
                return null;
            }

            if ($token->is(';')) {
                $terminators++;
            }
        }

        return $terminators === 1 ? array_slice($tokens, 3, -1) : null;
    }
}

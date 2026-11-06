<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use ParseError;
use PhpToken;

use function array_pop;
use function array_slice;
use function count;
use function end;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function str_repeat;
use function strlen;
use function strpbrk;
use function trim;

use const T_ARRAY;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DNUMBER;
use const T_DOUBLE_ARROW;
use const T_DOUBLE_COLON;
use const T_LNUMBER;
use const T_NAME_FULLY_QUALIFIED;
use const T_OBJECT_CAST;
use const T_RETURN;
use const T_STRING;
use const T_WHITESPACE;
use const TOKEN_PARSE;

/**
 * Detects a log message that is the `var_export()` or `VarDumper::export()` output of an array or object and returns it
 * in short array syntax.
 *
 * A message qualifies when, trimmed, it starts like an export (`array (`, `[`, `(object) array(`, or
 * `\<Class>::__set_state(`) and is exactly one syntactically valid PHP expression, within {@see PrintRParser::MAX_BYTES}
 * bytes, made only of the token kinds these exports print: arrays, casts to `object`, quoted strings without
 * interpolation, numbers, names, `::`, `=>`, `-`, `.`, `,`, and whitespace. Variables, interpolated strings, heredocs,
 * comments, closures, statements, and inline output are rejected. The check tokenizes the message with the PHP parser
 * and never evaluates it. Parser diagnostics are muted while it runs: a no-op error handler keeps a deprecation (such as
 * a `(boolean)` cast) away from the application's error handler, and the `@` operator keeps a compile warning (such as
 * an `"\400"` octal overflow), which no user handler can catch, out of the response.
 *
 * The returned source writes every `array(...)` as `[...]`, moves a value that `var_export()` prints on the line after
 * its `=>` up to the key, indents each line by four spaces per open bracket, and writes an empty array as `[]`. Tokens
 * keep their text, so string contents never change; a short-syntax export such as `VarDumper::export()` output keeps its
 * layout. More than {@see PrintRParser::MAX_DEPTH} nested arrays, or a result longer than {@see self::MAX_OUTPUT}
 * bytes, yields `null`.
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
     * Token id of `]`; a single-character token has the byte value of its character as id.
     */
    private const int CLOSE_BRACKET = 93;
    /**
     * Token id of `)`.
     */
    private const int CLOSE_PARENTHESIS = 41;
    /**
     * Indentation of one bracket level.
     */
    private const string INDENT = '    ';
    /**
     * Maximum size of the returned source, in bytes: re-indentation may enlarge a deeply nested export.
     */
    private const int MAX_OUTPUT = 4 * PrintRParser::MAX_BYTES;
    /**
     * Token id of `[`.
     */
    private const int OPEN_BRACKET = 91;
    /**
     * Token id of `(`.
     */
    private const int OPEN_PARENTHESIS = 40;
    /**
     * Leading text of a PHP value export: an `array (` or short `[` array, a `stdClass` cast, or a `__set_state()` call.
     */
    private const string OPENER = '/^(?:array \(|\[|\(object\) array\(|\\\\[\w\\\\\x80-\xff]+::__set_state\()/';
    /**
     * Ids of the tokens a `var_export()` or `VarDumper::export()` value is made of: the four brackets, `,` (44), `-`
     * (45), and `.` (46), which joins the parts of a binary string.
     */
    private const array TOKENS = [
        self::CLOSE_BRACKET,
        self::CLOSE_PARENTHESIS,
        self::OPEN_BRACKET,
        self::OPEN_PARENTHESIS,
        44,
        45,
        46,
        T_ARRAY,
        T_CONSTANT_ENCAPSED_STRING,
        T_DNUMBER,
        T_DOUBLE_ARROW,
        T_DOUBLE_COLON,
        T_LNUMBER,
        T_NAME_FULLY_QUALIFIED,
        T_OBJECT_CAST,
        T_STRING,
        T_WHITESPACE,
    ];

    /**
     * Returns a PHP value export in short array syntax.
     *
     * @param string $message Log message to inspect.
     *
     * @return string|null Export source in short array syntax, or `null` when the message is not a single export
     * expression within the size, depth, and output bounds.
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
     * Whitespace without a line break stays as is. A line break (`\n`, `\r\n`, or `\r`) after `=>` becomes one space, a
     * line break inside an empty array disappears, and any other line break starts a line indented to the bracket
     * depth, one level less before a closing bracket.
     *
     * @param string $space Whitespace text, or `''` when the tokens are adjacent.
     * @param int $previous Id of the token before the whitespace; `[` for the `(` of an `array(`.
     * @param bool $closes Whether the token after the whitespace closes an array.
     * @param int $depth Number of arrays open before the token after the whitespace.
     */
    private static function layout(string $space, int $previous, bool $closes, int $depth): string
    {
        if (strpbrk($space, "\r\n") === false) {
            return $space;
        }

        if ($previous === T_DOUBLE_ARROW) {
            return ' ';
        }

        if ($closes) {
            return $previous === self::OPEN_BRACKET ? '' : "\n" . str_repeat(self::INDENT, $depth - 1);
        }

        return "\n" . str_repeat(self::INDENT, $depth);
    }

    /**
     * Rebuilds the expression from its tokens with `array(...)` written as `[...]`.
     *
     * Structure is read from token ids, never from token text. A stack holds one entry per open array, counting the
     * other parentheses open inside it, so the `)` closing an `array(` is told from the one closing a call such as
     * `__set_state(`; the depth is the size of that stack and never drops below zero.
     *
     * @param array<PhpToken> $tokens Tokens of the expression, in source order.
     *
     * @return string|null Expression source in short array syntax, or `null` when it nests more than
     * {@see PrintRParser::MAX_DEPTH} arrays or grows beyond {@see self::MAX_OUTPUT} bytes.
     */
    private static function shortArrays(array $tokens): string|null
    {
        $arrays = [];
        $code = '';
        $keyword = false;
        $previous = T_RETURN;
        $space = '';

        foreach ($tokens as $token) {
            if ($token->id === T_WHITESPACE) {
                $space = $keyword ? '' : $token->text;

                continue;
            }

            $id = $keyword ? self::OPEN_BRACKET : $token->id;
            $keyword = $id === T_ARRAY;
            $inner = end($arrays);
            $closes = $inner !== false
                && ($id === self::CLOSE_BRACKET || ($id === self::CLOSE_PARENTHESIS && $inner === 0));
            $code .= self::layout($space, $previous, $closes, count($arrays));
            $previous = $id;
            $space = '';

            if ($closes) {
                array_pop($arrays);
            } elseif ($id === self::OPEN_BRACKET) {
                $arrays[] = 0;
            } elseif ($inner !== false && $id === self::OPEN_PARENTHESIS) {
                array_pop($arrays);

                $arrays[] = $inner + 1;
            } elseif ($inner !== false && $id === self::CLOSE_PARENTHESIS) {
                array_pop($arrays);

                $arrays[] = $inner - 1;
            }

            $code .= match (true) {
                $keyword => '',
                $closes => ']',
                $id === self::OPEN_BRACKET => '[',
                default => $token->text,
            };

            if (count($arrays) > PrintRParser::MAX_DEPTH || strlen($code) > self::MAX_OUTPUT) {
                return null;
            }
        }

        return $code;
    }

    /**
     * Tokenizes the source as the operand of a `return` statement.
     *
     * Every token between `return` and the appended `;` must be one {@see self::TOKENS} lists. The statement must end
     * for the parse to succeed, and that list leaves out `;`, closing tags, and comments, so the appended `;` is the
     * last token and the only terminator: a `;` or closing tag of the text, or a trailing comment swallowing the
     * appended `;`, yields `null`. The text cannot smuggle further statements or inline output after the expression.
     * The error handler is restored on every path.
     *
     * @return array<PhpToken>|null Tokens of the expression alone, or `null` when the source is not exactly one export
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

        $tokens = array_slice($tokens, 3, -1);

        foreach ($tokens as $token) {
            if ($token->is(self::TOKENS) === false) {
                return null;
            }
        }

        return $tokens;
    }
}

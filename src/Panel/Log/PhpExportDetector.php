<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use PhpToken;

use function array_pop;
use function count;
use function end;
use function str_repeat;
use function strlen;
use function strpbrk;

use const T_ARRAY;
use const T_DOUBLE_ARROW;
use const T_RETURN;
use const T_WHITESPACE;

/**
 * Detects a log message that is the `var_export()` or `VarDumper::export()` output of an array or object and returns it
 * in short array syntax.
 *
 * Never evaluates the message and never changes string contents; only the array syntax and indentation are rewritten.
 */
final class PhpExportDetector
{
    /**
     * Indentation of one bracket level.
     */
    private const string INDENT = '    ';
    /**
     * Maximum size of the returned source, in bytes.
     */
    private const int MAX_OUTPUT = 4 * PrintRParser::MAX_BYTES;

    /**
     * Returns a PHP value export in short array syntax.
     *
     * @return string|null Export source, or `null` when the message is not one export expression within the bounds.
     */
    public static function detect(string $message): string|null
    {
        $tokens = PhpExportTokenizer::tokenize($message);

        return $tokens === null ? null : self::shortArrays($tokens);
    }

    /**
     * Lays out the whitespace between two tokens, re-indenting line breaks to the bracket depth.
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
            return $previous === PhpExportTokenizer::OPEN_BRACKET ? '' : "\n" . str_repeat(self::INDENT, $depth - 1);
        }

        return "\n" . str_repeat(self::INDENT, $depth);
    }

    /**
     * Rebuilds the expression with `array(...)` written as `[...]`, or returns `null` past a depth or size bound.
     *
     * @param array<PhpToken> $tokens Expression tokens in source order.
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

            $id = $keyword ? PhpExportTokenizer::OPEN_BRACKET : $token->id;

            $keyword = $id === T_ARRAY;

            $inner = end($arrays);

            $closes = $inner !== false
                && ($id === PhpExportTokenizer::CLOSE_BRACKET || ($id === PhpExportTokenizer::CLOSE_PARENTHESIS && $inner === 0));

            $code .= self::layout($space, $previous, $closes, count($arrays));

            $previous = $id;
            $space = '';

            if ($closes) {
                array_pop($arrays);
            } elseif ($id === PhpExportTokenizer::OPEN_BRACKET) {
                $arrays[] = 0;
            } elseif ($inner !== false && $id === PhpExportTokenizer::OPEN_PARENTHESIS) {
                array_pop($arrays);

                $arrays[] = $inner + 1;
            } elseif ($inner !== false && $id === PhpExportTokenizer::CLOSE_PARENTHESIS) {
                array_pop($arrays);

                $arrays[] = $inner - 1;
            }

            $code .= match (true) {
                $keyword => '',
                $closes => ']',
                $id === PhpExportTokenizer::OPEN_BRACKET => '[',
                default => $token->text,
            };

            if (count($arrays) > PrintRParser::MAX_DEPTH || strlen($code) > self::MAX_OUTPUT) {
                return null;
            }
        }

        return $code;
    }
}

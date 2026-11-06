<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use ParseError;
use PhpToken;

use function array_slice;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function strlen;
use function trim;

use const T_ARRAY;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DNUMBER;
use const T_DOUBLE_ARROW;
use const T_DOUBLE_COLON;
use const T_LNUMBER;
use const T_NAME_FULLY_QUALIFIED;
use const T_OBJECT_CAST;
use const T_STRING;
use const T_WHITESPACE;
use const TOKEN_PARSE;

/**
 * Tokenizes a log message that is the `var_export()` or `VarDumper::export()` output of an array or object.
 *
 * Never evaluates the message and returns `null` for anything but exactly one such export expression.
 */
final class PhpExportTokenizer
{
    /**
     * Token id of `]`; a single-character token uses its byte value as id.
     */
    public const int CLOSE_BRACKET = 93;
    /**
     * Token id of `)`.
     */
    public const int CLOSE_PARENTHESIS = 41;
    /**
     * Token id of `,`.
     */
    public const int COMMA = 44;
    /**
     * Token id of `.`.
     */
    public const int DOT = 46;
    /**
     * Token id of `-`.
     */
    public const int MINUS = 45;
    /**
     * Token id of `[`.
     */
    public const int OPEN_BRACKET = 91;
    /**
     * Token id of `(`.
     */
    public const int OPEN_PARENTHESIS = 40;
    /**
     * Leading text of a PHP value export.
     */
    private const string OPENER = '/^(?:array \(|\[|\(object\) array\(|\\\\[\w\\\\\x80-\xff]+::__set_state\()/';
    /**
     * Ids of the tokens an export may contain.
     */
    private const array TOKENS = [
        self::CLOSE_BRACKET,
        self::CLOSE_PARENTHESIS,
        self::OPEN_BRACKET,
        self::OPEN_PARENTHESIS,
        self::COMMA,
        self::MINUS,
        self::DOT,
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
     * Returns the tokens of a PHP value export.
     *
     * @return array<PhpToken>|null Expression tokens with whitespace, or `null` unless the message is one export
     * expression within {@see PrintRParser::MAX_BYTES} bytes.
     */
    public static function tokenize(string $message): array|null
    {
        if (strlen($message) > PrintRParser::MAX_BYTES) {
            return null;
        }

        $code = trim($message);

        if (preg_match(self::OPENER, $code) !== 1) {
            return null;
        }

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

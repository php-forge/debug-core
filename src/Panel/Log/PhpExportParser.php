<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use PhpToken;

use function array_filter;
use function array_values;
use function count;
use function in_array;
use function str_starts_with;
use function strtolower;
use function strtr;
use function substr;

use const T_ARRAY;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DNUMBER;
use const T_DOUBLE_ARROW;
use const T_DOUBLE_COLON;
use const T_LNUMBER;
use const T_NAME_FULLY_QUALIFIED;
use const T_OBJECT_CAST;
use const T_WHITESPACE;

/**
 * Parses the `var_export()` or `VarDumper::export()` output of an array or object into a typed {@see DumpNode} tree.
 *
 * Never evaluates the message; returns `null`, without throwing, for any unsupported form or an exceeded
 * {@see PrintRParser} bound.
 */
final class PhpExportParser
{
    /**
     * Only double-quoted literal an export prints: the NUL byte of a binary string.
     */
    private const string NUL = '"\0"';

    /**
     * Index of the next token to read.
     */
    private int $cursor = 0;
    /**
     * Token returned past the last one; it matches no token the parser expects.
     */
    private readonly PhpToken $end;
    /**
     * Number of values read so far, the root block included.
     */
    private int $nodes = 0;

    /**
     * @param list<PhpToken> $tokens Export tokens, whitespace removed.
     */
    private function __construct(private readonly array $tokens)
    {
        $this->end = new PhpToken(T_WHITESPACE, '');
    }

    /**
     * Parses a `var_export()` or `VarDumper::export()` value.
     *
     * @return DumpNode|null Root array or object block, or `null` for an unsupported message.
     */
    public static function parse(string $message): DumpNode|null
    {
        $tokens = PhpExportTokenizer::tokenize($message);

        if ($tokens === null) {
            return null;
        }

        $parser = new self(
            array_values(array_filter($tokens, static fn(PhpToken $token): bool => $token->id !== T_WHITESPACE)),
        );

        $root = $parser->value(1);

        return $parser->cursor === count($parser->tokens) ? $root : null;
    }

    /**
     * Consumes the current token when it has the given id.
     */
    private function accept(int $id): bool
    {
        if ($this->token()->id !== $id) {
            return false;
        }

        $this->cursor++;

        return true;
    }

    /**
     * Reads an `array(...)` or `[...]` array starting at the current token.
     *
     * @return list<DumpEntry>|null Entries in export order, or `null` when rejected.
     */
    private function block(int $depth): array|null
    {
        if ($depth > PrintRParser::MAX_DEPTH) {
            return null;
        }

        if ($this->accept(T_ARRAY)) {
            $this->cursor++;

            $close = PhpExportTokenizer::CLOSE_PARENTHESIS;
        } elseif ($this->accept(PhpExportTokenizer::OPEN_BRACKET)) {
            $close = PhpExportTokenizer::CLOSE_BRACKET;
        } else {
            return null;
        }

        $entries = [];
        $keyless = 0;

        while ($this->token()->id !== $close) {
            $key = $this->key();
            $value = $this->value($depth + 1);

            if ($value === null) {
                return null;
            }

            $entries[] = new DumpEntry($key ?? (string) $keyless++, $value);

            if ($this->accept(PhpExportTokenizer::COMMA) === false) {
                break;
            }
        }

        if ($this->accept($close) === false || ($keyless !== 0 && $keyless !== count($entries))) {
            return null;
        }

        return $entries;
    }

    /**
     * Decodes a single-quoted string or the `"\0"` literal, or returns `null` for any other token.
     */
    private static function decode(PhpToken $token): string|null
    {
        if ($token->text === self::NUL) {
            return "\0";
        }

        return str_starts_with($token->text, "'")
            ? strtr(substr($token->text, 1, -1), ['\\\\' => '\\', "\\'" => "'"])
            : null;
    }

    /**
     * Reads a string or integer key and its `=>`, or returns `null` for a keyless entry.
     */
    private function key(): string|null
    {
        $start = $this->cursor;
        $key = $this->scalar();

        if (
            in_array($key?->kind, [DumpKind::STRING, DumpKind::INTEGER], true)
            && $this->accept(T_DOUBLE_ARROW)
        ) {
            return $key->text;
        }

        $this->cursor = $start;

        return null;
    }

    /**
     * Returns the current token and moves past it.
     */
    private function next(): PhpToken
    {
        $token = $this->token();

        $this->cursor++;

        return $token;
    }

    /**
     * Builds an array or object block from the entries {@see self::block()} read.
     *
     * @param list<DumpEntry>|null $entries Entries, or `null` when rejected.
     */
    private static function node(DumpKind $kind, string $label, array|null $entries): DumpNode|null
    {
        return $entries === null ? null : DumpNode::block($kind, $label, $entries);
    }

    /**
     * Reads a `__set_state()` object or an enum case reference starting at its class name.
     */
    private function object(int $depth): DumpNode|null
    {
        $class = substr($this->next()->text, 1);

        if ($this->accept(T_DOUBLE_COLON) === false) {
            return null;
        }

        $name = $this->next()->text;

        if ($this->accept(PhpExportTokenizer::OPEN_PARENTHESIS) === false) {
            return DumpNode::enumCase($class, $name);
        }

        if ($name !== '__set_state') {
            return null;
        }

        $entries = $this->block($depth);

        return self::node(
            DumpKind::OBJECT,
            $class,
            $this->accept(PhpExportTokenizer::CLOSE_PARENTHESIS) ? $entries : null,
        );
    }

    /**
     * Reads a string, a number, a boolean, or `null` starting at the current token.
     */
    private function scalar(): DumpNode|null
    {
        if ($this->token()->id === T_CONSTANT_ENCAPSED_STRING) {
            return $this->string();
        }

        $negative = $this->accept(PhpExportTokenizer::MINUS);
        $token = $this->next();
        $text = $negative ? "-{$token->text}" : $token->text;

        if ($token->id === T_LNUMBER) {
            if ($negative && $this->token()->id === PhpExportTokenizer::MINUS && $this->token(1)->text === '1') {
                $this->cursor += 2;
                $text .= '-1';
            }

            return DumpNode::integer($text);
        }

        if ($token->id === T_DNUMBER || $token->text === 'INF' || ($negative === false && $token->text === 'NAN')) {
            return DumpNode::float($text);
        }

        return match ($negative ? '' : strtolower($text)) {
            'false', 'true' => DumpNode::boolean($text),
            'null' => DumpNode::null($text),
            default => null,
        };
    }

    /**
     * Reads a string literal and the literals joined to it by `.`, decoding their values.
     */
    private function string(): DumpNode|null
    {
        $value = '';

        do {
            $part = self::decode($this->next());

            if ($part === null) {
                return null;
            }

            $value .= $part;
        } while ($this->accept(PhpExportTokenizer::DOT));

        return DumpNode::string($value);
    }

    /**
     * Returns the token at the given offset from the cursor, or {@see self::$end} past the last one.
     */
    private function token(int $offset = 0): PhpToken
    {
        return $this->tokens[$this->cursor + $offset] ?? $this->end;
    }

    /**
     * Reads one value, counting it against {@see PrintRParser::MAX_NODES}.
     */
    private function value(int $depth): DumpNode|null
    {
        if (++$this->nodes > PrintRParser::MAX_NODES) {
            return null;
        }

        if ($this->accept(T_OBJECT_CAST)) {
            return self::node(DumpKind::OBJECT, 'stdClass', $this->block($depth));
        }

        return match ($this->token()->id) {
            T_ARRAY, PhpExportTokenizer::OPEN_BRACKET => self::node(DumpKind::ARRAY, '', $this->block($depth)),
            T_NAME_FULLY_QUALIFIED => $this->object($depth),
            default => $this->scalar(),
        };
    }
}

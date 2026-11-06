<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use stdClass;

use function count;
use function explode;
use function preg_match;
use function rtrim;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

use const PREG_UNMATCHED_AS_NULL;

/**
 * Parses the output of `print_r($value, true)` for an array, object, or enum into a {@see PrintRNode} tree.
 *
 * The parser follows the layout `print_r()` emits: a header line (`Array`, `<class> Object`, `<class> Enum`, or
 * `<class> Enum:<type>`), a `(` line whose indentation plus four spaces is the indentation of the `[key] => value`
 * entries, and a `)` line at the indentation of `(`. Nested blocks are followed by one blank line, recursive values by a
 * ` *RECURSION*` line, and the lines of a multi-line string continue until the next entry or the closing `)` of the
 * block. Only the root header may carry free text before it, such as `Request: Array`; only trailing whitespace may
 * follow the root block.
 *
 * Any other text, a structural mismatch, or a dump beyond {@see self::MAX_BYTES} bytes, {@see self::MAX_DEPTH} nested
 * blocks, or {@see self::MAX_NODES} values yields `null`; the parser never throws.
 *
 * Usage example:
 * ```php
 * $node = PrintRParser::parse("Request: Array\n(\n    [id] => 7\n)\n");
 * // $node->kind === PrintRKind::ARRAY, $node->text === 'Request:', $node->entries[0]->key === 'id'
 * ```
 */
final class PrintRParser
{
    /**
     * Maximum size of a parsed message, in bytes; {@see PhpExportDetector} applies the same bound.
     */
    public const int MAX_BYTES = 262144;
    /**
     * Maximum nesting level of blocks, the root block included; {@see PhpExportDetector} applies the same bound to
     * nested arrays.
     */
    public const int MAX_DEPTH = 32;
    /**
     * Separator between the key and the value of an entry line.
     */
    private const string ARROW = '] => ';
    /**
     * Header of an array or object block, with the optional free text that may precede the root header.
     */
    private const string HEADER = '/^(.*\s)?(?:Array|(\S+) (Object|Enum)(?::int|:string)?)$/';
    /**
     * Maximum number of values, the root block included.
     */
    private const int MAX_NODES = 5000;
    /**
     * Line `print_r()` prints after the header of an array or object it is already printing.
     */
    private const string RECURSION = ' *RECURSION*';
    /**
     * Property key of an object or enum block: name and either `protected` or the declaring class of a private property.
     */
    private const string VISIBILITY = '/^([^:]*):(?:(protected)|(.+):private)$/';

    /**
     * Index of the next line to read.
     */
    private int $cursor = 1;
    /**
     * Number of values read so far, the root block included.
     */
    private int $nodes = 1;

    /**
     * @param list<string> $lines Lines of the dump, trailing whitespace removed.
     */
    private function __construct(private readonly array $lines) {}

    /**
     * Parses a `print_r()` dump of an array, object, or enum.
     *
     * @param string $message Log message to parse.
     *
     * @return PrintRNode|null Root block, with the free text preceding its header as {@see PrintRNode::$text}, or
     * `null` when the message is not a complete dump within the bounds.
     */
    public static function parse(string $message): PrintRNode|null
    {
        if (strlen($message) > self::MAX_BYTES) {
            return null;
        }

        $lines = explode("\n", rtrim($message));
        $header = self::header($lines[0]);

        if ($header === null) {
            return null;
        }

        [$prefix, $kind, $label] = $header;

        $parser = new self($lines);
        $entries = $parser->opens(0) ? $parser->block(0, self::hasVisibility($kind, $label), 1) : null;

        return $entries === null || $parser->cursor !== count($lines)
            ? null
            : PrintRNode::block($kind, $label, $entries, $prefix);
    }

    /**
     * Reads the entries of the block whose `(` line is the current line, up to and including its `)` line.
     *
     * @param int $indent Indentation of the `(` and `)` lines.
     * @param bool $visibility Whether the keys are property names that may carry a visibility.
     * @param int $depth Nesting level of the block, the root block being `1`.
     *
     * @return list<PrintREntry>|null Entries in dump order, or `null` on a structural mismatch or an exceeded bound.
     */
    private function block(int $indent, bool $visibility, int $depth): array|null
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }

        $this->cursor++;

        $close = str_repeat(' ', $indent) . ')';
        $start = str_repeat(' ', $indent + 4) . '[';

        $entries = [];

        while (($line = $this->lines[$this->cursor] ?? '') !== $close) {
            $entry = self::split($line, $start);

            if ($entry === null || ++$this->nodes > self::MAX_NODES) {
                return null;
            }

            $this->cursor++;

            $value = $this->value($entry[1], $indent + 8, $close, $start, $depth);

            if ($value === null) {
                return null;
            }

            $entries[] = self::entry($entry[0], $value, $visibility);
        }

        $this->cursor++;

        return $entries;
    }

    /**
     * Builds an entry, splitting the visibility out of a property key when the block's keys may carry one.
     */
    private static function entry(string $key, PrintRNode $value, bool $visibility): PrintREntry
    {
        if ($visibility && preg_match(self::VISIBILITY, $key, $matches, PREG_UNMATCHED_AS_NULL) === 1) {
            return $matches[2] === null
                ? new PrintREntry($matches[1], $value, 'private', (string) $matches[3])
                : new PrintREntry($matches[1], $value, 'protected');
        }

        return new PrintREntry($key, $value);
    }

    /**
     * Whether the keys of a block are property names to which `print_r()` appends a visibility.
     *
     * Array keys never carry one. A `stdClass` object declares no property, so every key is a dynamic property name
     * kept verbatim, even one such as `status:protected`.
     */
    private static function hasVisibility(PrintRKind $kind, string $label): bool
    {
        return $kind !== PrintRKind::ARRAY && $label !== stdClass::class;
    }

    /**
     * Reads a block header.
     *
     * @return array{string, PrintRKind, string}|null Free text preceding the header (right-trimmed), block kind, and
     * class name, or `null` when the text is not a header.
     */
    private static function header(string $text): array|null
    {
        if (preg_match(self::HEADER, $text, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        $kind = match ($matches[3]) {
            null => PrintRKind::ARRAY,
            'Enum' => PrintRKind::ENUM,
            default => PrintRKind::OBJECT,
        };

        return [rtrim((string) $matches[1]), $kind, (string) $matches[2]];
    }

    /**
     * Whether the current line opens a block at the given indentation.
     */
    private function opens(int $indent): bool
    {
        return ($this->lines[$this->cursor] ?? '') === str_repeat(' ', $indent) . '(';
    }

    /**
     * Splits an entry line at the given indentation into its key and the first line of its value.
     *
     * @return array{string, string}|null Key and value text, or `null` when the line is not an entry line.
     */
    private static function split(string $line, string $start): array|null
    {
        $arrow = strpos($line, self::ARROW);

        if ($arrow === false || str_starts_with($line, $start) === false) {
            return null;
        }

        $offset = strlen($start);

        return [substr($line, $offset, $arrow - $offset), substr($line, $arrow + 5)];
    }

    /**
     * Reads the value of an entry whose line has just been consumed.
     *
     * A header followed by the recursion marker or by a `(` line at the child indentation opens a nested value; any
     * other text is a scalar that absorbs the following lines up to the next entry or the closing line of the block.
     *
     * @param string $text Value text on the entry line.
     * @param int $indent Indentation of a nested block's `(` line.
     * @param string $close Closing line of the enclosing block.
     * @param string $start Leading text of an entry line of the enclosing block.
     * @param int $depth Nesting level of the enclosing block.
     *
     * @return PrintRNode|null Value node, or `null` when a nested block is malformed or exceeds a bound.
     */
    private function value(string $text, int $indent, string $close, string $start, int $depth): PrintRNode|null
    {
        $header = self::header($text);

        if ($header !== null && $header[0] === '') {
            if (($this->lines[$this->cursor] ?? '') === self::RECURSION) {
                $this->cursor++;

                return PrintRNode::recursion($header[2]);
            }

            if ($this->opens($indent)) {
                $entries = $this->block($indent, self::hasVisibility($header[1], $header[2]), $depth + 1);

                if ($entries === null || ($this->lines[$this->cursor] ?? '') !== '') {
                    return null;
                }

                $this->cursor++;

                return PrintRNode::block($header[1], $header[2], $entries);
            }
        }

        while (($line = $this->lines[$this->cursor] ?? $close) !== $close && self::split($line, $start) === null) {
            $text .= "\n{$line}";
            $this->cursor++;
        }

        return PrintRNode::scalar($text);
    }
}

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
 * Parses the output of `print_r($value, true)` for an array, object, or enum into a {@see DumpNode} tree.
 *
 * Only the root header may carry free text before it, such as `Request: Array`. Any other deviation from the
 * `print_r()` layout, or an exceeded bound, yields `null`; the parser never throws.
 */
final class PrintRParser
{
    /**
     * Maximum size of a parsed message, in bytes, shared with {@see PhpExportTokenizer}.
     */
    public const int MAX_BYTES = 262144;
    /**
     * Maximum nesting level of blocks, the root block included, shared with the PHP value export classes.
     */
    public const int MAX_DEPTH = 32;
    /**
     * Maximum number of values, the root block included, shared with {@see PhpExportParser}.
     */
    public const int MAX_NODES = 5000;
    /**
     * Separator between the key and the value of an entry line.
     */
    private const string ARROW = '] => ';
    /**
     * Block header, with the free text that may precede the root header.
     */
    private const string HEADER = '/^(.*\s)?(?:Array|(\S+) (Object|Enum)(?::int|:string)?)$/';
    /**
     * Line `print_r()` prints after the header of an array or object it is already printing.
     */
    private const string RECURSION = ' *RECURSION*';
    /**
     * Property key carrying `protected` or the declaring class of a private property.
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
     * @param list<string> $lines Dump lines, trailing whitespace removed.
     */
    private function __construct(private readonly array $lines) {}

    /**
     * Parses a `print_r()` dump of an array, object, or enum.
     *
     * @return DumpNode|null Root block, with the free text preceding its header as {@see DumpNode::$text}, or `null`
     * for an unsupported message.
     */
    public static function parse(string $message): DumpNode|null
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
            : DumpNode::block($kind, $label, $entries, $prefix);
    }

    /**
     * Reads the entries of the block whose `(` line is the current line, up to and including its `)` line.
     *
     * @return list<DumpEntry>|null Entries in dump order, or `null` on a mismatch or an exceeded bound.
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
    private static function entry(string $key, DumpNode $value, bool $visibility): DumpEntry
    {
        if ($visibility && preg_match(self::VISIBILITY, $key, $matches, PREG_UNMATCHED_AS_NULL) === 1) {
            return $matches[2] === null
                ? new DumpEntry($matches[1], $value, 'private', (string) $matches[3])
                : new DumpEntry($matches[1], $value, 'protected');
        }

        return new DumpEntry($key, $value);
    }

    /**
     * Whether the keys of a block may carry a visibility; array and `stdClass` keys never do.
     */
    private static function hasVisibility(DumpKind $kind, string $label): bool
    {
        return $kind !== DumpKind::ARRAY && $label !== stdClass::class;
    }

    /**
     * Reads a block header.
     *
     * @return array{string, DumpKind, string}|null Prefix, kind, and class name, or `null` for a non-header text.
     */
    private static function header(string $text): array|null
    {
        if (preg_match(self::HEADER, $text, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
            return null;
        }

        $kind = match ($matches[3]) {
            null => DumpKind::ARRAY,
            'Enum' => DumpKind::ENUM,
            default => DumpKind::OBJECT,
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
     * Splits an entry line into its key and the first line of its value.
     *
     * @return array{string, string}|null Key and value text, or `null` for a non-entry line.
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
     * Reads the nested block, recursion marker, or multi-line scalar of the entry whose line was just consumed.
     */
    private function value(string $text, int $indent, string $close, string $start, int $depth): DumpNode|null
    {
        $header = self::header($text);

        if ($header !== null && $header[0] === '') {
            if (($this->lines[$this->cursor] ?? '') === self::RECURSION) {
                $this->cursor++;

                return DumpNode::recursion($header[2]);
            }

            if ($this->opens($indent)) {
                $entries = $this->block($indent, self::hasVisibility($header[1], $header[2]), $depth + 1);

                if ($entries === null || ($this->lines[$this->cursor] ?? '') !== '') {
                    return null;
                }

                $this->cursor++;

                return DumpNode::block($header[1], $header[2], $entries);
            }
        }

        while (($line = $this->lines[$this->cursor] ?? $close) !== $close && self::split($line, $start) === null) {
            $text .= "\n{$line}";
            $this->cursor++;
        }

        return DumpNode::scalar($text);
    }
}

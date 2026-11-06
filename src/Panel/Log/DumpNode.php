<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use PHPForge\Debug\Helper\Dump;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function var_export;

/**
 * Represents one value of a dump tree: a scalar, an enum case reference, a recursion marker, or a block with entries.
 *
 * {@see PrintRParser} and {@see PhpExportParser} build trees from log messages; {@see DumpTreeRenderer} renders them.
 */
final readonly class DumpNode
{
    /**
     * @param string $label Class name of an object, enum, or recursive object; `''` otherwise.
     * @param string $text Scalar text, enum case name, or free text preceding a root block header.
     * @param list<DumpEntry> $entries Block entries.
     */
    private function __construct(
        public DumpKind $kind,
        public string $label,
        public string $text,
        public array $entries,
    ) {}

    /**
     * Creates an array, object, or enum block.
     *
     * @param string $label Class name of an object or enum; `''` for an array.
     * @param list<DumpEntry> $entries Entries in dump order.
     * @param string $prefix Free text preceding a root block header, such as `Request:`.
     */
    public static function block(DumpKind $kind, string $label, array $entries, string $prefix = ''): self
    {
        return new self($kind, $label, $prefix, $entries);
    }

    /**
     * Creates a boolean from its printed text.
     */
    public static function boolean(string $text): self
    {
        return new self(DumpKind::BOOLEAN, '', $text, []);
    }

    /**
     * Creates the enum case reference that `var_export()` prints as `\Class::Case`.
     *
     * @param string $class Enum class name, without the leading backslash.
     */
    public static function enumCase(string $class, string $case): self
    {
        return new self(DumpKind::ENUM, $class, $case, []);
    }

    /**
     * Creates a float from its printed text.
     */
    public static function float(string $text): self
    {
        return new self(DumpKind::FLOAT, '', $text, []);
    }

    /**
     * Creates the tree of a context value normalized by {@see LogContext::normalize()}.
     *
     * A value that is neither an array, a scalar, nor `null` becomes an untyped scalar.
     *
     * @return self|null Tree, or `null` over the {@see PrintRParser} depth or value bound.
     */
    public static function fromValue(mixed $value): self|null
    {
        $nodes = 0;

        return self::fromNormalized($value, 1, $nodes);
    }

    /**
     * Creates an integer from its printed text.
     */
    public static function integer(string $text): self
    {
        return new self(DumpKind::INTEGER, '', $text, []);
    }

    /**
     * Creates a `null` from its printed text.
     */
    public static function null(string $text): self
    {
        return new self(DumpKind::NULL, '', $text, []);
    }

    /**
     * Creates the marker `print_r()` prints instead of an array or object it is already printing.
     *
     * @param string $label Class name of a recursive object; `''` for a recursive array.
     */
    public static function recursion(string $label): self
    {
        return new self(DumpKind::RECURSION, $label, '', []);
    }

    /**
     * Creates an untyped `print_r()` scalar.
     *
     * @param string $text Text as printed, continuation lines included.
     */
    public static function scalar(string $text): self
    {
        return new self(DumpKind::SCALAR, '', $text, []);
    }

    /**
     * Creates a string from its decoded value.
     */
    public static function string(string $value): self
    {
        return new self(DumpKind::STRING, '', $value, []);
    }

    /**
     * Builds one value of {@see self::fromValue()}, counting it in `$nodes`.
     */
    private static function fromNormalized(mixed $value, int $depth, int &$nodes): self|null
    {
        if (++$nodes > PrintRParser::MAX_NODES) {
            return null;
        }

        if (is_array($value) === false) {
            return match (true) {
                is_string($value) => self::string($value),
                is_int($value) => self::integer(var_export($value, true)),
                is_float($value) => self::float(var_export($value, true)),
                is_bool($value) => self::boolean(var_export($value, true)),
                $value === null => self::null('NULL'),
                default => self::scalar(Dump::asString($value)),
            };
        }

        if ($depth > PrintRParser::MAX_DEPTH) {
            return null;
        }

        $entries = [];

        foreach ($value as $key => $item) {
            $node = self::fromNormalized($item, $depth + 1, $nodes);

            if ($node === null) {
                return null;
            }

            $entries[] = new DumpEntry((string) $key, $node);
        }

        return self::block(DumpKind::ARRAY, '', $entries);
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

/**
 * Represents one value of a parsed `print_r()` dump: a scalar text, a recursion marker, or an array, object, or enum
 * block with its entries.
 *
 * Usage example:
 * ```php
 * $node = PrintRParser::parse(print_r($request, true));
 *
 * if ($node !== null) {
 *     echo PrintRTreeRenderer::render($node);
 * }
 * ```
 */
final readonly class PrintRNode
{
    /**
     * @param PrintRKind $kind Kind of value.
     * @param string $label Class name of an object, enum, or recursive object; `''` otherwise.
     * @param string $text Scalar text, or the free text preceding the header of a root block.
     * @param list<PrintREntry> $entries Entries of an array, object, or enum block.
     */
    private function __construct(
        public PrintRKind $kind,
        public string $label,
        public string $text,
        public array $entries,
    ) {}

    /**
     * Creates an array, object, or enum block.
     *
     * @param PrintRKind $kind Block kind: {@see PrintRKind::ARRAY}, {@see PrintRKind::ENUM}, or
     * {@see PrintRKind::OBJECT}.
     * @param string $label Class name of an object or enum; `''` for an array.
     * @param list<PrintREntry> $entries Entries in dump order.
     * @param string $prefix Free text preceding the header of a root block, such as `Request:`.
     *
     * @return self Block node.
     */
    public static function block(PrintRKind $kind, string $label, array $entries, string $prefix = ''): self
    {
        return new self($kind, $label, $prefix, $entries);
    }

    /**
     * Creates the marker `print_r()` prints instead of an array or object it is already printing.
     *
     * @param string $label Class name of a recursive object; `''` for a recursive array.
     *
     * @return self Recursion node.
     */
    public static function recursion(string $label): self
    {
        return new self(PrintRKind::RECURSION, $label, '', []);
    }

    /**
     * Creates a scalar value.
     *
     * @param string $text Scalar text as printed, continuation lines of a multi-line string included.
     *
     * @return self Scalar node.
     */
    public static function scalar(string $text): self
    {
        return new self(PrintRKind::SCALAR, '', $text, []);
    }
}

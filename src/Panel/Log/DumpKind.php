<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

/**
 * Represents the kinds of value a dump tree distinguishes.
 *
 * A `print_r()` dump yields only blocks, recursion markers, and untyped {@see self::SCALAR} text.
 */
enum DumpKind
{
    /**
     * Array block.
     */
    case ARRAY;

    /**
     * Boolean, with its text as printed.
     */
    case BOOLEAN;

    /**
     * Enum block, or a `var_export()` case reference with the case name as text and no entries.
     */
    case ENUM;

    /**
     * Float, with its text as printed.
     */
    case FLOAT;

    /**
     * Integer, with its text as printed.
     */
    case INTEGER;

    /**
     * `null`, with its text as printed.
     */
    case NULL;

    /**
     * Object block.
     */
    case OBJECT;

    /**
     * Recursion marker `print_r()` prints instead of an array or object block.
     */
    case RECURSION;

    /**
     * Untyped `print_r()` text, empty for `''`, `null`, and `false`.
     */
    case SCALAR;

    /**
     * String, with its decoded value as text.
     */
    case STRING;
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

/**
 * Represents the kinds of value a `print_r()` dump distinguishes.
 */
enum PrintRKind
{
    /**
     * Array block opened by an `Array` header.
     */
    case ARRAY;

    /**
     * Enum case block opened by a `<class> Enum` header, optionally suffixed with its backing type.
     */
    case ENUM;

    /**
     * Object block opened by a `<class> Object` header.
     */
    case OBJECT;

    /**
     * Array or object header followed by the `*RECURSION*` marker instead of a block.
     */
    case RECURSION;

    /**
     * Scalar text: a string, a number, a resource label, or the empty output of `''`, `null`, and `false`.
     */
    case SCALAR;
}

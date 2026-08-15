<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use function base64_encode;
use function mb_check_encoding;
use function sprintf;

/**
 * Converts strings to JSON-safe debug storage representations.
 */
final class Json
{
    /**
     * Prevents instantiation of this static helper.
     */
    private function __construct() {}

    /**
     * Returns valid UTF-8, representing binary text as base64.
     *
     * Usage example:
     *
     * ```php
     * $label = \PHPForge\Debug\Storage\Json::safeString("\xB1\x31");
     * ```
     *
     * @param string $value String to normalize.
     *
     * @return string JSON-safe string.
     */
    public static function safeString(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : sprintf('(binary: base64 %s)', base64_encode($value));
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function count;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function rtrim;
use function sprintf;
use function strlen;

/**
 * Formats values and type labels for display in debug-panel views and toolbar chips.
 */
final class Format
{
    private const int BYTES_PER_MB = 1024 * 1024;

    /**
     * Returns a `N.NN MB` string for the given byte count, rounded to the requested precision.
     *
     * @param float|int $bytes Byte count to format.
     * @param int $precision Number of decimal places.
     *
     * @return string Megabyte readout.
     */
    public static function bytesToMb(float|int $bytes, int $precision = 2): string
    {
        return sprintf("%.{$precision}f MB", $bytes / self::BYTES_PER_MB);
    }

    /**
     * Returns a CSS percentage (`42%`, `33.333%`) with at most three decimals and trailing zeros trimmed.
     *
     * @param float $value Percentage value to format.
     *
     * @return string CSS percentage.
     */
    public static function cssPercent(float $value): string
    {
        $rendered = sprintf('%.3f', $value);
        $rendered = rtrim($rendered, '0');
        $rendered = rtrim($rendered, '.');

        return "{$rendered}%";
    }

    /**
     * Returns the display label of a value's type, with the element count for arrays and the byte length for strings.
     *
     * @param mixed $value JSON-safe value to describe.
     *
     * @return string Type label such as `array(3)`, `string(16)`, `int`, `float`, `bool`, or `null`.
     */
    public static function typeOf(mixed $value): string
    {
        return match (true) {
            is_array($value) => 'array(' . count($value) . ')',
            is_string($value) => 'string(' . strlen($value) . ')',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_bool($value) => 'bool',
            $value === null => 'null',
            default => gettype($value),
        };
    }
}

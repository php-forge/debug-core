<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function rtrim;
use function sprintf;

/**
 * Formats numeric values for display in debug-panel views and toolbar chips.
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
}

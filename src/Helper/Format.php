<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function count;
use function date;
use function gettype;
use function intdiv;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function number_format;
use function rtrim;
use function sprintf;
use function strlen;

/**
 * Formats values, timestamps, and type labels for display in debug-panel views and toolbar chips.
 */
final class Format
{
    /**
     * Bytes in one mebibyte, the unit of the `bytesToMb()` readout.
     */
    public const int BYTES_PER_MB = 1024 * 1024;
    /**
     * Milliseconds in one second, used to scale second-based durations.
     */
    public const int MILLISECONDS_PER_SECOND = 1000;
    /**
     * Relative-time threshold: ages of one day or more render as `X d ago`.
     */
    private const int SECONDS_PER_DAY = 86400;
    /**
     * Relative-time threshold: ages of one hour or more render as `X h ago`.
     */
    private const int SECONDS_PER_HOUR = 3600;
    /**
     * Relative-time threshold: ages of one minute or more render as `X min ago`.
     */
    private const int SECONDS_PER_MINUTE = 60;
    /**
     * Relative-time upper bound: ages of 30 days or more fall back to the absolute label.
     */
    private const int SECONDS_PER_MONTH = 2592000;

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
     * Returns a `N ms` readout for the given duration in seconds, grouped in thousands.
     *
     * @param float $seconds Duration in seconds.
     * @param int $decimals Number of decimal places.
     *
     * @return string Millisecond readout.
     */
    public static function milliseconds(float $seconds, int $decimals = 0): string
    {
        return number_format($seconds * self::MILLISECONDS_PER_SECOND, $decimals) . ' ms';
    }

    /**
     * Returns a coarse age label for the given elapsed seconds: `just now` under a minute, then `X min ago`, `X h ago`,
     * and `X d ago`. Ages of 30 days or more fall back to `$fallback`.
     *
     * The caller supplies the elapsed seconds so the clock source stays at the call site.
     *
     * @param int $elapsedSeconds Age in seconds, typically `time() - $capturedAt`.
     * @param string $fallback Absolute label used past the 30-day threshold.
     *
     * @return string Age label, or `$fallback` when the age reaches 30 days.
     */
    public static function relativeTime(int $elapsedSeconds, string $fallback): string
    {
        return match (true) {
            $elapsedSeconds < self::SECONDS_PER_MINUTE => 'just now',
            $elapsedSeconds < self::SECONDS_PER_HOUR => intdiv($elapsedSeconds, self::SECONDS_PER_MINUTE) . ' min ago',
            $elapsedSeconds < self::SECONDS_PER_DAY => intdiv($elapsedSeconds, self::SECONDS_PER_HOUR) . ' h ago',
            $elapsedSeconds < self::SECONDS_PER_MONTH => intdiv($elapsedSeconds, self::SECONDS_PER_DAY) . ' d ago',
            default => $fallback,
        };
    }

    /**
     * Returns the wall-clock readout of the given epoch milliseconds, suffixed with the millisecond fraction
     * (`H:i:s.mmm`).
     *
     * Negative timestamps use floor division, so the fraction stays in the `000`-`999` range and the seconds part
     * names the wall-clock second that contains the instant.
     *
     * @param int $epochMilliseconds Unix timestamp in milliseconds.
     * @param string $format `date()` format for the second-precision part, without the fraction separator.
     *
     * @return string Formatted timestamp followed by `.mmm`.
     */
    public static function timeOfDay(int $epochMilliseconds, string $format = 'H:i:s'): string
    {
        $seconds = intdiv($epochMilliseconds, self::MILLISECONDS_PER_SECOND);

        $fraction = $epochMilliseconds % self::MILLISECONDS_PER_SECOND;

        if ($fraction < 0) {
            --$seconds;
            $fraction += self::MILLISECONDS_PER_SECOND;
        }

        return date("{$format}.", $seconds) . sprintf('%03d', $fraction);
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

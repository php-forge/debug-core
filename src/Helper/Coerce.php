<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use Stringable;

use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;

/**
 * Narrows arbitrary mixed payloads into the typed scalars debug-panel renderers expect.
 */
final class Coerce
{
    /**
     * Returns a numeric value as a float or the supplied default.
     *
     * @param mixed $value Value to narrow.
     * @param float $default Value returned for non-numeric input.
     *
     * @return float Numeric value or the configured default.
     */
    public static function float(mixed $value, float $default = 0.0): float
    {
        return self::floatOrNull($value) ?? $default;
    }

    /**
     * Returns the value as a float when it is numeric, `null` otherwise.
     *
     * @param mixed $value Value to narrow.
     *
     * @return float|null Numeric value or `null`.
     */
    public static function floatOrNull(mixed $value): float|null
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Returns a numeric value as an int or the supplied default.
     *
     * @param mixed $value Value to narrow.
     * @param int $default Value returned for non-numeric input.
     *
     * @return int Numeric value or the configured default.
     */
    public static function int(mixed $value, int $default = 0): int
    {
        return self::intOrNull($value) ?? $default;
    }

    /**
     * Returns the value as an int when it is an integer or numeric, `null` otherwise.
     *
     * @param mixed $value Value to narrow.
     *
     * @return int|null Numeric value or `null`.
     */
    public static function intOrNull(mixed $value): int|null
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Returns a string value or the supplied default.
     *
     * @param mixed $value Value to narrow.
     * @param string $default Value returned for non-string input.
     *
     * @return string String value or the configured default.
     */
    public static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : $default;
    }

    /**
     * Returns only the entries of `$data` whose key is a string, preserving order.
     *
     * Narrows a mixed/`array<array-key, mixed>` snapshot down to the `array<string, mixed>` shape downstream view-model
     * normalizers expect.
     *
     * @param array<array-key, mixed> $data Source array with arbitrary keys.
     *
     * @return array<string, mixed> Entries whose key was already a string, in original order.
     */
    public static function stringKeyedArray(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Returns only the string entries of a raw list, preserving order.
     *
     * @param mixed $values Raw list, typically a user-configured category list.
     *
     * @return list<string> String entries in original order, possibly empty.
     */
    public static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * Returns the value as a string when it is scalar or {@see Stringable}, `null` otherwise.
     *
     * @param mixed $value Value to narrow.
     *
     * @return string|null String representation or `null`.
     */
    public static function stringOrNull(mixed $value): string|null
    {
        if (is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Narrows a raw trace value (as captured by Yii's logger) into the `list<array<string, mixed>>` shape every panel
     * renderer consumes.
     *
     * @param mixed $value Raw trace payload.
     *
     * @return list<array<string, mixed>> Trace frames normalized to string-keyed maps.
     */
    public static function traceFrames(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $frames = [];

        foreach ($value as $frame) {
            if (!is_array($frame)) {
                continue;
            }

            $frames[] = self::stringKeyedArray($frame);
        }

        return $frames;
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Data;

use function is_array;
use function is_finite;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;

/**
 * Reads `Prefix[attribute]` filter groups and scalar values from a parsed query-parameter array.
 */
final class QueryInput
{
    /**
     * Returns the active `Prefix[attribute]` filter map from the query parameters.
     *
     * @param array<array-key, mixed> $query Parsed query parameters.
     * @param string $prefix Filter-group prefix (for example, `Debug` matches `Debug[statusCode]`).
     *
     * @return array<string, string> Attribute-to-value map with empty and non-scalar entries removed.
     */
    public static function group(array $query, string $prefix): array
    {
        $group = $query[$prefix] ?? null;

        if (!is_array($group)) {
            return [];
        }

        $filters = [];

        foreach ($group as $attribute => $value) {
            if (!is_string($attribute)) {
                continue;
            }

            $normalized = self::stringValue($value);

            if ($normalized === null || $normalized === '') {
                continue;
            }

            $filters[$attribute] = $normalized;
        }

        return $filters;
    }

    /**
     * Returns a submitted lower bound as a finite, non-negative number, or `null` when the value is unusable.
     *
     * Empty, non-numeric, negative, and overflowing values are rejected, so callers can clear the stored filter
     * whenever `null` comes back.
     *
     * @param string $value Raw submitted bound.
     */
    public static function minimumBound(string $value): float|null
    {
        if (!is_numeric($value)) {
            return null;
        }

        $bound = (float) $value;

        return is_finite($bound) && $bound >= 0.0 ? $bound : null;
    }

    /**
     * Returns a top-level query parameter as a string, or `null` when absent or non-scalar.
     *
     * @param array<array-key, mixed> $query Parsed query parameters.
     * @param string $name Parameter name to read.
     */
    public static function scalar(array $query, string $name): string|null
    {
        return self::stringValue($query[$name] ?? null);
    }

    private static function stringValue(mixed $value): string|null
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}

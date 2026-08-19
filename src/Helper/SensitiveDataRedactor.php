<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function array_fill_keys;
use function array_map;
use function is_array;
use function is_string;
use function strtolower;

/**
 * Replaces values whose array keys match an explicitly configured sensitive-key list.
 */
final class SensitiveDataRedactor
{
    public const string PLACEHOLDER = '[redacted]';

    /**
     * Redacts configured keys case-insensitively throughout a nested array.
     *
     * @param array<array-key, mixed> $value Value tree to sanitize.
     * @param list<string> $sensitiveKeys Exact key names to redact.
     *
     * @return array<array-key, mixed> Sanitized tree with keys and non-sensitive values preserved.
     */
    public static function redact(array $value, array $sensitiveKeys): array
    {
        $keys = array_fill_keys(array_map(strtolower(...), $sensitiveKeys), true);

        return self::walk($value, $keys);
    }

    /**
     * @param array<array-key, mixed> $value
     * @param array<string, true> $sensitiveKeys
     *
     * @return array<array-key, mixed>
     */
    private static function walk(array $value, array $sensitiveKeys): array
    {
        $redacted = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && isset($sensitiveKeys[strtolower($key)])) {
                $redacted[$key] = self::PLACEHOLDER;

                continue;
            }

            $redacted[$key] = is_array($item) ? self::walk($item, $sensitiveKeys) : $item;
        }

        return $redacted;
    }
}

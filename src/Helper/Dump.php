<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function addslashes;
use function array_keys;
use function count;
use function get_debug_type;
use function gettype;
use function is_array;
use function is_scalar;
use function range;
use function str_repeat;
use function var_export;

/**
 * Renders JSON-safe values as display or parsable strings for the debugger panels.
 */
final class Dump
{
    /**
     * Renders a value as a display string: quoted strings, bare scalars, and 4-space-indented arrays without
     * trailing commas.
     *
     * Usage example:
     *
     * ```php
     * $text = \PHPForge\Debug\Helper\Dump::asString(['a' => 1]);
     * ```
     *
     * @param mixed $value JSON-safe value to render.
     * @param int $depth Maximum nesting level rendered before collapsing to `[...]`.
     *
     * @return string Display string.
     */
    public static function asString(mixed $value, int $depth = 10): string
    {
        return self::dumpInternal($value, $depth, 0);
    }

    /**
     * Renders a value as a parsable PHP expression: `var_export()` scalars and short-syntax arrays with trailing
     * commas, omitting sequential integer keys.
     *
     * Usage example:
     *
     * ```php
     * $code = \PHPForge\Debug\Helper\Dump::export(['a', 'b']);
     * ```
     *
     * @param mixed $value JSON-safe value to render.
     *
     * @return string Parsable PHP expression.
     */
    public static function export(mixed $value): string
    {
        return self::exportInternal($value, 0);
    }

    /**
     * @param array<array-key, mixed> $value Array to render.
     * @param int $depth Maximum nesting level.
     * @param int $level Current nesting level.
     */
    private static function dumpArray(array $value, int $depth, int $level): string
    {
        if ($depth <= $level) {
            return '[...]';
        }

        if ($value === []) {
            return '[]';
        }

        $spaces = str_repeat(' ', $level * 4);

        $output = '[';

        foreach (array_keys($value) as $key) {
            $output .= "\n{$spaces}    ";
            $output .= self::dumpInternal($key, $depth, $level);
            $output .= ' => ';
            $output .= self::dumpInternal($value[$key], $depth, $level + 1);
        }

        return "{$output}\n{$spaces}]";
    }

    /**
     * @param int $depth Maximum nesting level.
     * @param int $level Current nesting level.
     */
    private static function dumpInternal(mixed $value, int $depth, int $level): string
    {
        return match (gettype($value)) {
            'boolean' => $value ? 'true' : 'false',
            'integer', 'double' => (string) $value,
            'string' => "'" . addslashes($value) . "'",
            'NULL' => 'null',
            'array' => self::dumpArray($value, $depth, $level),
            default => '{' . get_debug_type($value) . '}',
        };
    }

    /**
     * @param int $level Current nesting level.
     */
    private static function exportInternal(mixed $value, int $level): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $outputKeys = array_keys($value) !== range(0, count($value) - 1);
            $spaces = str_repeat(' ', $level * 4);

            $output = '[';

            foreach ($value as $key => $item) {
                $output .= "\n{$spaces}    ";

                if ($outputKeys) {
                    $output .= self::exportInternal($key, $level);

                    $output .= ' => ';
                }

                $output .= self::exportInternal($item, $level + 1);

                $output .= ',';
            }

            return "{$output}\n{$spaces}]";
        }

        if (is_scalar($value)) {
            return var_export($value, true);
        }

        return '{' . get_debug_type($value) . '}';
    }
}

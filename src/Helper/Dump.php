<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function addslashes;
use function array_is_list;
use function get_debug_type;
use function gettype;
use function is_array;
use function is_scalar;
use function str_repeat;
use function var_export;

/**
 * Renders JSON-safe values as display or parsable strings for the debugger panels.
 */
final class Dump
{
    /**
     * Renders a value as a display string: quoted strings, bare scalars, and 4-space-indented arrays without trailing
     * commas.
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
     * @param mixed $value JSON-safe value to render.
     *
     * @return string Parsable PHP expression.
     */
    public static function export(mixed $value): string
    {
        return self::exportInternal($value, 0);
    }

    /**
     * Renders an array as an indented display block, or `[...]` once the depth budget is exhausted.
     *
     * @param array<array-key, mixed> $value Array to render.
     * @param int $depth Maximum nesting level.
     * @param int $level Current nesting level.
     *
     * @return string Display block for the array.
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

        foreach ($value as $key => $item) {
            $output .= "\n{$spaces}    ";
            $output .= self::dumpInternal($key, $depth, $level);
            $output .= ' => ';
            $output .= self::dumpInternal($item, $depth, $level + 1);
        }

        return "{$output}\n{$spaces}]";
    }

    /**
     * Renders one value of any type at the current nesting level.
     *
     * @param mixed $value JSON-safe value to render.
     * @param int $depth Maximum nesting level.
     * @param int $level Current nesting level.
     *
     * @return string Display string for the value.
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
     * Renders one value as a parsable PHP expression at the current nesting level.
     *
     * @param mixed $value JSON-safe value to render.
     * @param int $level Current nesting level.
     *
     * @return string Parsable expression for the value.
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

            $outputKeys = array_is_list($value) === false;
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

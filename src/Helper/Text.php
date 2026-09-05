<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function is_string;
use function mb_strtolower;
use function parse_url;
use function preg_replace;
use function str_replace;
use function trim;

/**
 * Provides small string conversions shared by the debugger panels.
 */
final class Text
{
    /**
     * Converts a CamelCase name into a lowercase id with `-` word separators, matching the Yii inflector semantics.
     *
     * @param string $name CamelCase name to convert.
     *
     * @return string Lowercase kebab-case id.
     */
    public static function camel2id(string $name): string
    {
        $replaced = preg_replace('/(?<!\p{Lu})\p{Lu}/u', '-\0', $name) ?? $name;

        return mb_strtolower(trim(str_replace('_', '-', $replaced), '-'), 'UTF-8');
    }

    /**
     * Strips the authority from a captured URL for display, retaining its path, non-empty query, and fragment.
     *
     * Unparseable URLs pass through unchanged. This is a display conversion, not URL validation or HTML escaping;
     * callers must retain the original diagnostic URL and escape the result at the rendering boundary.
     */
    public static function urlToPath(string $url): string
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            return $url;
        }

        $path = is_string($parsed['path'] ?? null) ? $parsed['path'] : '/';
        $query = is_string($parsed['query'] ?? null) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';
        $fragment = is_string($parsed['fragment'] ?? null) && $parsed['fragment'] !== ''
            ? '#' . $parsed['fragment']
            : '';

        return "{$path}{$query}{$fragment}";
    }
}

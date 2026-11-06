<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use UIAwesome\Html\Helper\Encode;

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
     * Line break sequences, `\r\n` first so that it reads as one line break, as the HTML parser reads it.
     */
    private const array LINE_BREAKS = ["\r\n", "\r", "\n"];

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
     * Writes each line break of HTML markup as the `&#10;` character reference, leaving the markup itself unchanged.
     *
     * Use it for markup that is already escaped and carries line breaks only in its text, such as
     * {@see PhpHighlighter::highlightCode()} output; {@see self::preserveLineBreaks()} escapes raw text first. `\r\n`
     * and a lone `\r` become one line break, as the HTML parser reads them.
     *
     * @param string $html Escaped HTML markup.
     *
     * @return string Markup without raw line breaks.
     */
    public static function preserveHtmlLineBreaks(string $html): string
    {
        return str_replace(self::LINE_BREAKS, '&#10;', $html);
    }

    /**
     * Escapes text for HTML content and writes each of its line breaks as the `&#10;` character reference.
     *
     * The UI Awesome block builders other than `<pre>` collapse consecutive line breaks in their rendered output, which
     * drops the blank lines of a multi-line value rendered inside them; a character reference survives that
     * normalization and still renders as a line break under `white-space: pre-wrap`. `\r\n` and a lone `\r` become one
     * line break, as the HTML parser reads them.
     *
     * @param string $text Raw text to render.
     *
     * @return string Escaped HTML content without raw line breaks.
     */
    public static function preserveLineBreaks(string $text): string
    {
        return self::preserveHtmlLineBreaks(Encode::content($text));
    }

    /**
     * Strips the authority from a captured URL for display, retaining its path, non-empty query, and fragment.
     *
     * Unparsable URLs pass through unchanged. This is a display conversion, not URL validation or HTML escaping;
     * callers must retain the original diagnostic URL and escape the result at the rendering boundary.
     *
     * @param string $url Captured URL to convert for display.
     *
     * @return string Path with its non-empty query and fragment, or the original URL when unparsable.
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

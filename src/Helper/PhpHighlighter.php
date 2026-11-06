<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function highlight_string;
use function preg_replace;

/**
 * Renders a captured value as syntax-highlighted PHP, the way the Dump panel presents its payloads.
 */
final class PhpHighlighter
{
    /**
     * Highlights a value as the PHP expression that would recreate it.
     *
     * The expression is built here with {@see Dump::export()} and handed to {@see self::highlightCode()}, so the result
     * carries no markup from the captured value.
     *
     * @param mixed $value JSON-safe value to render.
     *
     * @return string Highlighted markup in a focusable `<pre>`, without the opening tag PHP needs to recognize the
     * snippet.
     */
    public static function highlight(mixed $value): string
    {
        return self::highlightCode(Dump::export($value));
    }

    /**
     * Highlights PHP source text, such as the `var_export()` output carried by a log message.
     *
     * PHP's highlighter escapes the whole source, so markup carried by the text renders as text and never as markup.
     * The source is only highlighted, never evaluated.
     *
     * The `<pre>` wrapper scrolls horizontally, so it carries `tabindex="0"` to stay reachable by keyboard.
     *
     * @param string $code PHP source of an expression, without the opening tag.
     *
     * @return string Highlighted markup in a focusable `<pre>`, without the opening tag PHP needs to recognize the
     * snippet.
     */
    public static function highlightCode(string $code): string
    {
        return (string) preg_replace(
            [
                '~^(<pre><code[^>]*><span[^>]*>)&lt;\?php\s*~',
                '~^(<pre><code[^>]*>)<span[^>]*></span>~',
                '~^<pre>~',
            ],
            ['$1', '$1', '<pre tabindex="0">'],
            highlight_string("<?php\n" . $code, true),
        );
    }
}

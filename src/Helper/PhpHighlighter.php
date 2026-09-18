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
     * The expression is built here and handed straight to PHP's highlighter, which escapes it, so the result carries no
     * markup from the captured value.
     *
     * The `<pre>` wrapper scrolls horizontally, so it carries `tabindex="0"` to stay reachable by keyboard.
     *
     * @param mixed $value JSON-safe value to render.
     *
     * @return string Highlighted markup in a focusable `<pre>`, without the opening tag PHP needs to recognize the
     * snippet.
     */
    public static function highlight(mixed $value): string
    {
        return (string) preg_replace(
            [
                '~^(<pre><code[^>]*><span[^>]*>)&lt;\?php\s*~',
                '~^(<pre><code[^>]*>)<span[^>]*></span>~',
                '~^<pre>~',
            ],
            ['$1', '$1', '<pre tabindex="0">'],
            highlight_string("<?php\n" . Dump::export($value), true),
        );
    }
}

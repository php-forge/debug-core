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
     * @param mixed $value JSON-safe value to render.
     *
     * @return string Highlighted markup, without the opening tag PHP needs to recognize the snippet.
     */
    public static function highlight(mixed $value): string
    {
        $html = highlight_string("<?php\n" . Dump::export($value), true);

        $html = (string) preg_replace('~^(<pre><code[^>]*><span[^>]*>)&lt;\?php\s*~', '$1', $html);

        return (string) preg_replace('~^(<pre><code[^>]*>)<span[^>]*></span>~', '$1', $html);
    }
}

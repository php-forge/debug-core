<?php

declare(strict_types=1);

namespace PHPForge\Debug\Toolbar;

use function strripos;
use function substr_replace;

/**
 * Places rendered toolbar markup inside an already-rendered HTML response.
 */
final class ToolbarInjector
{
    /**
     * Injects toolbar markup immediately before the final closing body tag.
     *
     * @param string $html Response HTML.
     * @param string $toolbar Rendered toolbar markup.
     *
     * @return string HTML containing the toolbar, appended when no closing body tag exists.
     */
    public static function inject(string $html, string $toolbar): string
    {
        $offset = strripos($html, '</body>');

        return $offset === false
            ? "{$html}{$toolbar}"
            : substr_replace($html, $toolbar, $offset, 0);
    }
}

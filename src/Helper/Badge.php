<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use PHPForge\Debug\Theme\Css;
use PHPForge\Debug\Tone;
use UIAwesome\Html\Phrasing\Span;

/**
 * Renders the shared badge chip carrying a semantic tone.
 */
final class Badge
{
    /**
     * Renders one badge chip.
     *
     * @param string $label Text shown inside the chip.
     * @param Tone $tone Semantic tone selecting the chip hue.
     * @param string $modifier Extra CSS classes appended after the tone class, or `''` to add none.
     *
     * @return Span Badge chip element.
     */
    public static function render(string $label, Tone $tone, string $modifier = ''): Span
    {
        $class = Css::badge($tone);

        return Span::tag()
            ->class($modifier === '' ? $class : "{$class} {$modifier}")
            ->content($label);
    }
}

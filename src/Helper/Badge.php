<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use UIAwesome\Html\Phrasing\Span;

/**
 * Renders the shared `yii-debug-badge` chip carrying a semantic variant.
 */
final class Badge
{
    /**
     * Renders one badge chip.
     *
     * @param string $label Text shown inside the chip.
     * @param string $variant Semantic suffix appended to `yii-debug-badge-` (`success`, `muted`, `warning`, ...).
     * @param string $modifier Extra CSS classes appended after the variant class, or `''` to add none.
     *
     * @return Span Badge chip element.
     */
    public static function render(string $label, string $variant, string $modifier = ''): Span
    {
        $class = "yii-debug-badge yii-debug-badge-{$variant}";

        return Span::tag()
            ->class($modifier === '' ? $class : "{$class} {$modifier}")
            ->content($label);
    }
}

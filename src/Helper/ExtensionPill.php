<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use PHPForge\Debug\Theme\Css;
use UIAwesome\Html\Phrasing\Span;

/**
 * Renders the shared `yii-debug-ext-pill` chip: a status LED, the extension name, and its state.
 */
final class ExtensionPill
{
    /**
     * Renders one extension pill.
     *
     * @param string $label Extension or module name shown in the pill.
     * @param string $state Short state text shown after the label (`on`, `off`, or a version).
     * @param bool $enabled Whether the extension is active, selecting the `is-on` / `is-off` modifier.
     * @param string $summary Screen-reader-only detail appended after the state, or `''` to omit it.
     *
     * @return Span Extension pill element.
     */
    public static function render(string $label, string $state, bool $enabled, string $summary = ''): Span
    {
        $children = [
            Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class(Css::EXT_PILL_DOT),
            Span::tag()
                ->class(Css::EXT_PILL_LABEL)
                ->content($label),
            Span::tag()
                ->class(Css::EXT_PILL_STATE)
                ->content($state),
        ];

        if ($summary !== '') {
            $children[] = Span::tag()
                ->class(Css::SR_ONLY)
                ->content($summary);
        }

        return Span::tag()
            ->class(Css::EXT_PILL . ' ' . ($enabled ? 'is-on' : 'is-off'))
            ->html(...$children);
    }
}

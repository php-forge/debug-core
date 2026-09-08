<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

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
                ->class('yii-debug-ext-pill-dot'),
            Span::tag()
                ->class('yii-debug-ext-pill-label')
                ->content($label),
            Span::tag()
                ->class('yii-debug-ext-pill-state')
                ->content($state),
        ];

        if ($summary !== '') {
            $children[] = Span::tag()
                ->class('yii-debug-sr-only')
                ->content($summary);
        }

        return Span::tag()
            ->class('yii-debug-ext-pill ' . ($enabled ? 'is-on' : 'is-off'))
            ->html(...$children);
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\Phrasing\Span;

/**
 * Renders the shared collapsible section: a titled `<summary>` with an expand/collapse hint over a `<details>` body.
 *
 * Both wordings ship in the markup and CSS reveals the one matching the `open` state, so the affordance never invites
 * a click it cannot honour and no script is involved. The `<summary>` element already announces the state through
 * `aria-expanded`, so the hint stays out of the accessibility tree.
 */
final class Disclosure
{
    /**
     * Renders the expand/collapse affordance shown on the right of a summary.
     *
     * Exposed on its own so sections that build their own `<summary>` (the phpinfo Overview blocks) still share one
     * wording and one behaviour.
     *
     * Usage example:
     * ```php
     * $hint = \PHPForge\Debug\Helper\Disclosure::hint();
     * ```
     *
     * @return Span Expand/collapse hint element.
     */
    public static function hint(): Span
    {
        return Span::tag()
            ->addAriaAttribute('hidden', 'true')
            ->class('yii-debug-disclosure-hint')
            ->html(
                Span::tag()
                    ->addDataAttribute('yii-debug-hint', 'collapsed')
                    ->content('click to expand'),
                Span::tag()
                    ->addDataAttribute('yii-debug-hint', 'expanded')
                    ->content('click to collapse'),
            );
    }

    /**
     * Renders a titled collapsible section.
     *
     * Usage example:
     * ```php
     * $section = \PHPForge\Debug\Helper\Disclosure::render('Raw payload', $preBlock);
     * ```
     *
     * @param string $title Section heading shown in the summary.
     * @param string $body Rendered HTML revealed when the section expands.
     *
     * @return string Collapsible section markup.
     */
    public static function render(string $title, string $body): string
    {
        return Details::tag()
            ->class('yii-debug-disclosure')
            ->html(
                Summary::tag()
                    ->class('yii-debug-disclosure-summary')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-disclosure-title')
                            ->content($title),
                        self::hint(),
                    ),
                Div::tag()
                    ->class('yii-debug-disclosure-body')
                    ->html($body),
            )
            ->render();
    }
}

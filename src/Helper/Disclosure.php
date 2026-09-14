<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use PHPForge\Debug\Theme\Css;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\Phrasing\Span;

/**
 * Renders the shared collapsible section: a titled `<summary>` with an expand/collapse hint over a `<details>` body.
 */
final class Disclosure
{
    /**
     * Renders the expand/collapse affordance shown on the right of a summary.
     *
     * Exposed on its own so sections that build their own `<summary>` (the phpinfo Overview blocks) still share one
     * wording and one behaviour.
     *
     * @return Span Expand/collapse hint element.
     */
    public static function hint(): Span
    {
        return Span::tag()
            ->addAriaAttribute('hidden', 'true')
            ->class(Css::DISCLOSURE_HINT)
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
     * @param string $title Section heading shown in the summary.
     * @param string $body Rendered HTML revealed when the section expands.
     * @param bool $open Whether the section is expanded initially.
     *
     * @return string Collapsible section markup.
     */
    public static function render(string $title, string $body, bool $open = false): string
    {
        return Details::tag()
            ->open($open)
            ->class(Css::DISCLOSURE)
            ->html(
                Summary::tag()
                    ->class(Css::DISCLOSURE_SUMMARY)
                    ->html(
                        Span::tag()
                            ->class(Css::DISCLOSURE_TITLE)
                            ->content($title),
                        self::hint(),
                    ),
                Div::tag()
                    ->class(Css::DISCLOSURE_BODY)
                    ->html($body),
            )
            ->render();
    }
}

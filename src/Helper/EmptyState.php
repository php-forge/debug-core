<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use Stringable;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H2;

/**
 * Renders the contextual empty-state card shown when a panel captured no data for the request.
 *
 * Every panel shares the same `Div.yii-debug-empty-state` container and `<h2>` headline; the caller supplies the
 * explanatory body elements (paragraphs, code snippets), keeping the copy local to each view.
 */
final class EmptyState
{
    /**
     * Renders the empty-state card: headline followed by the explanatory body elements.
     *
     * Body values are trusted markup assembled by debug adapters; callers must encode untrusted data before passing it.
     *
     * Usage example:
     * ```php
     * use UIAwesome\Html\Flow\P;
     *
     * \PHPForge\Debug\Helper\EmptyState::card(
     *     'No variables dumped in this request',
     *     P::tag()->content('To populate this view, dump values with Yii::debug().'),
     * );
     * ```
     *
     * @param string $headline Card headline describing the empty capture.
     * @param string|Stringable ...$body Trusted explanatory body markup rendered after the headline.
     *
     * @return string Empty-state card markup.
     */
    public static function card(string $headline, string|Stringable ...$body): string
    {
        return Div::tag()
            ->class('yii-debug-empty-state')
            ->html(H2::tag()->content($headline), ...$body)
            ->render();
    }
}

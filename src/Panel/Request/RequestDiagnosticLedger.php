<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Dd, Dl, Dt};

/**
 * Builds the shared `yii-debug-diagnostic-ledger` used by the Request headers and server-variable panes.
 */
final class RequestDiagnosticLedger
{
    /**
     * Renders the ledger holding the supplied rows.
     *
     * @param string $modifier Extra CSS classes appended after the shared ledger class.
     * @param Div ...$rows Ledger rows in capture order.
     *
     * @return string Ledger markup.
     */
    public static function render(string $modifier, Div ...$rows): string
    {
        return Dl::tag()
            ->class("yii-debug-diagnostic-ledger {$modifier}")
            ->html(...$rows)
            ->render();
    }

    /**
     * Builds one filterable ledger row from already-escaped term and description markup.
     *
     * @param string $term Escaped entry name.
     * @param string $description Rendered entry value.
     * @param string $modifier Extra CSS classes appended after the shared row class, or `''` to add none.
     *
     * @return Div Ledger row element.
     */
    public static function row(string $term, string $description, string $modifier = ''): Div
    {
        $class = 'yii-debug-diagnostic-row';

        return Div::tag()
            ->addDataAttribute('yii-debug-filter-row', true)
            ->class($modifier === '' ? $class : "{$class} {$modifier}")
            ->html(
                Dt::tag()->html($term),
                Dd::tag()->html($description),
            );
    }
}

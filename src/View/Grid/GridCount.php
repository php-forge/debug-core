<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Grid;

use UIAwesome\Html\Phrasing\Span;

/**
 * Renders the row-count sentence below a panel grid.
 */
final class GridCount
{
    /**
     * Returns the rendered row-count sentence for the rows currently on screen.
     *
     * @param int $begin One-based position of the first visible row.
     * @param int $end One-based position of the last visible row.
     * @param int $total Number of rows matching the active filters.
     */
    public static function render(int $begin, int $end, int $total): string
    {
        return Span::tag()
            ->class('summary yii-debug-grid-count')
            ->content("Showing {$begin}-{$end} of {$total} " . ($total === 1 ? 'item' : 'items') . '.')
            ->render();
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Grid;

use PHPForge\Debug\View\Grid\GridCount;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see GridCount} covering the shared row-count sentence and its singular wording.
 */
#[Group('view')]
#[Group('grid')]
final class GridCountTest extends TestCase
{
    public function testRenderUsesTheSharedSummaryMarkupAndPluralizesTheTotal(): void
    {
        self::assertSame(
            '<span class="summary yii-debug-grid-count">Showing 0-0 of 0 items.</span>',
            GridCount::render(0, 0, 0),
            'An empty grid must keep the plural wording.',
        );
        self::assertSame(
            '<span class="summary yii-debug-grid-count">Showing 1-1 of 1 item.</span>',
            GridCount::render(1, 1, 1),
            'A single row must use the singular wording.',
        );
        self::assertSame(
            '<span class="summary yii-debug-grid-count">Showing 1-2 of 2 items.</span>',
            GridCount::render(1, 2, 2),
            'Several rows must use the plural wording.',
        );
    }
}

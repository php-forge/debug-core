<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Grid;

use PHPForge\Debug\View\Grid\SummaryChip;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see SummaryChip} covering the metric chip and the separator of a grid summary header.
 */
#[Group('view')]
#[Group('grid')]
final class SummaryChipTest extends TestCase
{
    public function testRenderHighlightsTheValueBeforeItsLabel(): void
    {
        self::assertSame(
            '<span><strong>3</strong> queries</span>',
            SummaryChip::render('3', ' queries')->render(),
            'Value must be strong and followed by the label.',
        );
    }

    public function testSeparatorUsesTheSharedClass(): void
    {
        self::assertSame(
            '<span class="yii-debug-grid-summary-sep">·</span>',
            SummaryChip::separator()->render(),
            'Separator must be a middle dot with the shared class.',
        );
    }
}

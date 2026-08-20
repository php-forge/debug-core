<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Event;

use PHPForge\Debug\Panel\Event\{EventCellRenderer, EventRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see EventCellRenderer} covering the typed cell renderers used by the events grid (time formatting,
 * FQCN splitting for the class/sender cells, and the static badge).
 */
#[Group('panel')]
#[Group('event')]
final class EventCellRendererTest extends TestCase
{
    public function testRenderClassCellOmitsNamespacePrefixForGlobalClasses(): void
    {
        $cell = EventCellRenderer::renderClassCell(self::makeRow(class: 'stdClass'));

        self::assertSame(
            <<<HTML
            <span title="stdClass"><strong>stdClass</strong></span>
            HTML,
            $cell,
            'Short name must render bold.',
        );

    }

    public function testRenderClassCellSplitsFqcnIntoMutedNamespaceAndStrongShortName(): void
    {
        $cell = EventCellRenderer::renderClassCell(self::makeRow(class: 'yii\\base\\Event'));

        self::assertSame(
            <<<HTML
            <span title="yii\base\Event"><span class="yii-debug-muted">yii\base\</span><wbr><strong>Event</strong></span>
            HTML,
            $cell,
            'Namespace prefix must render muted.',
        );


    }

    public function testRenderSenderCellRendersEmDashForStaticEvents(): void
    {
        self::assertSame(
            '—',
            EventCellRenderer::renderSenderCell(self::makeRow(senderClass: '')),
            'Empty sender must collapse to an em dash.',
        );
    }

    public function testRenderSenderCellSplitsFqcnIntoMutedNamespaceAndStrongShortName(): void
    {
        $cell = EventCellRenderer::renderSenderCell(self::makeRow(senderClass: 'yii\\web\\Application'));

        self::assertSame(
            <<<HTML
            <span title="yii\web\Application"><span class="yii-debug-muted">yii\web\</span><wbr><strong>Application</strong></span>
            HTML,
            $cell,
            'Namespace prefix must render muted.',
        );

    }

    public function testRenderStaticCellRendersEmDashForObjectEvents(): void
    {
        self::assertSame(
            '—',
            EventCellRenderer::renderStaticCell(self::makeRow(isStatic: '0')),
            'Object events must collapse to an em dash.',
        );
    }

    public function testRenderStaticCellRendersMutedBadgeForStaticEvents(): void
    {
        $cell = EventCellRenderer::renderStaticCell(self::makeRow(isStatic: '1'));

        self::assertSame(
            <<<HTML
            <span class="yii-debug-badge yii-debug-badge-muted">static</span>
            HTML,
            $cell,
            'Static flag must render the muted badge.',
        );

    }

    public function testRenderTimeCellFormatsTimestampAsHmsWithMillis(): void
    {
        self::assertSame(
            date('H:i:s', 1_700_000_000) . '.789',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 1_700_000_000.789)),
            "Timestamp must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeCellHandlesZeroTime(): void
    {
        self::assertSame(
            date('H:i:s', 0) . '.000',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 0.0)),
            "Zero time must format as 'H:i:s.000'.",
        );
    }

    public function testRenderTimeCellKeepsMillisecondsBelowTheNextBoundary(): void
    {
        self::assertSame(
            date('H:i:s', 1) . '.500',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 1.5005)),
            'Sub-millisecond fractions must not advance the rendered millisecond value.',
        );
    }

    public function testRenderTimeCellPadsMillisecondsWithLeadingZeros(): void
    {
        self::assertSame(
            date('H:i:s', 1_700_000_000) . '.005',
            EventCellRenderer::renderTimeCell(self::makeRow(time: 1_700_000_000.005)),
            'Milliseconds below 100 must be zero-padded to three digits.',
        );
    }

    private static function makeRow(
        float $time = 0.0,
        string $name = 'EVENT_X',
        string $class = 'yii\\base\\Event',
        string $isStatic = '0',
        string $senderClass = 'yii\\web\\Application',
    ): EventRow {
        return new EventRow(
            time: $time,
            name: $name,
            class: $class,
            isStatic: $isStatic,
            senderClass: $senderClass,
        );
    }
}

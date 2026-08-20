<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Log\{LogCellRenderer, LogRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LogCellRenderer} covering the typed cell renderers used by the logs grid (time, level, two-tone
 * category label, time-since-previous navigation, message + trace, SQL highlighting for DB command entries, row options
 * ).
 */
#[Group('panel')]
#[Group('log')]
final class LogCellRendererTest extends TestCase
{
    public function testBuildRowOptionsAttachesAnchorIdAndSeverityClass(): void
    {
        $options = LogCellRenderer::buildRowOptions(
            self::makeRow(id: 7, level: LogLevel::ERROR),
        );

        self::assertSame(
            'log-7',
            $options['id'] ?? null,
            "Row id must be 'log-{N}'.",
        );
        self::assertSame(
            'yii-debug-row-danger',
            $options['class'] ?? null,
            'Error level must carry the danger class.',
        );
    }

    public function testBuildRowOptionsMapsWarningAndInfoToTheirVariantClasses(): void
    {
        self::assertSame(
            'yii-debug-row-warning',
            LogCellRenderer::buildRowOptions(self::makeRow(level: LogLevel::WARNING))['class'] ?? null,
            'Warning level must map to the warning class.',
        );
        self::assertSame(
            'yii-debug-row-info',
            LogCellRenderer::buildRowOptions(self::makeRow(level: LogLevel::INFO))['class'] ?? null,
            'Info level must map to the info class.',
        );
    }

    public function testBuildRowOptionsOmitsClassForLevelsWithoutVariantMapping(): void
    {
        $options = LogCellRenderer::buildRowOptions(
            self::makeRow(id: 3, level: LogLevel::TRACE),
        );

        self::assertSame(
            'log-3',
            $options['id'] ?? null,
            "Row id must be 'log-{N}'.",
        );
        self::assertArrayNotHasKey(
            'class',
            $options,
            'Trace level must not attach a row class.',
        );
    }

    public function testRenderCategoryCellKeepsMethodSuffixInsideStrongShortName(): void
    {
        self::assertSame(
            <<<HTML
            <span title="yii\db\Command::query"><span class="yii-debug-muted">yii\db\</span><wbr><strong>Command::query</strong></span>
            HTML,
            LogCellRenderer::renderCategoryCell(self::makeRow(category: 'yii\\db\\Command::query')),
            'Method pair must render bold as one segment.',
        );
    }

    public function testRenderCategoryCellRendersPlainCategoryWithoutMutedPrefix(): void
    {
        $cell = LogCellRenderer::renderCategoryCell(self::makeRow(category: 'application'));

        self::assertSame(
            <<<HTML
            <span title="application"><strong>application</strong></span>
            HTML,
            $cell,
            'Plain category must render bold.',
        );

    }

    public function testRenderCategoryCellSplitsFqcnCategoryIntoMutedNamespaceAndStrongShortName(): void
    {
        $cell = LogCellRenderer::renderCategoryCell(self::makeRow(category: 'yii\\web\\UrlManager::parseRequest'));

        self::assertSame(
            <<<HTML
            <span title="yii\web\UrlManager::parseRequest"><span class="yii-debug-muted">yii\web\</span><wbr><strong>UrlManager::parseRequest</strong></span>
            HTML,
            $cell,
            'Namespace prefix must render muted.',
        );

    }

    public function testRenderLevelCellWrapsLevelNameInVocabularyChip(): void
    {
        self::assertSame(
            '<span class="yii-debug-level-chip yii-debug-level-error">error</span>',
            LogCellRenderer::renderLevelCell(self::makeRow(level: LogLevel::ERROR)),
            "Error level must wear the 'error' chip.",
        );
        self::assertSame(
            '<span class="yii-debug-level-chip yii-debug-level-warning">warning</span>',
            LogCellRenderer::renderLevelCell(self::makeRow(level: LogLevel::WARNING)),
            "Warning level must wear the 'warning' chip.",
        );
        self::assertSame(
            <<<'HTML'
        <span class="yii-debug-level-chip yii-debug-level-trace">trace</span>
        HTML,
            LogCellRenderer::renderLevelCell(self::makeRow(level: LogLevel::TRACE)),
            "Trace level must wear the 'trace' chip.",
        );
        self::assertSame(
            <<<'HTML'
        <span class="yii-debug-level-chip yii-debug-level-profile">profile</span>
        HTML,
            LogCellRenderer::renderLevelCell(self::makeRow(level: LogLevel::PROFILE)),
            "Profile level must wear the 'profile' chip.",
        );
    }

    public function testRenderMessageCellAppendsTraceListWhenTraceHasFrames(): void
    {
        $html = LogCellRenderer::renderMessageCell(
            self::makeRow(
                message: 'Something happened',
                trace: [['file' => '/app/User.php', 'line' => 42]],
            ),
            self::traceLine(),
        );

        self::assertSame(
            <<<HTML
            Something happened<ul class="yii-debug-trace">
            <li>
            /app/User.php:
            </li>
            </ul>
            HTML,
            $html,
            'Message text must be present.',
        );
    }

    public function testRenderMessageCellClampsLongMessageBehindMoreToggle(): void
    {
        $message = str_repeat('long message segment ', 40);

        $html = LogCellRenderer::renderMessageCell(
            self::makeRow(message: $message),
            self::traceLine(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            {$message}
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">[+] Show more</button>
            </div>
            HTML,
            $html,
            'Long message must render inside the clamp.',
        );
    }

    public function testRenderMessageCellEscapesPlainMessageWhenTraceIsEmpty(): void
    {
        $html = LogCellRenderer::renderMessageCell(
            self::makeRow(message: '<script>alert(1)</script>'),
            self::traceLine(),
        );

        self::assertSame(
            <<<HTML
            &lt;script&gt;alert(1)&lt;/script&gt;
            HTML,
            $html,
            'Message must render as exact HTML-escaped text without a trace list.',
        );
    }

    public function testRenderMessageCellHighlightsSqlForDbCommandCategory(): void
    {
        $html = LogCellRenderer::renderMessageCell(
            self::makeRow(message: 'SELECT * FROM `user` WHERE id = 1', category: 'yii\db\Command::query'),
            self::traceLine(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> * <span class="yii-debug-sql-kw">FROM</span> `user` <span class="yii-debug-sql-kw">WHERE</span> id = <span class="yii-debug-sql-num">1</span>
            </div>
            HTML,
            $html,
            'SQL body must wear the mono wrapper.',
        );

    }

    public function testRenderMessageCellKeepsPlainEscapingForNonDbCategory(): void
    {
        $html = LogCellRenderer::renderMessageCell(
            self::makeRow(message: 'SELECT 1', category: 'app'),
            self::traceLine(),
        );

        self::assertSame(
            'SELECT 1',
            $html,
            'Non-DB categories must stay plain text.',
        );
    }

    public function testRenderMessageCellLeavesShortMessageUnclamped(): void
    {
        self::assertSame(
            <<<HTML
            short message
            HTML,
            LogCellRenderer::renderMessageCell(self::makeRow(message: 'short message'), self::traceLine()),
            'Short message must render exactly without the clamp.',
        );
    }

    public function testRenderTimeCellFormatsMillisecondsAtTheUpperBoundary(): void
    {
        self::assertSame(
            date('H:i:s', 1_700_000_000) . '.123',
            LogCellRenderer::renderTimeCell(self::makeRow(time: 1_700_000_000_123.0)),
            'Millisecond conversion must use exactly one thousand units per second.',
        );
    }

    public function testRenderTimeCellFormatsMillisecondTimestampAsHmsWithMillis(): void
    {
        $expected = date('H:i:s', 1_700_000_000) . '.789';

        $html = LogCellRenderer::renderTimeCell(
            self::makeRow(time: 1_700_000_000_789.0),
        );

        self::assertSame(
            $expected,
            $html,
            "Timestamp must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeCellTruncatesFractionalMillisecondsWithoutImplicitConversion(): void
    {
        self::assertSame(
            date('H:i:s', 1_787_057_930) . '.402',
            LogCellRenderer::renderTimeCell(self::makeRow(time: 1_787_057_930_402.636)),
            'Fractional milliseconds must be truncated explicitly before integer arithmetic.',
        );
    }

    public function testRenderTimeSincePreviousCellEmitsAbsoluteDiffWithUnitsAndArrows(): void
    {
        $html = LogCellRenderer::renderTimeSincePreviousCell(
            self::makeRow(
                time: 1_700_000_001_500.0,
                timeOfPrevious: 1_700_000_000_000.0,
                idOfPrevious: 6,
                idOfNext: 8,
            ),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-since-previous">
            <a class="yii-debug-since-previous-btn" href="#log-6">&lt;</a><span>1s 500ms</span><a class="yii-debug-since-previous-btn" href="#log-8">&gt;</a>
            </div>
            HTML,
            $html,
            'Wrapper class must be present.',
        );
    }

    public function testRenderTimeSincePreviousCellIncludesHoursAndMinutesWhenDeltaExceedsOneHour(): void
    {
        $html = LogCellRenderer::renderTimeSincePreviousCell(
            self::makeRow(
                time: 1_700_000_000_000.0 + (2 * 3600 + 5 * 60 + 7) * 1000 + 250,
                timeOfPrevious: 1_700_000_000_000.0,
                idOfPrevious: 1,
                idOfNext: 2,
            ),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-since-previous">
            <a class="yii-debug-since-previous-btn" href="#log-1">&lt;</a><span>2h 5m 7s 250ms</span><a class="yii-debug-since-previous-btn" href="#log-2">&gt;</a>
            </div>
            HTML,
            $html,
            'Diff components must be integral and rendered in exact descending unit order.',
        );
    }

    public function testRenderTimeSincePreviousCellRendersDisabledArrowsAtBoundaries(): void
    {
        $html = LogCellRenderer::renderTimeSincePreviousCell(
            self::makeRow(
                time: 1_700_000_000_500.0,
                timeOfPrevious: 1_700_000_000_500.0,
                idOfPrevious: null,
                idOfNext: null,
            )
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-since-previous">
            <span class="yii-debug-since-previous-btn is-disabled">&lt;</span><span>0ms</span><span class="yii-debug-since-previous-btn is-disabled">&gt;</span>
            </div>
            HTML,
            $html,
            'Boundary rows must render disabled arrows.',
        );
    }

    public function testRenderTimeSincePreviousCellUsesSixtyMinutesPerHour(): void
    {
        $base = 1_700_000_000_000.0;

        $belowHour = LogCellRenderer::renderTimeSincePreviousCell(
            self::makeRow(time: $base + (59 * 60 + 30) * 1000, timeOfPrevious: $base),
        );
        $aboveHour = LogCellRenderer::renderTimeSincePreviousCell(
            self::makeRow(time: $base + (60 * 60 + 30) * 1000, timeOfPrevious: $base),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-since-previous">
            <span class="yii-debug-since-previous-btn is-disabled">&lt;</span><span>59m 30s 0ms</span><span class="yii-debug-since-previous-btn is-disabled">&gt;</span>
            </div>
            HTML,
            $belowHour,
            'Fifty-nine minutes must stay below one hour.',
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-since-previous">
            <span class="yii-debug-since-previous-btn is-disabled">&lt;</span><span>1h 30s 0ms</span><span class="yii-debug-since-previous-btn is-disabled">&gt;</span>
            </div>
            HTML,
            $aboveHour,
            'Sixty minutes must roll over to one hour.',
        );
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function makeRow(
        int $id = 1,
        string $message = 'msg',
        int $level = LogLevel::INFO,
        string $category = 'app',
        float $time = 0.0,
        float $timeOfPrevious = 0.0,
        int|null $idOfPrevious = null,
        int|null $idOfNext = null,
        array $trace = [],
    ): LogRow {
        return new LogRow(
            id: $id,
            message: $message,
            level: $level,
            category: $category,
            time: $time,
            timeOfPrevious: $timeOfPrevious,
            timeSincePrevious: ($time - $timeOfPrevious) / 1000,
            idOfPrevious: $idOfPrevious,
            idOfNext: $idOfNext,
            memory: 0,
            trace: $trace,
        );
    }

    /**
     * Returns a deterministic trace-line closure standing in for the adapter's IDE-link renderer.
     *
     * @return \Closure(array<string, mixed>): string Trace-line renderer.
     */
    private static function traceLine(): \Closure
    {
        return static fn(array $frame): string => Coerce::string($frame['file']
            ?? null) . ':' . Coerce::string($frame['line'] ?? null);
    }
}

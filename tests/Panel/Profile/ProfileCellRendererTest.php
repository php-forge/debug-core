<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Profile;

use PHPForge\Debug\Helper\CellMore;
use PHPForge\Debug\Panel\Profile\{ProfileCellRenderer, ProfileRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Unit tests for {@see ProfileCellRenderer} covering the typed cell renderers used by the profile grid (time
 * formatting with the hover tooltip, duration formatting, two-tone category label, and the indented info cell with
 * its SQL highlighting and long-statement clamp).
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileCellRendererTest extends TestCase
{
    public function testRenderCategoryCellKeepsMethodSuffixInsideStrongShortName(): void
    {
        self::assertSame(
            <<<HTML
            <span title="yii\db\Command::query"><span class="yii-debug-muted">yii\db\</span><wbr><strong>Command::query</strong></span>
            HTML,
            ProfileCellRenderer::renderCategoryCell(self::makeRow(category: 'yii\\db\\Command::query')),
            'Method pair must render bold as one segment.',
        );
    }

    public function testRenderCategoryCellRendersPlainCategoryWithoutMutedPrefix(): void
    {
        $cell = ProfileCellRenderer::renderCategoryCell(self::makeRow(category: 'application'));

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
        $cell = ProfileCellRenderer::renderCategoryCell(self::makeRow(category: 'yii\\db\\Command::query'));

        self::assertSame(
            <<<HTML
            <span title="yii\db\Command::query"><span class="yii-debug-muted">yii\db\</span><wbr><strong>Command::query</strong></span>
            HTML,
            $cell,
            'Namespace prefix must render muted.',
        );
    }

    public function testRenderDurationCellFormatsDurationToOneDecimalMillisecond(): void
    {
        self::assertSame(
            '12.5 ms',
            ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 12.5), 0.0),
            'Duration must keep one decimal.',
        );
        self::assertSame(
            '0.0 ms',
            ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 0.0), 0.0),
            "Zero duration must render as '0.0 ms'.",
        );
    }

    public function testRenderDurationCellScalesGaugeAgainstCaptureMaximum(): void
    {
        $html = ProfileCellRenderer::renderDurationCell(self::makeRow(duration: 12.5), 25.0);

        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">12.5 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            $html,
            'Rail must sit at half the capture maximum.',
        );
    }

    public function testRenderInfoCellClampsLongStatements(): void
    {
        $long = 'SELECT ' . str_repeat('a', CellMore::THRESHOLD);
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(category: 'yii\\db\\Command::query', info: $long));

        self::assertSame(
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
            </div>
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">[+] Show more</button>
            </div>
            HTML,
            $html,
            'A long statement must collapse behind the clamp.',
        );

    }

    public function testRenderInfoCellEmitsOneIndentArrowPerLevel(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(info: 'nested', level: 3));

        self::assertSame(
            str_repeat('<span class="yii-debug-indent">→</span>', 3) . 'nested',
            $html,
            'Indentation arrows must precede the profile token.',
        );
        self::assertSame(
            3,
            substr_count($html, 'yii-debug-indent'),
            'Each nesting level must add one indentation arrow.',
        );
        self::assertSame(
            3,
            substr_count($html, '→'),
            'Each indentation arrow must contain the chevron glyph.',
        );
        self::assertSame(
            <<<'HTML'
        <span class="yii-debug-indent">→</span><span class="yii-debug-indent">→</span><span class="yii-debug-indent">→</span>nested
        HTML,
            $html,
            'Info text must be visible after the indentation arrows.',
        );
    }

    public function testRenderInfoCellEscapesInfoText(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(info: '<script>alert(1)</script>', level: 0));

        self::assertSame(
            <<<HTML
            &lt;script&gt;alert(1)&lt;/script&gt;
            HTML,
            $html,
            'Info content must render as exact HTML-escaped text.',
        );
    }

    public function testRenderInfoCellHighlightsSqlForDbCommandBlocks(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(
            self::makeRow(category: 'yii\\db\\Command::query', info: 'SELECT * FROM "user"'),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> * <span class="yii-debug-sql-kw">FROM</span> "user"
            </div>
            HTML,
            $html,
            'SQL must reuse the queries-grid presentation.',
        );
    }

    public function testRenderInfoCellHighlightsSqlForYii3DbCommandBlocks(): void
    {
        self::assertSame(
            <<<'HTML'
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            HTML,
            ProfileCellRenderer::renderInfoCell(
                self::makeRow(category: 'Yiisoft\\Db\\Command::query', info: 'SELECT 1'),
            ),
            'Yii3 DB command categories must use the exact shared SQL presentation.',
        );
    }

    public function testRenderInfoCellKeepsPlainInfoUnhighlighted(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(category: 'application', info: 'SELECT me'));

        self::assertSame(
            <<<'HTML'
            SELECT me
            HTML,
            $html,
            'Only DB command blocks may render highlighted HTML.',
        );
    }

    public function testRenderInfoCellLeavesShortStatementsUnclamped(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(
            self::makeRow(category: 'yii\\db\\Command::query', info: 'SELECT 1'),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            HTML,
            $html,
            'Short statements must stay inline.',
        );
    }

    public function testRenderInfoCellOmitsIndentArrowsAtLevelZero(): void
    {
        $html = ProfileCellRenderer::renderInfoCell(self::makeRow(info: 'root', level: 0));

        self::assertSame(
            <<<HTML
            root
            HTML,
            $html,
            "Level '0' must render the exact text without indentation arrows.",
        );
    }

    public function testRenderTimeCellExposesFullTimestampInTitleAttribute(): void
    {
        self::assertSame(
            <<<HTML
            <span title="2023-11-14 22:13:20.789">22:13:20.789</span>
            HTML,
            ProfileCellRenderer::renderTimeCell(self::makeRow(timestamp: 1_700_000_000_789.0)),
            'Full timestamp must sit in the `title` attribute.',
        );
    }

    public function testRenderTimeCellFormatsMillisecondTimestampAsHmsWithMillis(): void
    {
        $html = ProfileCellRenderer::renderTimeCell(self::makeRow(timestamp: 1_700_000_000_789.0));

        self::assertSame(
            <<<HTML
            <span title="2023-11-14 22:13:20.789">22:13:20.789</span>
            HTML,
            $html,
            "Visible text must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeCellKeepsMillisecondsBelowTheNextBoundary(): void
    {
        $html = ProfileCellRenderer::renderTimeCell(self::makeRow(timestamp: 1_500.5));

        self::assertSame(
            <<<HTML
            <span title="1970-01-01 00:00:01.500">00:00:01.500</span>
            HTML,
            $html,
            'Sub-millisecond fractions must not advance the rendered millisecond value.',
        );
    }

    public function testRenderTimeCellPadsMillisecondsWithLeadingZeros(): void
    {
        $html = ProfileCellRenderer::renderTimeCell(
            self::makeRow(timestamp: 1_700_000_000_005.0),
        );

        self::assertSame(
            <<<HTML
            <span title="2023-11-14 22:13:20.005">22:13:20.005</span>
            HTML,
            $html,
            "Milliseconds below '100' must be zero-padded to three digits.",
        );
    }

    private static function makeRow(
        float $timestamp = 0.0,
        float $duration = 1.0,
        string $category = 'app',
        string $info = 'token',
        int $level = 0,
        int $seq = 0,
    ): ProfileRow {
        return new ProfileRow(
            timestamp: $timestamp,
            duration: $duration,
            category: $category,
            info: $info,
            level: $level,
            seq: $seq,
            memory: 0,
            memoryDiff: 0,
            trace: [],
        );
    }
}

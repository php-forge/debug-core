<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\History;

use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\View\History\{HistoryCellRenderer, HistoryRow, HistoryStatusBucket, HistorySummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function date;

/**
 * Unit tests for {@see HistoryCellRenderer} covering the per-column rendering helpers, the row-attributes builder
 * (`data-yii-debug-*` attributes for the sidebar cursor JS) and the summary header composition.
 */
#[Group('view')]
#[Group('history')]
final class HistoryCellRendererTest extends TestCase
{
    public function testBuildRowAttributesAddsDataAttributesForCursorJs(): void
    {
        $row = self::row([
            'tag' => 'abc',
            'method' => 'GET',
            'url' => '/path',
            'statusCode' => 200,
            'time' => 1_700_000_000,
            'ajax' => true,
        ]);

        $options = HistoryCellRenderer::buildRowAttributes($row, false);

        self::assertSame(
            [
                'tag' => 'abc',
                'method' => 'GET',
                'url' => '/path',
                'status' => '200',
                'time' => date('H:i:s', 1_700_000_000),
                'ajax' => '1',
            ],
            [
                'tag' => $options['data-yii-debug-tag'] ?? null,
                'method' => $options['data-yii-debug-method'] ?? null,
                'url' => $options['data-yii-debug-url'] ?? null,
                'status' => $options['data-yii-debug-status'] ?? null,
                'time' => $options['data-yii-debug-time'] ?? null,
                'ajax' => $options['data-yii-debug-ajax'] ?? null,
            ],
            'Row data-yii-debug-* attributes must mirror the typed row.',
        );
        self::assertArrayNotHasKey(
            'class',
            $options,
            'Non-critical rows must not carry a row class.',
        );
    }

    public function testBuildRowAttributesFlagsCriticalStatusCodesWithDangerHighlight(): void
    {
        $options = HistoryCellRenderer::buildRowAttributes(self::row(['statusCode' => 500]), true);

        self::assertIsString(
            $options['class'] ?? null,
            'class entry must be a string.',
        );
        self::assertStringContainsString(
            'yii-debug-row-danger',
            $options['class'],
            'Critical status codes must surface the danger highlight class.',
        );
    }

    public function testRenderAjaxCellMapsBoolToYesOrNo(): void
    {
        self::assertSame(
            'Yes',
            HistoryCellRenderer::renderAjaxCell(self::row(['ajax' => true])),
            "Boolean ajax value must map to 'Yes'.",
        );
        self::assertSame(
            'No',
            HistoryCellRenderer::renderAjaxCell(self::row(['ajax' => false])),
            "Boolean ajax value must map to 'No'.",
        );
    }

    public function testRenderDurationCellFormatsMilliseconds(): void
    {
        self::assertSame(
            '125 ms',
            HistoryCellRenderer::renderDurationCell(self::row(['processingTime' => 0.125]), 0.0),
            "Seconds must format as 'X ms'.",
        );
        self::assertSame(
            '2,000 ms',
            HistoryCellRenderer::renderDurationCell(self::row(['processingTime' => 2.0]), 0.0),
            'Second-scale durations must keep the thousands separator.',
        );
    }

    public function testRenderDurationCellScalesGaugeAgainstPageMaximum(): void
    {
        $html = HistoryCellRenderer::renderDurationCell(self::row(['processingTime' => 0.125]), 0.25);

        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">125 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            $html,
            'Rail must sit at half the page maximum.',
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">250 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            HistoryCellRenderer::renderDurationCell(self::row(['processingTime' => 0.25]), 0.25),
            'The slowest row must fill its rail.',
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 0%;'><span class="yii-debug-gauge-value">0 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            HistoryCellRenderer::renderDurationCell(self::row(['processingTime' => 0.0]), 0.25),
            'A zero measurement must show an empty rail.',
        );
    }

    public function testRenderDurationCellShowsNotSetWhenMissing(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-not-set">(not set)</span>
            HTML,
            HistoryCellRenderer::renderDurationCell(self::row([]), 0.25),
            'Missing duration must surface the muted placeholder.',
        );

    }

    public function testRenderMemoryCellFormatsMb(): void
    {
        self::assertSame(
            '2.000 MB',
            HistoryCellRenderer::renderMemoryCell(self::row(['peakMemory' => 2097152]), 0),
            "Bytes must format as 'X.XXX MB'.",
        );
    }

    public function testRenderMemoryCellScalesGaugeAgainstPageMaximum(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">2.000 MB</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            HistoryCellRenderer::renderMemoryCell(self::row(['peakMemory' => 2097152]), 4194304),
            'Rail must sit at half the page maximum.',
        );

    }

    public function testRenderMemoryCellShowsNotSetWhenMissing(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-not-set">(not set)</span>
            HTML,
            HistoryCellRenderer::renderMemoryCell(self::row([]), 4194304),
            'Missing peak memory must surface the muted placeholder.',
        );

    }

    public function testRenderMethodCellRendersVocabularyColoredText(): void
    {
        self::assertSame(
            '<span class="yii-debug-method yii-debug-verb-get">GET</span>',
            HistoryCellRenderer::renderMethodCell(self::row(['method' => 'GET'])),
            "GET must wear the 'get' verb class.",
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-method yii-debug-verb-put">PATCH</span>
            HTML,
            HistoryCellRenderer::renderMethodCell(self::row(['method' => 'PATCH'])),
            "PATCH must share the 'put' verb hue.",
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-method yii-debug-verb-other">COMMAND</span>
            HTML,
            HistoryCellRenderer::renderMethodCell(self::row(['method' => 'COMMAND'])),
            "COMMAND must fall back to the 'other' verb.",
        );
    }

    public function testRenderMethodCellReturnsEmptyStringForUncapturedMethod(): void
    {
        self::assertSame(
            '',
            HistoryCellRenderer::renderMethodCell(self::row(['method' => ''])),
            'An uncaptured method must render nothing.',
        );
    }

    public function testRenderSqlCountCellEmitsWarningGlyphWhenCountIsCritical(): void
    {
        $row = self::row(['tag' => 'flood', 'sqlCount' => 500, 'excessiveCallersCount' => 0]);

        self::assertSame(
            <<<HTML
            <a href="/debug/view?panel=db&amp;tag=flood" title="Executed 500 database queries.">500 <span title="Too many queries. Allowed count is 100">⚠</span></a>
            HTML,
            HistoryCellRenderer::renderSqlCountCell($row, '/debug/view?panel=db&tag=flood', true, 100),
            'Critical counts must surface the warning glyph.',
        );


    }

    public function testRenderSqlCountCellPluralizesExcessiveCallersCount(): void
    {
        $row = self::row(['tag' => 'flood', 'sqlCount' => 10, 'excessiveCallersCount' => 4]);

        self::assertSame(
            <<<HTML
            <a href="/db" title="Executed 10 database queries.">10 <span title="4 callers are making too many calls.">⚠</span></a>
            HTML,
            HistoryCellRenderer::renderSqlCountCell($row, '/db', false, 100),
            'Multiple excessive callers must surface the plural tooltip form.',
        );
    }

    public function testRenderSqlCountCellRendersPlainCountWhenNotCritical(): void
    {
        $row = self::row(['tag' => 'low', 'sqlCount' => 3, 'excessiveCallersCount' => 0]);

        self::assertSame(
            <<<HTML
            <a href="/db" title="Executed 3 database queries.">3</a>
            HTML,
            HistoryCellRenderer::renderSqlCountCell($row, '/db', false, 100),
            'Plain SQL count must surface as the bare integer.',
        );

    }

    public function testRenderSqlCountCellSingularizesSingleExcessiveCaller(): void
    {
        $row = self::row(['tag' => 'flood', 'sqlCount' => 10, 'excessiveCallersCount' => 1]);

        self::assertSame(
            <<<HTML
            <a href="/db" title="Executed 10 database queries.">10 <span title="1 caller is making too many calls.">⚠</span></a>
            HTML,
            HistoryCellRenderer::renderSqlCountCell($row, '/db', false, 100),
            'A single excessive caller must surface the singular tooltip form.',
        );
    }

    public function testRenderStatusCellMapsCommandWithZeroToSuccess(): void
    {
        self::assertSame(
            '<span class="yii-debug-badge yii-debug-status-2xx">200</span>',
            HistoryCellRenderer::renderStatusCell(self::row(['method' => 'COMMAND', 'statusCode' => 0])),
            "COMMAND with status '0' must display as status '200'.",
        );
    }

    public function testRenderStatusCellMapsRangeToStatusClass(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-badge yii-debug-status-2xx">200</span>
            HTML,
            HistoryCellRenderer::renderStatusCell(self::row(['statusCode' => 200])),
            "Status code '200' must map to '2xx'.",
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-badge yii-debug-status-3xx">301</span>
            HTML,
            HistoryCellRenderer::renderStatusCell(self::row(['statusCode' => 301])),
            "Status code '301' must map to '3xx'.",
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-badge yii-debug-status-4xx">404</span>
            HTML,
            HistoryCellRenderer::renderStatusCell(self::row(['statusCode' => 404])),
            "Status code '404' must map to '4xx'.",
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-badge yii-debug-status-5xx">500</span>
            HTML,
            HistoryCellRenderer::renderStatusCell(self::row(['statusCode' => 500])),
            "Status code '500' must map to '5xx'.",
        );
    }

    public function testRenderSummaryEchoesBucketPills(): void
    {
        $summary = new HistorySummary(
            totalRequests: 5,
            statusBuckets: [
                new HistoryStatusBucket(label: '2xx', count: 4, sampleCode: 200, variant: '2xx'),
                new HistoryStatusBucket(label: '4xx', count: 1, sampleCode: 404, variant: '4xx'),
            ],
            statusCodeFilter: null,
        );

        $html = HistoryCellRenderer::renderSummary(
            $summary,
            [
                '2xx' => '/debug?Debug%5BstatusCode%5D=200',
                '4xx' => '/debug?Debug%5BstatusCode%5D=404',
            ],
            '<label class="yii-debug-grid-pagesize"><select data-yii-debug-pagesize name="per-page"></select></label>',
        );

        self::assertSame(
            <<<HTML
            <header class="yii-debug-grid-summary">
            <span><strong>5</strong> captured requests</span><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-2xx" href="/debug?Debug%5BstatusCode%5D=200" title="Filter to 2xx responses (sample 200)"><strong>4</strong> 2xx</a><span class="yii-debug-grid-summary-sep">·</span><a class="yii-debug-grid-summary-stat-4xx" href="/debug?Debug%5BstatusCode%5D=404" title="Filter to 4xx responses (sample 404)"><strong>1</strong> 4xx</a><label class="yii-debug-grid-pagesize"><select data-yii-debug-pagesize name="per-page"></select></label>
            </header>
            HTML,
            $html,
            'Multiple requests must use the plural label.',
        );
    }

    public function testRenderSummaryReturnsEmptyWhenNoRequestsCaptured(): void
    {
        $summary = new HistorySummary(totalRequests: 0, statusBuckets: [], statusCodeFilter: null);

        self::assertSame(
            '',
            HistoryCellRenderer::renderSummary($summary, [], ''),
            'Empty manifest must skip the header entirely.',
        );
    }

    public function testRenderSummaryUsesSingularLabelForOneRequest(): void
    {
        $summary = new HistorySummary(totalRequests: 1, statusBuckets: [], statusCodeFilter: null);

        self::assertSame(
            <<<HTML
            <header class="yii-debug-grid-summary">
            <span><strong>1</strong> captured request</span>
            </header>
            HTML,
            HistoryCellRenderer::renderSummary($summary, [], ''),
            'One request must use the singular label.',
        );
    }

    public function testRenderTagCellLinksToPanelView(): void
    {
        self::assertSame(
            <<<HTML
            <a class="yii-debug-tag-link" href="/debug/view?tag=abc">abc</a>
            HTML,
            HistoryCellRenderer::renderTagCell(self::row(['tag' => 'abc']), '/debug/view?tag=abc'),
            'Tag link must carry the tag-link CSS class.',
        );
    }

    public function testRenderTimeCellRendersCompactClockWithFullTooltip(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-nowrap" title="2023-11-14 22:13:20">22:13:20</span>
            HTML,
            HistoryCellRenderer::renderTimeCell(self::row(['time' => 1_700_000_000])),
            'Time cell must carry the nowrap CSS class.',
        );
    }

    public function testRenderTimeCellShowsNotSetForZeroTimestamp(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-not-set">(not set)</span>
            HTML,
            HistoryCellRenderer::renderTimeCell(self::row(['time' => 0])),
            'Zero timestamps must surface the muted placeholder.',
        );
    }

    public function testRenderUrlCellWrapsUrlInTitleSpan(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-url-cell" title="http://example.test/path">http://example.test/path</span>
            HTML,
            HistoryCellRenderer::renderUrlCell(self::row(['url' => 'http://example.test/path'])),
            'URL cell must carry the dedicated class.',
        );

    }

    /**
     * @param array<string, mixed> $overrides
     */
    private static function row(array $overrides = []): HistoryRow
    {
        return HistoryRow::fromSummary(
            RequestSummary::fromArray(
                [
                    'tag' => 'tag-1',
                    'url' => 'https://example.test/',
                    'ajax' => false,
                    'method' => 'GET',
                    'ip' => '127.0.0.1',
                    'time' => 1_700_000_000.0,
                    'statusCode' => 200,
                    'sqlCount' => 0,
                    'excessiveCallersCount' => 0,
                    'mailCount' => 0,
                    'mailFiles' => [],
                    'processingTime' => null,
                    'peakMemory' => null,
                    ...$overrides,
                ],
            ),
        );
    }
}

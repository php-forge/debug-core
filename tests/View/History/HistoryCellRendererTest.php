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
 *
 * @since 0.1
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
                'ajax' => '1',
            ],
            [
                'tag' => $options['data-yii-debug-tag'] ?? null,
                'method' => $options['data-yii-debug-method'] ?? null,
                'url' => $options['data-yii-debug-url'] ?? null,
                'status' => $options['data-yii-debug-status'] ?? null,
                'ajax' => $options['data-yii-debug-ajax'] ?? null,
            ],
            'Row data-yii-debug-* attributes must mirror the typed row.',
        );
        self::assertArrayNotHasKey('class', $options, 'Non-critical rows must not carry a row class.');
    }

    public function testBuildRowAttributesFlagsCriticalStatusCodesWithDangerHighlight(): void
    {
        $options = HistoryCellRenderer::buildRowAttributes(self::row(['statusCode' => 500]), true);

        self::assertIsString($options['class'] ?? null, 'class entry must be a string.');
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
            '<span class="yii-debug-gauge" style=\'--yii-debug-gauge: 50%;\'>'
            . '<span class="yii-debug-gauge-value">125 ms</span>'
            . '<span class="yii-debug-gauge-bar" aria-hidden="true"></span>'
            . '</span>',
            $html,
            'Rail must sit at half the page maximum.',
        );
        self::assertStringContainsString(
            '--yii-debug-gauge: 100%;',
            HistoryCellRenderer::renderDurationCell(self::row(['processingTime' => 0.25]), 0.25),
            'The slowest row must fill its rail.',
        );
        self::assertStringContainsString(
            '--yii-debug-gauge: 0%;',
            HistoryCellRenderer::renderDurationCell(self::row(['processingTime' => 0.0]), 0.25),
            'A zero measurement must show an empty rail.',
        );
    }

    public function testRenderDurationCellShowsNotSetWhenMissing(): void
    {
        $html = HistoryCellRenderer::renderDurationCell(self::row([]), 0.25);

        self::assertStringContainsString('(not set)', $html, 'Missing duration must surface the muted placeholder.');
        self::assertStringNotContainsString('yii-debug-gauge', $html, 'Missing duration must not draw a rail.');
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
        $html = HistoryCellRenderer::renderMemoryCell(self::row(['peakMemory' => 2097152]), 4194304);

        self::assertStringContainsString('--yii-debug-gauge: 50%;', $html, 'Rail must sit at half the page maximum.');
        self::assertStringContainsString('2.000 MB', $html, 'Readout must keep its formatted value.');
    }

    public function testRenderMemoryCellShowsNotSetWhenMissing(): void
    {
        $html = HistoryCellRenderer::renderMemoryCell(self::row([]), 4194304);

        self::assertStringContainsString('(not set)', $html, 'Missing peak memory must surface the muted placeholder.');
        self::assertStringNotContainsString('yii-debug-gauge', $html, 'Missing peak memory must not draw a rail.');
    }

    public function testRenderMethodCellRendersVocabularyColoredText(): void
    {
        self::assertSame(
            '<span class="yii-debug-method yii-debug-verb-get">GET</span>',
            HistoryCellRenderer::renderMethodCell(self::row(['method' => 'GET'])),
            "GET must wear the 'get' verb class.",
        );
        self::assertStringContainsString(
            'yii-debug-verb-put',
            HistoryCellRenderer::renderMethodCell(self::row(['method' => 'PATCH'])),
            "PATCH must share the 'put' verb hue.",
        );
        self::assertStringContainsString(
            'yii-debug-verb-other',
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

        $html = HistoryCellRenderer::renderSqlCountCell($row, '/debug/view?panel=db&tag=flood', true, 100);

        self::assertStringContainsString('⚠', $html, 'Critical counts must surface the warning glyph.');
        self::assertStringContainsString('Too many queries', $html, 'Warning tooltip must explain the breach.');
        self::assertStringContainsString(
            'panel=db&amp;tag=flood',
            $html,
            'SQL count must link to the request database panel.',
        );
    }

    public function testRenderSqlCountCellPluralizesExcessiveCallersCount(): void
    {
        $row = self::row(['tag' => 'flood', 'sqlCount' => 10, 'excessiveCallersCount' => 4]);

        self::assertStringContainsString(
            '4 callers are making too many calls.',
            HistoryCellRenderer::renderSqlCountCell($row, '/db', false, 100),
            'Multiple excessive callers must surface the plural tooltip form.',
        );
    }

    public function testRenderSqlCountCellRendersPlainCountWhenNotCritical(): void
    {
        $row = self::row(['tag' => 'low', 'sqlCount' => 3, 'excessiveCallersCount' => 0]);

        $html = HistoryCellRenderer::renderSqlCountCell($row, '/db', false, 100);

        self::assertStringContainsString('>3<', $html, 'Plain SQL count must surface as the bare integer.');
        self::assertStringNotContainsString('⚠', $html, 'Non-critical counts must NOT carry the warning glyph.');
    }

    public function testRenderSqlCountCellSingularizesSingleExcessiveCaller(): void
    {
        $row = self::row(['tag' => 'flood', 'sqlCount' => 10, 'excessiveCallersCount' => 1]);

        self::assertStringContainsString(
            '1 caller is making too many calls.',
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
        self::assertStringContainsString(
            'yii-debug-badge yii-debug-status-2xx',
            HistoryCellRenderer::renderStatusCell(self::row(['statusCode' => 200])),
            "Status code '200' must map to '2xx'.",
        );
        self::assertStringContainsString(
            'yii-debug-status-3xx',
            HistoryCellRenderer::renderStatusCell(self::row(['statusCode' => 301])),
            "Status code '301' must map to '3xx'.",
        );
        self::assertStringContainsString(
            'yii-debug-status-4xx',
            HistoryCellRenderer::renderStatusCell(self::row(['statusCode' => 404])),
            "Status code '404' must map to '4xx'.",
        );
        self::assertStringContainsString(
            'yii-debug-status-5xx',
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
            ['2xx' => '/debug?Debug%5BstatusCode%5D=200', '4xx' => '/debug?Debug%5BstatusCode%5D=404'],
            '<label class="yii-debug-grid-pagesize"><select data-yii-debug-pagesize name="per-page"></select></label>',
        );

        self::assertStringContainsString('captured requests', $html, 'Multiple requests must use the plural label.');
        self::assertStringContainsString(
            'yii-debug-grid-summary-stat-2xx',
            $html,
            "'2xx' pill must carry the '2xx' status class.",
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary-stat-4xx',
            $html,
            "'4xx' pill must carry the '4xx' status class.",
        );
        self::assertStringContainsString(
            'Debug%5BstatusCode%5D=200',
            $html,
            "The '2xx' bucket must link to its sample status filter.",
        );
        self::assertStringContainsString(
            'Debug%5BstatusCode%5D=404',
            $html,
            "The '4xx' bucket must link to its sample status filter.",
        );
        self::assertStringContainsString(
            'yii-debug-grid-pagesize',
            $html,
            'History summary must include the shared page-size selector.',
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

        $html = HistoryCellRenderer::renderSummary($summary, [], '');

        self::assertStringContainsString('captured request', $html, 'One request must use the singular label.');
        self::assertStringNotContainsString('captured requests', $html, 'One request must not use the plural label.');
    }

    public function testRenderTagCellLinksToPanelView(): void
    {
        $html = HistoryCellRenderer::renderTagCell(self::row(['tag' => 'abc']), '/debug/view?tag=abc');

        self::assertStringContainsString('yii-debug-tag-link', $html, 'Tag link must carry the tag-link CSS class.');
        self::assertStringContainsString('abc', $html, 'Tag value must surface inside the link.');
        self::assertStringContainsString('tag=abc', $html, 'Tag cell must link to the matching request view.');
    }

    public function testRenderTimeCellRendersCompactClockWithFullTooltip(): void
    {
        $html = HistoryCellRenderer::renderTimeCell(self::row(['time' => 1_700_000_000]));

        self::assertStringContainsString('yii-debug-nowrap', $html, 'Time cell must carry the nowrap CSS class.');
        self::assertStringContainsString(
            'title="' . date('Y-m-d H:i:s', 1_700_000_000) . '"',
            $html,
            'Time cell must carry the full datetime tooltip.',
        );
        self::assertStringContainsString(
            '>' . date('H:i:s', 1_700_000_000) . '<',
            $html,
            'Time cell must render the compact clock string.',
        );
    }

    public function testRenderTimeCellShowsNotSetForZeroTimestamp(): void
    {
        self::assertStringContainsString(
            '(not set)',
            HistoryCellRenderer::renderTimeCell(self::row(['time' => 0])),
            'Zero timestamps must surface the muted placeholder.',
        );
    }

    public function testRenderUrlCellWrapsUrlInTitleSpan(): void
    {
        $html = HistoryCellRenderer::renderUrlCell(self::row(['url' => 'http://example.test/path']));

        self::assertStringContainsString('yii-debug-url-cell', $html, 'URL cell must carry the dedicated class.');
        self::assertStringContainsString('http://example.test/path', $html, 'URL value must render inside the cell.');
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

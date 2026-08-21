<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\Db\{DbQueryRenderer, QueryRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DbQueryRenderer} covering the typed cell renderers used by the queries grid (type pill,
 * timestamp formatting, duration formatting, rows fallback, query column with optional trace and EXPLAIN toggle).
 */
#[Group('panel')]
#[Group('db')]
final class DbQueryRendererTest extends TestCase
{
    public function testCanBeExplainedReturnsFalseForUnsupportedVerb(): void
    {
        self::assertFalse(
            DbQueryRenderer::canBeExplained('PRAGMA'),
            'PRAGMA must not be marked as EXPLAIN-able.',
        );
        self::assertFalse(
            DbQueryRenderer::canBeExplained(''),
            'Empty verb must not be marked as EXPLAIN-able.',
        );
    }

    public function testCanBeExplainedReturnsTrueForSupportedVerbs(): void
    {
        foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH'] as $verb) {
            self::assertTrue(
                DbQueryRenderer::canBeExplained($verb),
                "Verb '{$verb}' must be EXPLAIN-able.",
            );
            self::assertTrue(
                DbQueryRenderer::canBeExplained(strtolower($verb)),
                "Verb '{$verb}' must be EXPLAIN-able regardless of case.",
            );
        }
    }


    public function testRenderDurationCellFormatsDurationToOneDecimalMillisecond(): void
    {
        self::assertSame(
            '12.5 ms',
            DbQueryRenderer::renderDurationCell(self::makeRow(duration: 12.5)),
            'Duration must keep one decimal.',
        );
        self::assertSame(
            '0.0 ms',
            DbQueryRenderer::renderDurationCell(self::makeRow(duration: 0.0)),
            "Zero duration must render as '0.0 ms'.",
        );
    }

    public function testRenderQueryCellEmitsExplainToggleWithBuiltUrl(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(type: 'SELECT', seq: 7),
            self::traceLine(),
            true,
            self::makeUrlBuilder('request-tag-1'),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div><div class="yii-debug-db-explain">
            <a class="yii-debug-db-explain-toggle" href="/debug/db-explain?seq=7&amp;tag=request-tag-1" role="button" aria-controls="yii-debug-db-explain-7" aria-expanded="false" aria-label="Toggle EXPLAIN output"><span class="yii-debug-db-explain-chevron" aria-hidden="true">›</span><span class="yii-debug-db-explain-label">Explain</span></a><div class="yii-debug-db-explain-text" id="yii-debug-db-explain-7">
            </div>
            </div>
            HTML,
            $html,
            'Explain toggle wrapper must be present.',
        );
    }

    public function testRenderQueryCellEmitsTraceListWhenTracePresent(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            self::traceLine(),
            false,
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div><ul class="yii-debug-trace">
            <li>
            /app/User.php:
            </li>
            </ul>
            HTML,
            $html,
            'Trace list must carry the dedicated class.',
        );
    }

    public function testRenderQueryCellEscapesQueryContent(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(query: '<script>'),
            self::traceLine(),
            false,
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            &lt;script&gt;
            </div>
            HTML,
            $html,
            'Query content must be HTML-escaped.',
        );

    }

    public function testRenderQueryCellOmitsExplainToggleWhenHasExplainIsFalse(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(type: 'SELECT'),
            self::traceLine(),
            false,
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            HTML,
            $html,
            'Explain toggle must be hidden when the driver does not support EXPLAIN.',
        );
    }

    public function testRenderQueryCellOmitsExplainToggleWhenTypeIsNotExplainable(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(type: 'PRAGMA'),
            self::traceLine(),
            true,
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            HTML,
            $html,
            'PRAGMA must not produce an explain toggle.',
        );
    }

    public function testRenderQueryCellOmitsTraceListWhenTraceEmpty(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(trace: []),
            self::traceLine(),
            false,
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            HTML,
            $html,
            'Empty trace must omit the trace list entirely.',
        );
    }

    public function testRenderQueryCellWrapsSqlInTheDebugSqlContainer(): void
    {
        $html = DbQueryRenderer::renderQueryCell(
            self::makeRow(query: 'SELECT 1'),
            self::traceLine(),
            false,
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-db-sql">
            <span class="yii-debug-sql-kw">SELECT</span> <span class="yii-debug-sql-num">1</span>
            </div>
            HTML,
            $html,
            'SQL must be wrapped in the dedicated container.',
        );
    }

    public function testRenderRowsCellShowsEnDashWhenRowsAreUnknown(): void
    {
        self::assertSame(
            '–',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: null)),
            'Missing rows must render the en-dash placeholder.',
        );
    }

    public function testRenderRowsCellShowsRowOrRowsBasedOnCount(): void
    {
        self::assertSame(
            '1 row',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: 1)),
            'Single row must use the singular noun.'
        );
        self::assertSame(
            '5 rows',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: 5)),
            'Multiple rows must use the plural noun.'
        );
        self::assertSame(
            '0 rows',
            DbQueryRenderer::renderRowsCell(self::makeRow(rows: 0)),
            'Zero rows must still pluralize.'
        );
    }

    public function testRenderTimeCellFormatsMillisecondTimestampAsHmsWithMillis(): void
    {
        $timestampMs = 1_700_000_000_789.0;

        $expected = date('H:i:s.', 1_700_000_000) . '789';

        $html = DbQueryRenderer::renderTimeCell(self::makeRow(timestamp: $timestampMs));

        self::assertSame(
            $expected,
            $html,
            "Timestamp must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderTimeCellKeepsMillisecondsBelowTheNextBoundary(): void
    {
        self::assertSame(
            date('H:i:s.', 1) . '500',
            DbQueryRenderer::renderTimeCell(self::makeRow(timestamp: 1_500.5)),
            'Sub-millisecond fractions must not advance the rendered millisecond value.',
        );
    }

    public function testRenderTypeCellMapsInsertToPostAndDeleteToDeleteVerbs(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-db-type yii-debug-verb-post">INSERT</span>
            HTML,
            DbQueryRenderer::renderTypeCell(self::makeRow(type: 'INSERT')),
            "INSERT must map to the 'post' verb.",
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-db-type yii-debug-verb-delete">DELETE</span>
            HTML,
            DbQueryRenderer::renderTypeCell(self::makeRow(type: 'DELETE')),
            "DELETE must map to the 'delete' verb.",
        );
    }

    public function testRenderTypeCellWrapsTypeInAColoredBadge(): void
    {
        $html = DbQueryRenderer::renderTypeCell(self::makeRow(type: 'SELECT'));

        self::assertSame(
            <<<HTML
            <span class="yii-debug-db-type yii-debug-verb-get">SELECT</span>
            HTML,
            $html,
            "SELECT must use the 'get' verb.",
        );

    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function makeRow(
        string $type = 'SELECT',
        string $query = 'SELECT 1',
        float $duration = 1.0,
        array $trace = [],
        string $traceHash = '',
        float $timestamp = 0.0,
        int $seq = 0,
        int $duplicate = 1,
        int|null $rows = null,
    ): QueryRow {
        return new QueryRow(
            type: $type,
            query: $query,
            duration: $duration,
            trace: $trace,
            traceHash: $traceHash,
            timestamp: $timestamp,
            seq: $seq,
            duplicate: $duplicate,
            rows: $rows,
        );
    }

    /**
     * Builds a deterministic explain-URL builder so tests can assert the rendered href without needing an active
     * controller context.
     *
     * @return callable(int): string
     */
    private static function makeUrlBuilder(string $tag = 'tag'): callable
    {
        return static fn(int $seq): string => "/debug/db-explain?seq={$seq}&tag={$tag}";
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

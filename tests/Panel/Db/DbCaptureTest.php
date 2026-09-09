<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use PHPForge\Debug\Panel\Db\{DbSnapshot, DbSummary, DbSummaryRenderer, QueryRow};
use PHPForge\Debug\Tests\Provider\DbCaptureProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DbCapture} covering interleaved rows, duplicate normalization, and query factories.
 */
#[Group('db')]
final class DbCaptureTest extends TestCase
{
    public function testCapturePreservesInterleavedRowsAndNormalizesExactDuplicates(): void
    {
        $rows = [
            QueryRow::create('SELECT 1', 1.25, 1000.0)
                ->withTrace([['file' => '/a', 'line' => 3]])
                ->withTraceHash('first')
                ->withSequence(8)
                ->withDuplicate(99)
                ->withRows(4),
            QueryRow::create('UPDATE t SET a=1', 2.5, 2000.0)
                ->withTraceHash('second')
                ->withSequence(4)
                ->withDuplicate(99)
                ->withRows(0),
            QueryRow::create('SELECT 1', 3.25, 3000.0)
                ->withTraceHash('first')
                ->withSequence(6)
                ->withDuplicate(99),
        ];

        $snapshot = DbSnapshot::capture($rows);

        $expected = [];

        foreach ($rows as $index => $row) {
            $data = $row->jsonSerialize();
            $data['duplicate'] = $index === 1 ? 1 : 2;
            $expected[] = $data;
        }

        self::assertSame(
            ['entries' => $expected],
            $snapshot->jsonSerialize(),
            'Only exact duplicate counts may change.',
        );
        self::assertSame(
            ['entries' => []],
            DbSnapshot::capture([])->jsonSerialize(),
            'Empty capture must stay canonical.',
        );
        self::assertEquals(
            $snapshot,
            DbSnapshot::fromArray($snapshot->jsonSerialize(), '$.db'),
            'Capture must round-trip.',
        );
        self::assertSame(
            99,
            $rows[0]->getDuplicate(),
            'Capture must not mutate the source row.',
        );
    }

    #[DataProviderExternal(DbCaptureProvider::class, 'explainableStatements')]
    public function testIsExplainable(string $type, string $query, bool $expected): void
    {
        self::assertSame(
            $expected,
            self::makeRow($type, $query)->isExplainable(),
            'EXPLAIN eligibility must match the query type and statement count.',
        );
    }

    public function testQueryFactoriesPreserveFieldsAndReturnImmutableCopies(): void
    {
        $row = QueryRow::create(" \nselect 1", 2.5, 1234.0);

        self::assertSame(
            [
                'type' => 'SELECT',
                'query' => " \nselect 1",
                'duration' => 2.5,
                'trace' => [],
                'traceHash' => '',
                'timestamp' => 1234.0,
                'seq' => 0,
                'duplicate' => 1,
                'rows' => null,
            ],
            $row->jsonSerialize(),
            'Factory defaults must use milliseconds and leave unavailable metrics unset.',
        );
        self::assertSame(
            '',
            QueryRow::create('123 SELECT', 0.0, 0.0)->getType(),
            'Non-word prefixes must have no SQL verb.',
        );

        $source = QueryRow::create('UPDATE t', 9.5, 99.0)
            ->withTrace([['file' => '/old']])
            ->withTraceHash('old')
            ->withSequence(5)
            ->withDuplicate(4)
            ->withRows(2);

        $trace = [['file' => '/new', 'line' => 7]];

        $changed = $source
            ->withTrace($trace)
            ->withSequence(8);
        $expected = $source->jsonSerialize();

        $expected['trace'] = $trace;

        $expected['traceHash'] = hash('sha256', json_encode($trace, JSON_THROW_ON_ERROR));

        $expected['seq'] = 8;

        self::assertSame(
            $expected,
            $changed->jsonSerialize(),
            'Withers must preserve all unrelated fields.',
        );
        self::assertSame(
            [],
            $changed->withTrace([])->getTrace(),
            'An empty trace must clear the frames.',
        );
        self::assertSame(
            '',
            $changed->withTrace([])->getTraceHash(),
            'An empty trace must not create a synthetic caller.',
        );

        $duplicated = $source->withDuplicate(3);
        $expectedDuplicate = $source->jsonSerialize();

        $expectedDuplicate['duplicate'] = 3;

        self::assertSame(
            $expectedDuplicate,
            $duplicated->jsonSerialize(),
            'The duplicate count must be the only replaced field.',
        );

        $counted = $source->withRows(5);
        $expectedRows = $source->jsonSerialize();

        $expectedRows['rows'] = 5;

        self::assertSame(
            $expectedRows,
            $counted->jsonSerialize(),
            'The reported row count must be the only replaced field.',
        );
        self::assertNull(
            $counted->withRows(null)->getRows(),
            'An unreported row count must clear the field.',
        );
        self::assertSame(
            2,
            $source->getRows(),
            'The source row count must stay untouched.',
        );
    }

    public function testSummaryAggregatesRequestWideCountDurationDuplicatesAndCallers(): void
    {
        $rows = [
            QueryRow::create('SELECT 1', 1.25, 1000.0)
                ->withTrace([['file' => '/a', 'line' => 3]])
                ->withTraceHash('first')
                ->withSequence(8)
                ->withDuplicate(99)
                ->withRows(4),
            QueryRow::create('UPDATE t SET a=1', 2.5, 2000.0)
                ->withTraceHash('second')
                ->withSequence(4)
                ->withDuplicate(99)
                ->withRows(0),
            QueryRow::create('SELECT 1', 3.25, 3000.0)
                ->withTraceHash('first')
                ->withSequence(6)
                ->withDuplicate(99),
        ];

        $summary = new DbSummary(DbSnapshot::capture($rows)->entries());

        self::assertSame(
            3,
            $summary->count,
            'Query count must include all rows.',
        );
        self::assertSame(
            7.0,
            $summary->duration,
            'Durations must be summed in milliseconds.',
        );
        self::assertSame(
            2,
            $summary->duplicates,
            'Duplicated rows, not extra executions, must be counted.',
        );
        self::assertSame(
            ['first' => 2, 'second' => 1],
            $summary->callers,
            'Caller hashes must be counted independently.',
        );
        self::assertSame(
            ['SELECT' => 'SELECT', 'UPDATE' => 'UPDATE'],
            $summary->types,
            'Types must retain encounter order.',
        );
        self::assertSame(
            0,
            $summary->excessiveCallerCount(null),
            'A null threshold must disable caller warnings.',
        );
        self::assertSame(
            1,
            $summary->excessiveCallerCount(2),
            'The inclusive caller threshold must count matching groups.',
        );
        self::assertSame(
            0,
            $summary->excessiveCallerCount(3),
            'Below-threshold callers must not be counted.',
        );
    }

    public function testSummaryFlagsCriticalCountsAndExcessiveCallers(): void
    {
        $summary = self::makeSummary(['a', 'a', 'b']);

        self::assertFalse(
            $summary->isCritical(null),
            'A `null` threshold must disable the check.',
        );
        self::assertFalse(
            $summary->isCritical(3),
            'A count equal to the threshold must stay below critical.',
        );
        self::assertTrue(
            $summary->isCritical(2),
            'A count above the threshold must be critical.',
        );
        self::assertFalse(
            $summary->hasWarning(null, null),
            'Disabled thresholds must raise no warning.',
        );
        self::assertFalse(
            $summary->hasWarning(3, 5),
            'Unexceeded thresholds must raise no warning.',
        );
        self::assertTrue(
            $summary->hasWarning(2, null),
            'A critical count alone must raise a warning.',
        );
        self::assertTrue(
            $summary->hasWarning(null, 2),
            'An excessive call site alone must raise a warning.',
        );
    }

    public function testSummaryIgnoresRowsWithoutACapturedTrace(): void
    {
        $uncaptured = self::makeSummary(['', '']);

        self::assertSame(
            [],
            $uncaptured->callers,
            'An empty hash must not open a caller bucket.',
        );
        self::assertSame(
            0,
            $uncaptured->excessiveCallerCount(1),
            'A phantom caller must never be flagged.',
        );
        self::assertSame(
            2,
            $uncaptured->count,
            'Uncounted callers must still be counted as queries.',
        );

        $mixed = self::makeSummary(['', 'a', 'a', '']);

        self::assertSame(
            ['a' => 2],
            $mixed->callers,
            'Only captured traces must be accumulated.',
        );
        self::assertSame(
            1,
            $mixed->excessiveCallerCount(2),
            'Captured callers must still reach the threshold.',
        );
    }

    public function testSummaryRendererUsesExactSharedMarkup(): void
    {
        $summary = new DbSummary(
            DbSnapshot::capture(
                [
                    QueryRow::create('SELECT 1', 1.25, 1000.0),
                    QueryRow::create('SELECT 1', 2.25, 2000.0),
                ],
            )->entries()
        );

        self::assertSame(
            <<<HTML
            <header class="yii-debug-grid-summary">
            <span><strong>2</strong> queries</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>3.500</strong> ms total</span><span class="yii-debug-grid-summary-sep">·</span><span class="yii-debug-grid-summary-stat-warn"><strong>2</strong> duplicated</span><select></select>
            </header>
            HTML,
            DbSummaryRenderer::render($summary, '<select></select>'),
            'Summary markup must remain shared across adapters.',
        );
        self::assertSame(
            <<<HTML
            <header class="yii-debug-grid-summary">
            <span><strong>0</strong> queries</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>0.000</strong> ms total</span>
            </header>
            HTML,
            DbSummaryRenderer::render(new DbSummary([])),
            'Empty summaries must omit duplicate warnings and optional controls.',
        );
    }

    public function testToolbarTitleReportsExecutedCountOrTheActiveWarnings(): void
    {
        $summary = self::makeSummary(['a', 'a', 'b']);

        self::assertSame(
            'Executed 3 database queries.',
            DbSummaryRenderer::toolbarTitle($summary, null, null),
            'Disabled thresholds must report the executed count.',
        );
        self::assertSame(
            'Executed 3 database queries.',
            DbSummaryRenderer::toolbarTitle($summary, 3, 5),
            'Unexceeded thresholds must report the executed count.',
        );
        self::assertSame(
            'Too many queries, allowed count is 2.',
            DbSummaryRenderer::toolbarTitle($summary, 2, null),
            'A critical count alone must report the allowed count.',
        );
        self::assertSame(
            '1 caller is making too many calls.',
            DbSummaryRenderer::toolbarTitle($summary, null, 2),
            'One flagged call site must use the singular wording.',
        );
        self::assertSame(
            '2 callers are making too many calls.',
            DbSummaryRenderer::toolbarTitle($summary, null, 1),
            'Several flagged call sites must use the plural wording.',
        );
        self::assertSame(
            "Too many queries, allowed count is 2.\n2 callers are making too many calls.",
            DbSummaryRenderer::toolbarTitle($summary, 2, 1),
            'Both sentences must join with a newline, critical count first.',
        );
    }

    private static function makeRow(string $type, string $query = 'SELECT 1'): QueryRow
    {
        return QueryRow::create($query, 1.0, 0.0)->withType($type);
    }

    /**
     * Builds a summary of one row per caller hash, so caller counts follow the repeated hashes.
     *
     * @param list<string> $traceHashes Caller hash of each captured row, in capture order.
     */
    private static function makeSummary(array $traceHashes): DbSummary
    {
        $rows = [];

        foreach ($traceHashes as $seq => $traceHash) {
            $rows[] = QueryRow::create("SELECT {$seq}", 1.0, 0.0)
                ->withType('SELECT')
                ->withTraceHash($traceHash)
                ->withSequence($seq);
        }

        return new DbSummary($rows);
    }
}

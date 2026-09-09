<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Comparison;

use PHPForge\Debug\Comparison\{PanelComparison, SnapshotComparison, SummaryMetricComparison};
use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Unit tests for {@see SnapshotComparison} pairing summary metrics with panel structural differences.
 */
#[Group('history')]
final class SnapshotComparisonTest extends TestCase
{
    public function testBetweenRetainsSnapshotsAndSharedComparisonResults(): void
    {
        $baseline = self::snapshot(['db' => ['queries' => 1]]);
        $target = self::snapshot(['db' => ['queries' => 2]]);

        $comparison = SnapshotComparison::between(
            $baseline,
            $target,
            ['db' => 'Database'],
        );

        self::assertSame(
            $baseline,
            $comparison->baseline,
            'The baseline snapshot must be retained as given.',
        );
        self::assertSame(
            $target,
            $comparison->target,
            'The target snapshot must be retained as given.',
        );
        self::assertSame(
            [
                'Status',
                'Method',
                'AJAX',
                'Duration',
                'Peak memory',
                'SQL queries',
                'Mail messages',
                'Excessive DB callers',
            ],
            array_map(static fn(SummaryMetricComparison $metric): string => $metric->label, $comparison->metrics),
            'Metrics must keep the canonical history order.',
        );
        self::assertSame(
            [['db', 'Database', 'Captured', 'Captured', 0, 0, 1, 0]],
            array_map(
                static fn(PanelComparison $panel): array => [
                    $panel->id,
                    $panel->label,
                    $panel->baselineState,
                    $panel->targetState,
                    $panel->added,
                    $panel->removed,
                    $panel->changed,
                    $panel->unchanged,
                ],
                $comparison->panels,
            ),
            'Panels must carry the configured label, states, and counts.',
        );
    }

    public function testHasDifferencesReportsChangedPanelPayloads(): void
    {
        $comparison = SnapshotComparison::between(
            self::snapshot(['db' => ['queries' => 1]]),
            self::snapshot(['db' => ['queries' => 2]]),
        );

        self::assertSame(
            [false, false, false, false, false, false, false, false],
            array_map(
                static fn(SummaryMetricComparison $metric): bool => $metric->hasDifference(),
                $comparison->metrics,
            ),
            'Identical summaries must leave every metric unchanged.',
        );
        self::assertTrue(
            $comparison->hasDifferences(),
            'A changed panel leaf must be reported.',
        );
    }

    public function testHasDifferencesReportsChangedSummaryMetrics(): void
    {
        $panels = ['db' => ['queries' => 1]];

        $comparison = SnapshotComparison::between(
            new DebugSnapshot(RequestSummary::create('capture')->withResponse(200), $panels, []),
            new DebugSnapshot(RequestSummary::create('capture')->withResponse(500), $panels, []),
        );

        self::assertSame(
            [0],
            array_map(
                static fn(PanelComparison $panel): int => $panel->differenceCount(),
                $comparison->panels,
            ),
            'Identical payloads must leave the panel without differences.',
        );
        self::assertTrue(
            $comparison->hasDifferences(),
            'A changed status metric must be reported.',
        );
    }

    public function testHasDifferencesReturnsFalseForIdenticalCaptures(): void
    {
        $panels = ['db' => ['queries' => 1]];

        self::assertFalse(
            SnapshotComparison::between(
                self::snapshot($panels),
                self::snapshot($panels),
            )->hasDifferences(),
            'Equal summaries and payloads must report no difference.',
        );
    }

    /**
     * Returns a capture carrying the given panel payloads and an otherwise empty summary.
     *
     * @param array<string, array<string, mixed>> $panels Serialized panel payloads indexed by panel ID.
     */
    private static function snapshot(array $panels): DebugSnapshot
    {
        return new DebugSnapshot(RequestSummary::create('capture'), $panels, []);
    }
}

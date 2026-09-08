<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Comparison;

use PHPForge\Debug\Comparison\PanelComparison;
use PHPForge\Debug\Storage\DebugSnapshot;
use PHPForge\Debug\Tests\Provider\PanelComparisonProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Locks the original adapter output before and after sharing panel comparison.
 */
#[Group('history')]
final class PanelComparisonTest extends TestCase
{
    /**
     * @param array<string, string> $labels
     * @param list<array{string, string, string, string, int, int, int, int}> $expected
     */
    #[DataProviderExternal(PanelComparisonProvider::class, 'comparisons')]
    public function testBetweenPreservesPanelContracts(
        DebugSnapshot $baseline,
        DebugSnapshot $target,
        array $labels,
        array $expected,
        bool $hasDifferences,
    ): void {
        $beforeBaseline = $baseline->jsonSerialize();
        $beforeTarget = $target->jsonSerialize();

        $panels = PanelComparison::between($baseline, $target, $labels);

        $actual = [];
        $actualCounts = [];
        $differenceCount = 0;

        foreach ($panels as $panel) {
            $differenceCount += $panel->differenceCount();
            $actualCounts[] = $panel->differenceCount();
            $actual[] = [
                $panel->id,
                $panel->label,
                $panel->baselineState,
                $panel->targetState,
                $panel->added,
                $panel->removed,
                $panel->changed,
                $panel->unchanged,
            ];
        }

        self::assertSame(
            $expected,
            $actual,
            'Panel IDs, labels, order, states, and counts must remain exact.',
        );
        self::assertSame(
            array_map(static fn(array $row): int => $row[4] + $row[5] + $row[6], $expected),
            $actualCounts,
            'Totals must add added, removed, and changed leaves.',
        );
        self::assertSame(
            $hasDifferences,
            $differenceCount > 0,
            'Difference detection must remain exact.'
        );
        self::assertSame(
            $beforeBaseline,
            $baseline->jsonSerialize(),
            'Baseline diagnostics must remain intact.',
        );
        self::assertSame(
            $beforeTarget,
            $target->jsonSerialize(),
            'Target diagnostics must remain intact.'
        );
    }
}

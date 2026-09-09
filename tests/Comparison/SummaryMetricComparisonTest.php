<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Comparison;

use PHPForge\Debug\Comparison\SummaryMetricComparison;
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\Tests\Provider\SummaryMetricComparisonProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Unit tests for {@see SummaryMetricComparison} validating metric contracts, boundaries, and formatted deltas.
 *
 * {@see SummaryMetricComparisonProvider} for test case data providers.
 */
#[Group('history')]
final class SummaryMetricComparisonTest extends TestCase
{
    /**
     * @param list<array{string, string, string, string, string, string|null}> $expected
     */
    #[DataProviderExternal(SummaryMetricComparisonProvider::class, 'summaries')]
    public function testBetweenPreservesAllMetricContracts(
        RequestSummary $baseline,
        RequestSummary $target,
        array $expected,
    ): void {
        $beforeBaseline = $baseline->jsonSerialize();
        $beforeTarget = $target->jsonSerialize();

        $metrics = SummaryMetricComparison::between(
            $baseline,
            $target,
        );

        $actual = array_map(self::row(...), $metrics);

        self::assertSame(
            $expected,
            $actual,
            'Labels, order, values, deltas, trends, and panel IDs must remain exact.',
        );
        self::assertSame(
            $beforeBaseline,
            $baseline->jsonSerialize(),
            'The baseline must remain unchanged.'
        );
        self::assertSame(
            $beforeTarget,
            $target->jsonSerialize(),
            'The target must remain unchanged.'
        );
    }

    /**
     * @param array{string, string, string, string, string, string|null} $expected
     */
    #[DataProviderExternal(SummaryMetricComparisonProvider::class, 'metrics')]
    public function testBetweenPreservesMetricBoundaries(
        RequestSummary $baseline,
        RequestSummary $target,
        int $index,
        array $expected,
    ): void {
        $metrics = SummaryMetricComparison::between($baseline, $target);

        if (!isset($metrics[$index])) {
            self::fail(
                'The metric must retain its canonical position.',
            );
        }

        self::assertSame(
            $expected,
            self::row($metrics[$index]),
            'Metric arithmetic and formatting must remain exact.'
        );
    }

    public function testHasDifferenceFollowsTheFormattedDelta(): void
    {
        $baseline = RequestSummary::create('baseline');

        self::assertSame(
            [false, false, false, false, false, false, false, false],
            self::differences(SummaryMetricComparison::between($baseline, $baseline)),
            'Identical summaries must leave every metric unchanged.',
        );
        self::assertSame(
            [true, false, false, false, false, false, false, false],
            self::differences(SummaryMetricComparison::between($baseline, $baseline->withResponse(500))),
            'Only the changed status metric must be reported.',
        );
    }

    /**
     * @param list<SummaryMetricComparison> $metrics
     *
     * @return list<bool>
     */
    private static function differences(array $metrics): array
    {
        return array_map(static fn(SummaryMetricComparison $metric): bool => $metric->hasDifference(), $metrics);
    }

    /**
     * @return array{string, string, string, string, string, string|null}
     */
    private static function row(SummaryMetricComparison $metric): array
    {
        return [
            $metric->label,
            $metric->baseline,
            $metric->target,
            $metric->delta,
            $metric->trend,
            $metric->panelId,
        ];
    }
}

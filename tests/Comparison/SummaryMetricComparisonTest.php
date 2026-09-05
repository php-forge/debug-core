<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Comparison;

use PHPForge\Debug\Comparison\SummaryMetricComparison;
use PHPForge\Debug\Storage\RequestSummary;
use PHPForge\Debug\Tests\Provider\SummaryMetricComparisonProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Tests summary metric compatibility with the original adapter calculations and formatting.
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
        $actual = [];

        foreach (SummaryMetricComparison::between($baseline, $target) as $metric) {
            $actual[] = self::row($metric);
        }

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

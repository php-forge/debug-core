<?php

declare(strict_types=1);

namespace PHPForge\Debug\Comparison;

use PHPForge\Debug\Storage\RequestSummary;

use function number_format;

/**
 * Calculates and formats request-summary metrics without depending on adapter presentation models.
 *
 * Results retain the history labels, order, units, trends, and related panel IDs used by both adapters.
 */
final readonly class SummaryMetricComparison
{
    private function __construct(
        public string $label,
        public string $baseline,
        public string $target,
        public string $delta,
        public string $trend,
        public string|null $panelId = null,
    ) {}

    /**
     * Compares summaries in the canonical history metric order.
     *
     * @return list<self>
     */
    public static function between(RequestSummary $baseline, RequestSummary $target): array
    {
        return [
            self::textMetric(
                'Status',
                self::status($baseline->statusCode),
                self::status($target->statusCode),
            ),
            self::textMetric(
                'Method',
                $baseline->method,
                $target->method,
            ),
            self::textMetric(
                'AJAX',
                self::yesNo($baseline->ajax),
                self::yesNo($target->ajax),
            ),
            self::nullableFloatMetric(
                'Duration',
                $baseline->processingTime,
                $target->processingTime,
                1000,
                'ms',
                'profiling',
            ),
            self::nullableFloatMetric(
                'Peak memory',
                $baseline->peakMemory,
                $target->peakMemory,
                1 / 1_048_576,
                'MB',
                'profiling',
            ),
            self::integerMetric(
                'SQL queries',
                $baseline->sqlCount,
                $target->sqlCount,
                'db',
            ),
            self::integerMetric(
                'Mail messages',
                $baseline->mailCount,
                $target->mailCount,
                'mail',
            ),
            self::integerMetric(
                'Excessive DB callers',
                $baseline->excessiveCallersCount,
                $target->excessiveCallersCount,
                'db',
            ),
        ];
    }

    private static function formatNumber(float|int $value, string $unit, int $precision): string
    {
        $formatted = number_format($value, $precision, '.', ',');

        return $unit === '' ? $formatted : "{$formatted} {$unit}";
    }

    private static function integerMetric(
        string $label,
        int $baseline,
        int $target,
        string|null $panelId = null,
    ): self {
        return self::numericMetric(
            $label,
            $baseline,
            $target,
            1,
            '',
            $panelId,
            0,
        );
    }

    private static function nullableFloatMetric(
        string $label,
        float|int|null $baseline,
        float|int|null $target,
        float $scale,
        string $unit,
        string|null $panelId = null,
    ): self {
        if ($baseline === null || $target === null) {
            return new self(
                label: $label,
                baseline: $baseline === null ? 'Not captured' : self::formatNumber($baseline * $scale, $unit, 2),
                target: $target === null ? 'Not captured' : self::formatNumber($target * $scale, $unit, 2),
                delta: $baseline === $target ? 'No change' : 'Not comparable',
                trend: 'neutral',
                panelId: $panelId,
            );
        }

        return self::numericMetric(
            $label,
            $baseline,
            $target,
            $scale,
            $unit,
            $panelId,
            2,
        );
    }

    private static function numericMetric(
        string $label,
        float|int $baseline,
        float|int $target,
        float $scale,
        string $unit,
        string|null $panelId,
        int $precision,
    ): self {
        $scaledBaseline = $baseline * $scale;
        $scaledTarget = $target * $scale;
        $scaledDelta = $scaledTarget - $scaledBaseline;
        $trend = $scaledDelta > 0 ? 'up' : ($scaledDelta < 0 ? 'down' : 'neutral');

        $delta = 'No change';

        if ($scaledDelta !== 0.0) {
            $sign = $trend === 'up' ? '+' : '';
            $percentage = (float) $baseline !== 0.0
                ? " ({$sign}" . number_format((($target - $baseline) / $baseline) * 100, 1) . '%)'
                : '';
            $delta = $sign . self::formatNumber($scaledDelta, $unit, $precision) . $percentage;
        }

        return new self(
            label: $label,
            baseline: self::formatNumber($scaledBaseline, $unit, $precision),
            target: self::formatNumber($scaledTarget, $unit, $precision),
            delta: $delta,
            trend: $trend,
            panelId: $panelId,
        );
    }

    private static function status(int $statusCode): string
    {
        return $statusCode === 0 ? 'Not captured' : (string) $statusCode;
    }

    private static function textMetric(string $label, string $baseline, string $target): self
    {
        return new self(
            label: $label,
            baseline: $baseline,
            target: $target,
            delta: $baseline === $target ? 'No change' : 'Changed',
            trend: 'neutral',
        );
    }

    private static function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}

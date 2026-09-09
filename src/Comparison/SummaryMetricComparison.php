<?php

declare(strict_types=1);

namespace PHPForge\Debug\Comparison;

use PHPForge\Debug\Helper\Format;
use PHPForge\Debug\Storage\RequestSummary;

use function number_format;

/**
 * Calculates and formats request-summary metrics without depending on adapter presentation models.
 *
 * Results retain the history labels, order, units, trends, and related panel IDs used by both adapters.
 */
final readonly class SummaryMetricComparison
{
    /**
     * @param string $label History metric label.
     * @param string $baseline Formatted baseline value.
     * @param string $target Formatted target value.
     * @param string $delta Formatted difference, or `No change` when both values match.
     * @param string $trend Trend direction: `up`, `down`, or `neutral`.
     * @param string|null $panelId Related panel ID, or `null` when the metric has no panel.
     */
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
     * @param RequestSummary $baseline Baseline request metadata.
     * @param RequestSummary $target Target request metadata.
     *
     * @return list<self> Metric comparisons in canonical history order.
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
                Format::MILLISECONDS_PER_SECOND,
                'ms',
                'profiling',
            ),
            self::nullableFloatMetric(
                'Peak memory',
                $baseline->peakMemory,
                $target->peakMemory,
                1 / Format::BYTES_PER_MB,
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

    /**
     * Returns whether the metric changed between the baseline and the target.
     *
     * @return bool `true` when the formatted delta reports a change; `false` otherwise.
     */
    public function hasDifference(): bool
    {
        return $this->delta !== 'No change';
    }

    /**
     * Formats a scaled value with its unit.
     *
     * @param float|int $value Scaled value to format.
     * @param string $unit Unit suffix, or an empty string to omit it.
     * @param int $precision Decimal places to keep.
     *
     * @return string Formatted value, with the unit appended when one is given.
     */
    private static function formatNumber(float|int $value, string $unit, int $precision): string
    {
        $formatted = number_format($value, $precision, '.', ',');

        return $unit === '' ? $formatted : "{$formatted} {$unit}";
    }

    /**
     * Compares two counters reported without a unit.
     *
     * @param string $label History metric label.
     * @param int $baseline Baseline counter.
     * @param int $target Target counter.
     * @param string|null $panelId Related panel ID, or `null` when the metric has no panel.
     *
     * @return self Metric comparison for the two counters.
     */
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

    /**
     * Compares two optional measurements, reporting `Not comparable` when either side is missing.
     *
     * @param string $label History metric label.
     * @param float|int|null $baseline Baseline measurement, or `null` when not captured.
     * @param float|int|null $target Target measurement, or `null` when not captured.
     * @param float $scale Factor converting the raw value to its display unit.
     * @param string $unit Unit suffix.
     * @param string|null $panelId Related panel ID, or `null` when the metric has no panel.
     *
     * @return self Metric comparison for the two measurements.
     */
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

    /**
     * Compares two measurements, formatting the absolute delta and its percentage.
     *
     * @param string $label History metric label.
     * @param float|int $baseline Baseline measurement.
     * @param float|int $target Target measurement.
     * @param float $scale Factor converting the raw value to its display unit.
     * @param string $unit Unit suffix, or an empty string to omit it.
     * @param string|null $panelId Related panel ID, or `null` when the metric has no panel.
     * @param int $precision Decimal places to keep.
     *
     * @return self Metric comparison for the two measurements.
     */
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

        $trend = match (true) {
            $scaledDelta > 0 => 'up',
            $scaledDelta < 0 => 'down',
            default => 'neutral',
        };

        $delta = 'No change';

        if ($scaledDelta !== 0.0) {
            $sign = $trend === 'up' ? '+' : '';
            $percentage = '';

            if ((float) $baseline !== 0.0) {
                $ratio = number_format((($target - $baseline) / $baseline) * 100, 1);

                $percentage = " ({$sign}{$ratio}%)";
            }

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

    /**
     * Formats an HTTP status code, reporting an uncaptured status as text.
     *
     * @param int $statusCode Captured status code, or `0` when not captured.
     *
     * @return string Status code, or `Not captured` when the code is `0`.
     */
    private static function status(int $statusCode): string
    {
        return $statusCode === 0 ? 'Not captured' : (string) $statusCode;
    }

    /**
     * Compares two textual values, reporting only whether they differ.
     *
     * @param string $label History metric label.
     * @param string $baseline Baseline value.
     * @param string $target Target value.
     *
     * @return self Metric comparison for the two values.
     */
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

    /**
     * Formats a flag for display.
     *
     * @param bool $value Flag to format.
     *
     * @return string `Yes` when the flag is `true`; `No` otherwise.
     */
    private static function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}

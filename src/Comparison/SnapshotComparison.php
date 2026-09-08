<?php

declare(strict_types=1);

namespace PHPForge\Debug\Comparison;

use PHPForge\Debug\Storage\DebugSnapshot;

/**
 * Pairs request-summary metrics with per-panel structural differences for two captured snapshots.
 *
 * The comparison keeps references to both source snapshots so adapter facades can expose them, while the metric and
 * panel results retain only labels, states, and counts. An overview therefore cannot surface values that the individual
 * panels keep behind their own presentation and redaction rules unless it reads the snapshots directly.
 */
final readonly class SnapshotComparison
{
    /**
     * @param DebugSnapshot $baseline Baseline snapshot.
     * @param DebugSnapshot $target Target snapshot.
     * @param list<SummaryMetricComparison> $metrics Summary metric comparisons in canonical history order.
     * @param list<PanelComparison> $panels Panel comparisons in configured display order.
     */
    private function __construct(
        public DebugSnapshot $baseline,
        public DebugSnapshot $target,
        public array $metrics,
        public array $panels,
    ) {}

    /**
     * Compares two snapshots, combining summary metrics with panel structural differences.
     *
     * @param array<string, string> $panelLabels Display names indexed by stable panel ID, in display order.
     */
    public static function between(DebugSnapshot $baseline, DebugSnapshot $target, array $panelLabels = []): self
    {
        return new self(
            baseline: $baseline,
            target: $target,
            metrics: SummaryMetricComparison::between($baseline->summary, $target->summary),
            panels: PanelComparison::between($baseline, $target, $panelLabels),
        );
    }

    /**
     * Returns whether any summary metric or panel payload differs.
     */
    public function hasDifferences(): bool
    {
        foreach ($this->metrics as $metric) {
            if ($metric->hasDifference()) {
                return true;
            }
        }

        foreach ($this->panels as $panel) {
            if ($panel->differenceCount() > 0) {
                return true;
            }
        }

        return false;
    }
}

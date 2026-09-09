<?php

declare(strict_types=1);

namespace PHPForge\Debug\Comparison;

use PHPForge\Debug\Storage\DebugSnapshot;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function in_array;
use function sort;

/**
 * Compares observed panels in configured order, combining structural differences with capture-state transitions.
 *
 * Results retain only identity, labels, states, and counts; source diagnostics are neither copied nor redacted.
 */
final readonly class PanelComparison
{
    /**
     * Capture state of a panel whose payload reached the snapshot.
     */
    public const string STATE_CAPTURED = 'Captured';
    /**
     * Capture state of a panel whose capture failed.
     */
    public const string STATE_FAILED = 'Failed';
    /**
     * Capture state of a panel absent from the snapshot.
     */
    public const string STATE_NOT_CAPTURED = 'Not captured';

    /**
     * @param string $id Stable panel ID.
     * @param string $label Display name, falling back to the panel ID.
     * @param string $baselineState Capture state in the baseline snapshot.
     * @param string $targetState Capture state in the target snapshot.
     * @param int $added Leaves present only in the target payload.
     * @param int $removed Leaves present only in the baseline payload.
     * @param int $changed Leaves that differ, or `1` for a state-only transition.
     * @param int $unchanged Leaves present in both payloads under the same value.
     */
    private function __construct(
        public string $id,
        public string $label,
        public string $baselineState,
        public string $targetState,
        public int $added,
        public int $removed,
        public int $changed,
        public int $unchanged,
    ) {}

    /**
     * Compares the union of payload and failure IDs, with observed configured IDs first and sorted extras last.
     *
     * Failures take precedence over payloads. A state transition adds one change only when there are no structural
     * additions, removals, or changes; unchanged leaves remain counted even for that state-only transition.
     *
     * @param DebugSnapshot $baseline Baseline snapshot.
     * @param DebugSnapshot $target Target snapshot.
     * @param array<string, string> $panelLabels Display names indexed by stable panel ID, in display order.
     *
     * @return list<self> Panel comparisons in configured display order.
     */
    public static function between(DebugSnapshot $baseline, DebugSnapshot $target, array $panelLabels = []): array
    {
        $observedIds = array_unique(
            [
                ...array_keys($baseline->panels),
                ...array_keys($baseline->failures),
                ...array_keys($target->panels),
                ...array_keys($target->failures),
            ],
        );

        $configuredIds = array_keys($panelLabels);
        $orderedIds = [];

        foreach ($configuredIds as $id) {
            if (in_array($id, $observedIds, true)) {
                $orderedIds[] = $id;
            }
        }

        $extraIds = array_diff($observedIds, $orderedIds);

        sort($extraIds);

        $orderedIds = [
            ...$orderedIds,
            ...$extraIds,
        ];

        $comparisons = [];

        foreach ($orderedIds as $id) {
            $baselineState = self::panelState($baseline, $id);
            $targetState = self::panelState($target, $id);

            $difference = PayloadDifference::between(
                self::panelPayload($baseline, $id),
                self::panelPayload($target, $id),
            );

            $changed = $difference->changed;

            if ($difference->added + $difference->removed + $changed === 0 && $baselineState !== $targetState) {
                $changed = 1;
            }

            $comparisons[] = new self(
                id: $id,
                label: $panelLabels[$id] ?? $id,
                baselineState: $baselineState,
                targetState: $targetState,
                added: $difference->added,
                removed: $difference->removed,
                changed: $changed,
                unchanged: $difference->unchanged,
            );
        }

        return $comparisons;
    }

    /**
     * Returns the total number of structural differences detected for the panel.
     *
     * @return int Sum of added, removed, and changed leaves.
     */
    public function differenceCount(): int
    {
        return $this->added + $this->removed + $this->changed;
    }

    /**
     * Returns the captured payload or failure envelope, preserving the distinction between absent and empty.
     *
     * @param DebugSnapshot $snapshot Snapshot to inspect.
     * @param string $id Stable panel ID.
     *
     * @return array<string, mixed>|null Captured payload, failure envelope, or `null` when the panel is absent.
     */
    private static function panelPayload(DebugSnapshot $snapshot, string $id): array|null
    {
        if (isset($snapshot->failures[$id])) {
            return ['failure' => $snapshot->failures[$id]->jsonSerialize()];
        }

        return $snapshot->panels[$id] ?? null;
    }

    /**
     * Returns the capture state of the panel within the snapshot.
     *
     * @param DebugSnapshot $snapshot Snapshot to inspect.
     * @param string $id Stable panel ID.
     *
     * @return string One of {@see STATE_CAPTURED}, {@see STATE_FAILED}, or {@see STATE_NOT_CAPTURED}.
     */
    private static function panelState(DebugSnapshot $snapshot, string $id): string
    {
        if (array_key_exists($id, $snapshot->failures)) {
            return self::STATE_FAILED;
        }

        return array_key_exists($id, $snapshot->panels) ? self::STATE_CAPTURED : self::STATE_NOT_CAPTURED;
    }
}

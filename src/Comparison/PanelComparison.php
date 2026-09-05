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
     * @param array<string, string> $panelLabels Display names indexed by stable panel ID, in display order.
     *
     * @return list<self>
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

        $orderedIds = [];

        foreach ($panelLabels as $id => $_label) {
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

            $added = $difference->added;
            $removed = $difference->removed;
            $changed = $difference->changed;
            $unchanged = $difference->unchanged;

            if ($added + $removed + $changed === 0 && $baselineState !== $targetState) {
                $changed = 1;
            }

            $comparisons[] = new self(
                id: $id,
                label: $panelLabels[$id] ?? $id,
                baselineState: $baselineState,
                targetState: $targetState,
                added: $added,
                removed: $removed,
                changed: $changed,
                unchanged: $unchanged,
            );
        }

        return $comparisons;
    }

    /**
     * Returns the captured payload or failure envelope, preserving the distinction between absent and empty.
     *
     * @return array<string, mixed>|null
     */
    private static function panelPayload(DebugSnapshot $snapshot, string $id): array|null
    {
        if (isset($snapshot->failures[$id])) {
            return ['failure' => $snapshot->failures[$id]->jsonSerialize()];
        }

        return $snapshot->panels[$id] ?? null;
    }

    private static function panelState(DebugSnapshot $snapshot, string $id): string
    {
        if (array_key_exists($id, $snapshot->failures)) {
            return 'Failed';
        }

        return array_key_exists($id, $snapshot->panels) ? 'Captured' : 'Not captured';
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use function count;
use function spl_object_id;

/**
 * Derives chronology from the complete capture before filtering, sorting, or pagination.
 */
final readonly class EventSequence
{
    /**
     * @var array<int, list<EventRow>> Explicit request-local lifecycle correlations.
     */
    private array $pairs;
    /**
     * @var array<int, int> Original one-based observation identities, indexed by row object identity.
     */
    private array $positions;

    /**
     * @param list<EventRow> $rows Original observation order.
     */
    public function __construct(private array $rows)
    {
        $positions = [];
        $pairs = [];

        foreach ($rows as $index => $row) {
            $positions[spl_object_id($row)] ??= $index + 1;
            $id = $row->inspection()?->getPairId();

            if ($id !== null) {
                $pairs[$id][] = $row;
            }
        }

        $this->positions = $positions;
        $this->pairs = $pairs;
    }

    public function elapsed(EventRow $row): float
    {
        return ($row->time - ($this->rows[0]->time ?? $row->time)) * 1000;
    }

    public function gap(EventRow $row): float|null
    {
        $index = $this->index($row);

        $previous = $index < 2 ? null : ($this->rows[$index - 2] ?? null);

        return $previous === null ? null : ($row->time - $previous->time) * 1000;
    }

    public function index(EventRow $row): int
    {
        return $this->positions[spl_object_id($row)] ?? 0;
    }

    /**
     * Returns only unambiguous, explicitly correlated inclusive intervals.
     */
    public function interval(EventRow $row): float|null
    {
        $inspection = $row->inspection();

        if ($inspection?->getPhase() !== 'enter' || $inspection->getPairId() === null || $inspection->getClock() === null) {
            return null;
        }

        $pair = $this->pairs[$inspection->getPairId()] ?? [];

        if (count($pair) !== 2 || $pair[0] !== $row) {
            return null;
        }

        $end = $pair[1];

        $detail = $end->inspection();

        if (
            $end->senderClass !== $row->senderClass || $detail?->getPhase() !== 'leave'
            || $detail->getDepth() !== $inspection->getDepth() || $detail->getClock() === null || $detail->getClock() < $inspection->getClock()
        ) {
            return null;
        }

        return ($detail->getClock() - $inspection->getClock()) * 1000;
    }
}

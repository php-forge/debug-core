<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use function count;

/**
 * Computes request-wide Database metrics without depending on the visible page or active filters.
 */
final readonly class DbSummary
{
    /**
     * Counts captured database queries by trace hash, excluding rows whose trace was not captured (empty hash).
     *
     * @var array<string, int>
     */
    public array $callers;
    /**
     * Counts the captured database queries.
     */
    public int $count;
    /**
     * Counts queries marked as duplicates.
     */
    public int $duplicates;
    /**
     * Stores the total query duration in milliseconds.
     */
    public float $duration;
    /**
     * Encountered SQL command verbs keyed by verb, so adapters can feed them straight into select filters.
     *
     * @var array<string, string>
     */
    public array $types;

    /**
     * @param list<QueryRow> $rows
     */
    public function __construct(array $rows)
    {
        $duplicates = 0;
        $duration = 0.0;
        $callers = [];
        $types = [];

        foreach ($rows as $row) {
            if ($row->duplicate > 1) {
                $duplicates++;
            }

            $duration += $row->duration;

            if ($row->traceHash !== '') {
                $callers[$row->traceHash] = ($callers[$row->traceHash] ?? 0) + 1;
            }

            $types[$row->type] = $row->type;
        }

        $this->count = count($rows);
        $this->duplicates = $duplicates;
        $this->duration = $duration;
        $this->callers = $callers;
        $this->types = $types;
    }

    /**
     * Counts the call sites that issued at least `$threshold` statements, or `0` when the threshold is `null`.
     */
    public function excessiveCallerCount(int|null $threshold): int
    {
        if ($threshold === null) {
            return 0;
        }

        $count = 0;

        foreach ($this->callers as $calls) {
            if ($calls >= $threshold) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Returns whether the request exceeds either threshold and must be surfaced as a toolbar warning.
     *
     * @param int|null $criticalQueryThreshold Query count above which the request is critical, or `null` to disable.
     * @param int|null $excessiveCallerThreshold Statements per call site that flag it, or `null` to disable.
     */
    public function hasWarning(int|null $criticalQueryThreshold, int|null $excessiveCallerThreshold): bool
    {
        return $this->isCritical($criticalQueryThreshold)
            || $this->excessiveCallerCount($excessiveCallerThreshold) > 0;
    }

    /**
     * Returns whether the query count exceeds `$threshold`, or `false` when the threshold is `null`.
     */
    public function isCritical(int|null $threshold): bool
    {
        return $threshold !== null && $this->count > $threshold;
    }
}

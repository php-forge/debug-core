<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;

use function min;
use function strtoupper;
use function usort;

/**
 * Detects repeated read queries emitted from one captured call site.
 */
final class NPlusOneDetector
{
    /**
     * Indexes findings by every query sequence they cover, so a grid row can look up its own group.
     *
     * @param list<NPlusOneFinding> $findings Findings to index.
     *
     * @return array<int, NPlusOneFinding> Findings keyed by covered query sequence.
     */
    public static function bySequence(array $findings): array
    {
        $bySequence = [];

        foreach ($findings as $finding) {
            foreach ($finding->sequences as $sequence) {
                $bySequence[$sequence] = $finding;
            }
        }

        return $bySequence;
    }
    /**
     * Groups repeated `SELECT` statements by captured call site and keeps the groups above the threshold.
     *
     * @param list<QueryRow> $rows Captured query rows in capture order.
     * @param int $threshold Minimum occurrences a call site needs to be reported; must be at least `2`.
     *
     * @return list<NPlusOneFinding> Findings ordered by count, then total duration, then first sequence.
     *
     * @throws InvalidArgumentException When the threshold is lower than `2`.
     */
    public static function detect(array $rows, int $threshold = 3): array
    {
        if ($threshold < 2) {
            throw new InvalidArgumentException(
                Message::N_PLUS_ONE_THRESHOLD_INVALID->getMessage(),
            );
        }

        /**
         * @var array<non-empty-string, array{
         *   count: int,
         *   duration: float,
         *   first: int,
         *   query: string,
         *   sequences: list<int>
         * }> $groups
         */
        $groups = [];

        foreach ($rows as $row) {
            if (
                $row->getTraceHash() === ''
                || strtoupper($row->getType()) !== 'SELECT'
            ) {
                continue;
            }

            $group = $groups[$row->getTraceHash()] ?? [
                'count' => 0,
                'duration' => 0.0,
                'first' => $row->getSequence(),
                'query' => $row->getQuery(),
                'sequences' => [],
            ];

            $group['count']++;
            $group['duration'] += $row->getDuration();
            $group['first'] = min($group['first'], $row->getSequence());
            $group['sequences'][] = $row->getSequence();

            $groups[$row->getTraceHash()] = $group;
        }

        $findings = [];

        foreach ($groups as $fingerprint => $group) {
            $count = $group['count'];

            if ($count < $threshold) {
                continue;
            }

            $findings[] = new NPlusOneFinding(
                fingerprint: $fingerprint,
                count: $count,
                totalDuration: $group['duration'],
                firstSequence: $group['first'],
                sequences: $group['sequences'],
                representativeQuery: $group['query'],
            );
        }

        usort(
            $findings,
            static fn(NPlusOneFinding $left, NPlusOneFinding $right): int => [
                -$left->count,
                -$left->totalDuration,
                $left->firstSequence,
            ] <=> [
                -$right->count,
                -$right->totalDuration,
                $right->firstSequence,
            ],
        );

        return $findings;
    }
}

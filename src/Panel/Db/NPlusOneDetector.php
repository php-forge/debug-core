<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use InvalidArgumentException;

use function min;
use function strtoupper;
use function usort;

/**
 * Detects repeated read queries emitted from one captured call site.
 */
final class NPlusOneDetector
{
    /**
     * @param list<QueryRow> $rows
     *
     * @return list<NPlusOneFinding>
     */
    public static function detect(array $rows, int $threshold = 3): array
    {
        if ($threshold < 2) {
            throw new InvalidArgumentException('The N+1 threshold must be at least two.');
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
                $row->traceHash === ''
                || strtoupper($row->type) !== 'SELECT'
            ) {
                continue;
            }

            if (!isset($groups[$row->traceHash])) {
                $groups[$row->traceHash] = [
                    'count' => 0,
                    'duration' => 0.0,
                    'first' => $row->seq,
                    'query' => $row->query,
                    'sequences' => [],
                ];
            }

            $group = &$groups[$row->traceHash];
            $group['count']++;
            $group['duration'] += $row->duration;
            $group['first'] = min($group['first'], $row->seq);
            $group['sequences'][] = $row->seq;
        }

        unset($group);

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

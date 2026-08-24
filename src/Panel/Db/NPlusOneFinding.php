<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

/**
 * Potential N+1 query group sharing one captured call-site fingerprint.
 */
final readonly class NPlusOneFinding
{
    /**
     * @param non-empty-string $fingerprint Stable backtrace fingerprint.
     * @param positive-int $count Number of observed query occurrences.
     * @param float $totalDuration Total group duration in milliseconds.
     * @param int $firstSequence First query sequence used as the deep-link target.
     * @param list<int> $sequences Query sequences belonging to the group.
     */
    public function __construct(
        public string $fingerprint,
        public int $count,
        public float $totalDuration,
        public int $firstSequence,
        public array $sequences,
        public string $representativeQuery,
    ) {}

    public function contains(QueryRow $row): bool
    {
        return $row->traceHash === $this->fingerprint;
    }

    public function id(): string
    {
        return "yii-debug-db-n1-{$this->firstSequence}";
    }
}

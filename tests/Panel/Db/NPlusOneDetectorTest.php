<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use InvalidArgumentException;
use PHPForge\Debug\Panel\Db\{NPlusOneDetector, QueryRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('panel')]
#[Group('db')]
final class NPlusOneDetectorTest extends TestCase
{
    public function testDetectGroupsReadQueriesByCallSiteFingerprint(): void
    {
        $findings = NPlusOneDetector::detect(
            [
                self::row(0, 'caller-a', 'SELECT * FROM user WHERE id=1', 1.2),
                self::row(1, 'caller-a', 'SELECT * FROM user WHERE id=2', 2.3),
                self::row(2, 'caller-a', 'SELECT * FROM user WHERE id=3', 3.4),
                self::row(3, 'caller-b', 'SELECT 1', 10),
                self::row(4, 'caller-a', 'UPDATE user SET seen=1', 20, 'UPDATE'),
                self::row(5, '', 'SELECT 2', 20),
            ],
        );

        self::assertCount(1, $findings, 'Only repeated reads from a stable call site should be reported.');
        $finding = $findings[0] ?? self::fail('Expected one N+1 finding.');
        self::assertSame('caller-a', $finding->fingerprint, 'The backtrace hash is the group fingerprint.');
        self::assertSame(3, $finding->count, 'Every matching occurrence should contribute to the group.');
        self::assertEqualsWithDelta(6.9, $finding->totalDuration, 0.0001, 'Durations should be aggregated.');
        self::assertSame([0, 1, 2], $finding->sequences, 'Deep-link sequences should remain in capture order.');
        self::assertSame('yii-debug-db-n1-0', $finding->id(), 'The first query should provide a stable anchor.');
    }

    public function testDuplicateCountsFromOtherCallSitesDoNotTriggerFinding(): void
    {
        $candidate = self::row(1, 'caller-b', 'SELECT 1', 1, duplicate: 5);
        $rows = [self::row(0, 'caller-a', 'SELECT 1', 1, duplicate: 5), $candidate];

        for ($seq = 2; $seq <= 5; $seq++) {
            $rows[] = self::row($seq, 'caller-b', 'SELECT 1', 1, duplicate: 5);
        }

        $findings = NPlusOneDetector::detect($rows);

        self::assertCount(1, $findings, 'Only the call site that reaches the threshold should be reported.');
        $finding = $findings[0] ?? self::fail('Expected one N+1 finding.');
        self::assertSame('caller-b', $finding->fingerprint, 'Global exact duplicates must not leak across callers.');
        self::assertTrue($finding->contains($candidate), 'Findings should identify rows from their fingerprint.');
    }

    public function testRejectsUnsafeThreshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The N+1 threshold must be at least two.');

        NPlusOneDetector::detect([], 1);
    }

    public function testWithStatementsAreExcludedWithoutSqlParsing(): void
    {
        $rows = [
            self::row(0, 'caller-a', 'WITH deleted AS (DELETE FROM user RETURNING *) SELECT * FROM deleted', 1, 'WITH'),
            self::row(1, 'caller-a', 'WITH deleted AS (DELETE FROM user RETURNING *) SELECT * FROM deleted', 1, 'WITH'),
            self::row(2, 'caller-a', 'WITH deleted AS (DELETE FROM user RETURNING *) SELECT * FROM deleted', 1, 'WITH'),
        ];

        self::assertSame([], NPlusOneDetector::detect($rows), 'WITH statements may mutate and require a SQL parser.');
    }

    private static function row(
        int $seq,
        string $traceHash,
        string $query,
        float $duration,
        string $type = 'SELECT',
        int $duplicate = 1,
    ): QueryRow {
        return new QueryRow(
            type: $type,
            query: $query,
            duration: $duration,
            trace: [],
            traceHash: $traceHash,
            timestamp: 0,
            seq: $seq,
            duplicate: $duplicate,
            rows: null,
        );
    }
}

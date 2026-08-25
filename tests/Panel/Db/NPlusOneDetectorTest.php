<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use InvalidArgumentException;
use PHPForge\Debug\Panel\Db\{NPlusOneDetector, NPlusOneFinding, QueryRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('panel')]
#[Group('db')]
final class NPlusOneDetectorTest extends TestCase
{
    public function testDetectAcceptsThresholdTwoAndLowercaseSelectAfterSkippedRow(): void
    {
        $rows = [
            self::row(0, '', 'SELECT 1', 1.0),
            self::row(1, 'caller-a', 'select * from user where id=1', 1.0, 'select'),
            self::row(2, 'caller-a', 'select * from user where id=2', 1.0, 'select'),
        ];

        self::assertSame(
            [],
            NPlusOneDetector::detect($rows),
            'A pair must stay below the default threshold.',
        );

        $findings = NPlusOneDetector::detect($rows, 2);

        self::assertCount(
            1,
            $findings,
            'Threshold two must be accepted and reached by a pair.',
        );

        $finding = $findings[0] ?? self::fail('Expected one N+1 finding.');

        self::assertSame(
            2,
            $finding->count,
            'Lowercase read verbs must be grouped case-insensitively.',
        );
    }

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

        self::assertCount(
            1,
            $findings,
            'Only repeated reads from a stable call site should be reported.',
        );

        $finding = $findings[0] ?? self::fail('Expected one N+1 finding.');

        self::assertSame(
            'caller-a',
            $finding->fingerprint,
            'The backtrace hash is the group fingerprint.',
        );
        self::assertSame(
            3,
            $finding->count,
            'Every matching occurrence should contribute to the group.',
        );
        self::assertEqualsWithDelta(
            6.9,
            $finding->totalDuration,
            0.0001,
            'Durations should be aggregated.',
        );
        self::assertSame(
            [0, 1, 2],
            $finding->sequences,
            'Deep-link sequences must remain in capture order.',
        );
        self::assertSame(
            'yii-debug-db-n1-0',
            $finding->id(),
            'The first query should provide a stable anchor.',
        );
    }

    public function testDetectOrdersFindingsByCountThenTotalDurationThenFirstSequence(): void
    {
        $findings = NPlusOneDetector::detect(
            [
                self::row(11, 'slow-late', 'SELECT * FROM a WHERE id=1', 1.0),
                self::row(10, 'slow-late', 'SELECT * FROM a WHERE id=2', 2.0),
                self::row(12, 'slow-late', 'SELECT * FROM a WHERE id=3', 2.0),
                self::row(13, 'fast-heavy', 'SELECT * FROM b WHERE id=1', 3.0),
                self::row(14, 'fast-heavy', 'SELECT * FROM b WHERE id=2', 3.0),
                self::row(15, 'fast-heavy', 'SELECT * FROM b WHERE id=3', 3.0),
                self::row(16, 'hot', 'SELECT * FROM c WHERE id=1', 0.25),
                self::row(17, 'hot', 'SELECT * FROM c WHERE id=2', 0.25),
                self::row(18, 'hot', 'SELECT * FROM c WHERE id=3', 0.25),
                self::row(19, 'hot', 'SELECT * FROM c WHERE id=4', 0.25),
                self::row(2, 'tie-late', 'SELECT * FROM d WHERE id=1', 2.5),
                self::row(3, 'tie-late', 'SELECT * FROM d WHERE id=2', 1.5),
                self::row(4, 'tie-late', 'SELECT * FROM d WHERE id=3', 1.0),
            ],
        );

        self::assertSame(
            ['hot', 'fast-heavy', 'tie-late', 'slow-late'],
            array_map(static fn(NPlusOneFinding $finding): string => $finding->fingerprint, $findings),
            'Order: count desc, then duration desc, then first sequence asc.',
        );

        $last = $findings[3] ?? self::fail('Expected four findings.');

        self::assertSame(
            10,
            $last->firstSequence,
            'First sequence must be the minimum captured seq.',
        );
        self::assertSame(
            [11, 10, 12],
            $last->sequences,
            'Sequences must keep capture order.',
        );
    }

    public function testDuplicateCountsFromOtherCallSitesDoNotTriggerFinding(): void
    {
        $candidate = self::row(1, 'caller-b', 'SELECT 1', 1, duplicate: 5);
        $rows = [
            self::row(0, 'caller-a', 'SELECT 1', 1, duplicate: 5), $candidate,
        ];

        for ($seq = 2; $seq <= 5; $seq++) {
            $rows[] = self::row($seq, 'caller-b', 'SELECT 1', 1, duplicate: 5);
        }

        $findings = NPlusOneDetector::detect($rows);

        self::assertCount(
            1,
            $findings,
            'Only the call site that reaches the threshold should be reported.',
        );

        $finding = $findings[0] ?? self::fail('Expected one N+1 finding.');

        self::assertSame(
            'caller-b',
            $finding->fingerprint,
            'Global exact duplicates must not leak across callers.',
        );
        self::assertTrue(
            $finding->contains($candidate),
            'Findings should identify rows from their fingerprint.',
        );
    }

    public function testRejectsUnsafeThreshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The N+1 threshold must be at least two.',
        );

        NPlusOneDetector::detect([], 1);
    }

    public function testWithStatementsAreExcludedWithoutSqlParsing(): void
    {
        $rows = [
            self::row(0, 'caller-a', 'WITH deleted AS (DELETE FROM user RETURNING *) SELECT * FROM deleted', 1, 'WITH'),
            self::row(1, 'caller-a', 'WITH deleted AS (DELETE FROM user RETURNING *) SELECT * FROM deleted', 1, 'WITH'),
            self::row(2, 'caller-a', 'WITH deleted AS (DELETE FROM user RETURNING *) SELECT * FROM deleted', 1, 'WITH'),
        ];

        self::assertSame(
            [],
            NPlusOneDetector::detect($rows),
            'WITH statements may mutate and require a SQL parser.',
        );
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

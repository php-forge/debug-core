<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use PHPForge\Debug\Exception\Message;
use PHPForge\Debug\Panel\Db\QueryRow;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see QueryRow} covering the capture-time narrowing of logger timings and the strict JSON hydration
 * that restores them without coercion, as well as immutable row configuration.
 */
#[Group('panel')]
#[Group('db')]
final class QueryRowTest extends TestCase
{
    public function testExtractTypeReturnsAnEmptyStringWhenNoLeadingVerbIsPresent(): void
    {
        self::assertSame(
            '',
            QueryRow::extractType('  (SELECT 1)'),
            'A statement not starting with a letter has no verb.',
        );
        self::assertSame(
            '',
            QueryRow::extractType(''),
            'An empty statement has no verb.',
        );
    }
    public function testExtractTypeUppercasesTheLeadingVerbAndIgnoresLeadingWhitespace(): void
    {
        self::assertSame(
            'SELECT',
            QueryRow::extractType("  \n select * from users"),
            'Leading whitespace must not hide the verb.',
        );
        self::assertSame(
            'INSERT',
            QueryRow::extractType('InSeRt INTO users VALUES (1)'),
            'The verb must be uppercased.',
        );
    }

    public function testFindBySequenceComparesTheSequenceAsAString(): void
    {
        $rows = [
            QueryRow::create('SELECT 1', 1.0, 0.0)->withSequence(2),
            QueryRow::create('SELECT 2', 2.0, 0.0)->withSequence(13),
        ];

        self::assertSame(
            'SELECT 2',
            QueryRow::findBySequence($rows, '13')?->getQuery(),
            'An exact sequence must return its own row.',
        );
        self::assertNull(
            QueryRow::findBySequence($rows, '02'),
            'A padded sequence must not match.',
        );
        self::assertNull(
            QueryRow::findBySequence($rows, '99'),
            'An uncaptured sequence must return `null`.',
        );
    }

    public function testFromArrayClampsDuplicateToMinimumOfOne(): void
    {
        $row = QueryRow::fromArray(self::payload(['duplicate' => 0]), '$.panels.db.entries[0]');

        self::assertSame(
            1,
            $row->getDuplicate(),
            "A duplicate count below one must clamp to '1'.",
        );
    }

    public function testFromArrayPreservesExplicitTypeAndNonEncodableTrace(): void
    {
        $payload = self::payload(
            [
                'type' => 'select',
                'trace' => [['file' => "\xFF"]],
                'traceHash' => 'logger-hash',
            ],
        );

        self::assertSame(
            $payload,
            QueryRow::fromArray($payload, '$.row')->jsonSerialize(),
            'Hydration must preserve the captured type and hash without encoding the trace again.',
        );
    }

    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = QueryRow::create('SELECT * FROM t', 5.0, 1_700_000_000_000.0)
            ->withTrace([['file' => '/app/index.php', 'line' => 12]])
            ->withTraceHash('abc123')
            ->withSequence(3)
            ->withDuplicate(2)
            ->withRows(42);

        self::assertEquals(
            $row,
            QueryRow::fromArray($row->jsonSerialize(), '$.panels.db.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromTimingReindexesKeyedTraceFrames(): void
    {
        $row = QueryRow::fromTiming(
            [
                'info' => 'SELECT 1',
                'category' => 'yii\\db\\Command::query',
                'timestamp' => 1.0,
                'trace' => [
                    3 => ['file' => 'a.php'],
                    9 => ['file' => 'b.php'],
                ],
                'level' => 0,
                'duration' => 0.001,
                'memory' => 0,
                'memoryDiff' => 0,
                'traceHash' => 'hash',
            ],
            'SELECT',
            0,
            null,
        );

        self::assertSame(
            [
                ['file' => 'a.php'],
                ['file' => 'b.php'],
            ],
            $row->getTrace(),
            'Trace frames must become a zero-based list.',
        );
    }

    public function testFromTimingScalesToMillisecondsAndKeepsCallerValues(): void
    {
        $row = QueryRow::fromTiming(
            [
                'info' => 'SELECT 1',
                'category' => 'yii\\db\\Command::query',
                'timestamp' => 2.5,
                'trace' => [['file' => 'a.php']],
                'level' => 0,
                'duration' => 0.005,
                'memory' => 0,
                'memoryDiff' => 0,
                'traceHash' => 'hash',
            ],
            'SELECT',
            7,
            42,
        );

        self::assertSame(
            'SELECT',
            $row->getType(),
            'The verb is supplied by the caller.',
        );
        self::assertSame(
            'SELECT 1',
            $row->getQuery(),
            'The statement comes from the timing token.',
        );
        self::assertEqualsWithDelta(
            5.0,
            $row->getDuration(),
            1e-9,
            'Duration must be scaled to milliseconds.',
        );
        self::assertEqualsWithDelta(
            2_500.0,
            $row->getTimestamp(),
            1e-9,
            'Timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            'hash',
            $row->getTraceHash(),
            'Trace hash must round-trip.',
        );
        self::assertSame(
            7,
            $row->getSequence(),
            'Sequence index is supplied by the caller.',
        );
        self::assertSame(
            42,
            $row->getRows(),
            'Row count is supplied by the caller.',
        );
    }

    public function testFromTimingStartsTheDuplicateCountAtOne(): void
    {
        $row = QueryRow::fromTiming(
            [
                'info' => 'SELECT 1',
                'category' => 'yii\\db\\Command::query',
                'timestamp' => 1.0,
                'trace' => [],
                'level' => 0,
                'duration' => 0.001,
                'memory' => 0,
                'memoryDiff' => 0,
                'traceHash' => 'hash',
            ],
            'SELECT',
            0,
            null,
        );

        self::assertSame(
            1,
            $row->getDuplicate(),
            'A freshly captured row counts as a single occurrence.',
        );
    }

    public function testGettersExposeEveryPersistedField(): void
    {
        $payload = self::payload(
            [
                'type' => 'update',
                'query' => 'UPDATE users SET active = 1',
                'duration' => 2.5,
                'trace' => [['file' => '/app.php', 'line' => 12]],
                'traceHash' => 'captured-hash',
                'timestamp' => 1234.0,
                'seq' => 7,
                'duplicate' => 3,
                'rows' => 42,
            ],
        );

        $row = QueryRow::fromArray($payload, '$.row');

        self::assertSame(
            $payload,
            [
                'type' => $row->getType(),
                'query' => $row->getQuery(),
                'duration' => $row->getDuration(),
                'trace' => $row->getTrace(),
                'traceHash' => $row->getTraceHash(),
                'timestamp' => $row->getTimestamp(),
                'seq' => $row->getSequence(),
                'duplicate' => $row->getDuplicate(),
                'rows' => $row->getRows(),
            ],
            'Typed getters must expose every persisted value without coercion.',
        );
    }

    public function testGetTraceReturnsAnIndependentArray(): void
    {
        $row = QueryRow::create('SELECT 1', 1.0, 0.0)->withTrace([['file' => '/original.php', 'line' => 12]]);

        $trace = $row->getTrace();

        $trace[0]['file'] = '/changed.php';

        self::assertSame(
            [['file' => '/original.php', 'line' => 12]],
            $row->getTrace(),
            'Editing returned trace frames must not mutate the captured row.',
        );
    }

    public function testReturnNewInstanceWhenSettingAttribute(): void
    {
        $row = QueryRow::create('SELECT 1', 1.0, 0.0);

        self::assertNotSame(
            $row,
            $row->withDuplicate(2),
            'New instance must be returned (immutability).',
        );
        self::assertNotSame(
            $row,
            $row->withRows(5),
            'New instance must be returned (immutability).',
        );
        self::assertNotSame(
            $row,
            $row->withSequence(3),
            'New instance must be returned (immutability).',
        );
        self::assertNotSame(
            $row,
            $row->withTrace([['file' => '/app.php', 'line' => 7]]),
            'New instance must be returned (immutability).',
        );
        self::assertNotSame(
            $row,
            $row->withTraceHash('captured-hash'),
            'New instance must be returned (immutability).',
        );
        self::assertNotSame(
            $row,
            $row->withType('UPDATE'),
            'New instance must be returned (immutability).',
        );
    }

    public function testReturnNewInstanceWhenSettingUnchangedAttribute(): void
    {
        $row = QueryRow::create('SELECT 1', 1.0, 0.0);

        self::assertNotSame(
            $row,
            $row->withDuplicate(1),
            'New instance must be returned even when the duplicate count is unchanged.',
        );
        self::assertNotSame(
            $row,
            $row->withRows(null),
            'New instance must be returned even when the row count is `null`.',
        );
        self::assertNotSame(
            $row,
            $row->withSequence(0),
            'New instance must be returned even when the sequence is unchanged.',
        );
        self::assertNotSame(
            $row,
            $row->withTrace([]),
            'New instance must be returned even when the trace is empty.',
        );
        self::assertNotSame(
            $row,
            $row->withTraceHash(''),
            'New instance must be returned even when the caller hash is empty.',
        );
        self::assertNotSame(
            $row,
            $row->withType('SELECT'),
            'New instance must be returned even when the command verb is unchanged.',
        );
    }

    public function testThrowHydrationExceptionWhenDurationIsANumericString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            Message::SNAPSHOT_VALUE_INVALID->getMessage('$.panels.db.entries[0].duration', 'a number'),
        );

        QueryRow::fromArray(self::payload(['duration' => '5.0']), '$.panels.db.entries[0]');
    }

    public function testThrowHydrationExceptionWhenTraceIsNotAListOfObjects(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            Message::SNAPSHOT_VALUE_INVALID->getMessage('$.panels.db.entries[0].trace[0]', 'an object'),
        );

        QueryRow::fromArray(self::payload(['trace' => ['not-a-frame']]), '$.panels.db.entries[0]');
    }

    public function testWithersPreserveUnrelatedFieldsAndTheSourceRow(): void
    {
        $payload = self::payload(
            [
                'query' => 'SELECT * FROM users',
                'duration' => 2.5,
                'trace' => [['file' => '/original.php', 'line' => 12]],
                'timestamp' => 1234.0,
                'seq' => 7,
                'duplicate' => 3,
                'rows' => 42,
            ],
        );

        $row = QueryRow::fromArray($payload, '$.row');

        $trace = [
            [
                'file' => '/changed.php',
                'line' => 24,
            ],
        ];

        $changes = [
            [
                $row->withDuplicate(4),
                ['duplicate' => 4],
            ],
            [
                $row->withRows(0),
                ['rows' => 0],
            ],
            [
                $row->withRows(null),
                ['rows' => null],
            ],
            [
                $row->withSequence(0),
                ['seq' => 0],
            ],
            [
                $row->withTrace($trace),
                [
                    'trace' => $trace,
                    'traceHash' => hash('sha256', json_encode($trace, JSON_THROW_ON_ERROR)),
                ],
            ],
            [
                $row->withTrace([]),
                [
                    'trace' => [],
                    'traceHash' => '',
                ],
            ],
            [
                $row->withTraceHash('captured-hash'),
                ['traceHash' => 'captured-hash'],
            ],
            [
                $row->withTraceHash(''),
                ['traceHash' => ''],
            ],
            [
                $row->withType('delete'),
                ['type' => 'delete'],
            ],
            [
                $row->withType(''),
                ['type' => ''],
            ],
        ];

        foreach ($changes as [$changed, $overrides]) {
            self::assertSame(
                [...$payload, ...$overrides],
                $changed->jsonSerialize(),
                'Only the configured fields may change, without normalizing explicit values.',
            );
        }

        self::assertSame(
            $payload,
            $row->jsonSerialize(),
            'Configuring copies must preserve every field of the source row.',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function payload(array $overrides = []): array
    {
        return [
            'type' => 'SELECT',
            'query' => 'SELECT 1',
            'duration' => 1.0,
            'trace' => [],
            'traceHash' => 'hash',
            'timestamp' => 1.0,
            'seq' => 0,
            'duplicate' => 1,
            'rows' => null,
            ...$overrides,
        ];
    }
}

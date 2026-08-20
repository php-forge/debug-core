<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Profile;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ProfileRow} covering the capture-time narrowing of logger timings and the strict JSON hydration
 * that restores them without coercion.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileRowTest extends TestCase
{
    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = new ProfileRow(
            timestamp: 1_700_000_000_000.0,
            duration: 12.5,
            category: 'yii\\db\\Command::query',
            info: 'SELECT *',
            level: 2,
            seq: 3,
            memory: 1_572_864,
            memoryDiff: 1_048_576,
            trace: [['file' => '/app/index.php', 'line' => 12]],
        );

        self::assertEquals(
            $row,
            ProfileRow::fromArray(
                $row->jsonSerialize(),
                '$.panels.profiling.entries[0]',
            ),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromTimingUsesCanonicalFieldsAndScalesToMilliseconds(): void
    {
        $row = ProfileRow::fromTiming(
            [
                'timestamp' => 2.5,
                'duration' => 0.125,
                'category' => 'database',
                'info' => 'SELECT 1',
                'level' => 2,
                'memory' => 2_048,
                'memoryDiff' => 512,
                'trace' => [['file' => 'index.php']],
            ],
            7,
        );

        self::assertSame(
            2_500.0,
            $row->timestamp,
            'Timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            125.0,
            $row->duration,
            'Duration must be scaled to milliseconds.',
        );
        self::assertSame(
            2,
            $row->level,
            'Nesting level must come from the canonical timing.',
        );
        self::assertSame(
            2_048,
            $row->memory,
            'Memory must come from the canonical timing.',
        );
        self::assertSame(
            512,
            $row->memoryDiff,
            'Memory delta must come from the canonical timing.',
        );
        self::assertSame(
            7,
            $row->seq,
            'Sequence index is assigned by the caller.',
        );
        self::assertSame(
            'database',
            $row->category,
            'Category must come from the canonical timing.',
        );
        self::assertSame(
            'SELECT 1',
            $row->info,
            'Profile token must come from the canonical timing.',
        );
        self::assertSame(
            [['file' => 'index.php']],
            $row->trace,
            'Trace frames must be preserved.',
        );
    }

    public function testMaxDurationReturnsTheLongestBlock(): void
    {
        self::assertSame(
            0.0,
            ProfileRow::maxDuration([]),
            'An empty capture has no maximum.',
        );
        self::assertSame(
            12.5,
            ProfileRow::maxDuration(
                [self::row(1.0), self::row(12.5), self::row(3.0)]
            ),
            'The longest block wins.',
        );
    }

    public function testThrowHydrationExceptionWhenDurationIsANumericString(): void
    {
        $this->expectException(HydrationException::class);

        ProfileRow::fromArray(
            [
                'timestamp' => 1.0,
                'duration' => '12.5',
                'category' => 'app',
                'info' => '',
                'level' => 0,
                'seq' => 0,
                'memory' => 0,
                'memoryDiff' => 0,
                'trace' => [],
            ],
            '$.panels.profiling.entries[0]',
        );
    }

    private static function row(float $duration): ProfileRow
    {
        return new ProfileRow(0.0, $duration, 'app', '', 0, 0, 0, 0, []);
    }
}

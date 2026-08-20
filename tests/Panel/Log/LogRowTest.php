<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Log\LogRow;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LogRow} covering the capture-time narrowing of Yii logger tuples and the strict JSON hydration
 * that restores them without coercion.
 */
#[Group('panel')]
#[Group('log')]
final class LogRowTest extends TestCase
{
    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = new LogRow(
            id: 7,
            message: 'boom',
            level: LogLevel::ERROR,
            category: 'yii\\db\\Command::query',
            time: 1_700_000_000_500.0,
            timeOfPrevious: 1_700_000_000_000.0,
            timeSincePrevious: 0.5,
            idOfPrevious: 6,
            idOfNext: 8,
            memory: 2_048,
            trace: [['file' => '/app/index.php', 'line' => 12]],
        );

        self::assertEquals(
            $row,
            LogRow::fromArray($row->jsonSerialize(), '$.panels.log.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromLoggerTupleUsesCanonicalFieldsAndDefaultsOptionalMemory(): void
    {
        $row = LogRow::fromLoggerTuple(
            [
                'message',
                LogLevel::INFO,
                'app',
                2.5,
                [['file' => 'a.php']],
            ],
            3,
            1.5,
            2,
            4,
        );

        self::assertSame(
            3,
            $row->id,
            'Row id is assigned by the caller.',
        );
        self::assertSame(
            LogLevel::INFO,
            $row->level,
            'Level must come from the canonical logger tuple.',
        );
        self::assertSame(
            'app',
            $row->category,
            'Category must come from the logger tuple category position.',
        );
        self::assertSame(
            2_500.0,
            $row->time,
            'Timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            1_500.0,
            $row->timeOfPrevious,
            'Previous timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            1.0,
            $row->timeSincePrevious,
            'Delta stays in seconds.',
        );
        self::assertSame(
            0,
            $row->memory,
            'Omitted memory must default to zero.',
        );
        self::assertSame(
            2,
            $row->idOfPrevious,
            'Previous id is assigned by the caller.',
        );
        self::assertSame(
            4,
            $row->idOfNext,
            'Next id is assigned by the caller.',
        );
        self::assertSame(
            [['file' => 'a.php']],
            $row->trace,
            'Trace frames must be preserved.',
        );
    }

    public function testThrowHydrationExceptionWhenLevelIsANumericString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.log.entries[0].level': expected an integer.",
        );

        LogRow::fromArray(
            self::payload(['level' => '4']),
            '$.panels.log.entries[0]',
        );
    }

    public function testThrowHydrationExceptionWhenTraceIsNotAListOfObjects(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.log.entries[0].trace[0]': expected an object.",
        );

        LogRow::fromArray(
            self::payload(['trace' => ['not-a-frame']]),
            '$.panels.log.entries[0]',
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
            'id' => 1,
            'message' => 'msg',
            'level' => LogLevel::INFO,
            'category' => 'app',
            'time' => 1.0,
            'timeOfPrevious' => 1.0,
            'timeSincePrevious' => 0.0,
            'idOfPrevious' => null,
            'idOfNext' => null,
            'memory' => 0,
            'trace' => [],
            ...$overrides,
        ];
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Dump;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Dump\DumpRow;
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DumpRow} covering the capture-time narrowing of Yii logger tuples and the strict JSON hydration
 * that restores them without coercion.
 */
#[Group('panel')]
#[Group('dump')]
final class DumpRowTest extends TestCase
{
    public function testFromArrayRoundTripsEveryField(): void
    {
        $row = new DumpRow(
            message: '&lt;?php "hello"',
            level: LogLevel::TRACE,
            category: 'application',
            time: 1_700_000_000_500.0,
            trace: [['file' => '/app/index.php', 'line' => 12]],
        );

        self::assertEquals(
            $row,
            DumpRow::fromArray($row->jsonSerialize(), '$.panels.dump.entries[0]'),
            'Round-trip must preserve every field.',
        );
    }

    public function testFromLoggerTupleUsesCanonicalFieldsAndScalesTime(): void
    {
        $row = DumpRow::fromLoggerTuple(
            [
                'msg',
                LogLevel::TRACE,
                'app',
                2.5,
                [['file' => 'a.php']],
            ],
        );

        self::assertSame(
            'msg',
            $row->message,
            'Payload must round-trip verbatim.',
        );
        self::assertSame(
            LogLevel::TRACE,
            $row->level,
            'Level must come from the canonical logger tuple.',
        );
        self::assertSame(
            'app',
            $row->category,
            'Category must come from the canonical logger tuple.',
        );
        self::assertSame(
            2_500.0,
            $row->time,
            'Timestamp must be scaled to milliseconds.',
        );
        self::assertSame(
            [['file' => 'a.php']],
            $row->trace,
            'Trace frames must be preserved.',
        );
    }

    public function testThrowHydrationExceptionWhenAFieldIsMissing(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.dump.entries[0].trace': expected a required field.",
        );

        DumpRow::fromArray(
            [
                'message' => 'msg',
                'level' => LogLevel::TRACE,
                'category' => 'app',
                'time' => 1.0,
            ],
            '$.panels.dump.entries[0]',
        );
    }

    public function testThrowHydrationExceptionWhenTimeIsANumericString(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.dump.entries[0].time': expected a number.",
        );

        DumpRow::fromArray(
            [
                'message' => 'msg',
                'level' => LogLevel::TRACE,
                'category' => 'app',
                'time' => '2500',
                'trace' => [],
            ],
            '$.panels.dump.entries[0]',
        );
    }
}

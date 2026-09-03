<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Log\{LogCounts, LogRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see LogCounts} covering the level totals derived from the captured rows.
 */
#[Group('panel')]
#[Group('log')]
final class LogCountsTest extends TestCase
{
    public function testFromRowsAggregatesLevelsCorrectly(): void
    {
        $counts = LogCounts::fromRows(
            [
                self::row(LogLevel::INFO),
                self::row(LogLevel::ERROR),
                self::row(LogLevel::WARNING),
                self::row(LogLevel::ERROR),
                self::row(LogLevel::TRACE),
            ],
        );

        self::assertSame(
            5,
            $counts->total,
            'Total must span every level.',
        );
        self::assertSame(
            2,
            $counts->errors,
            'Two rows are at error level.',
        );
        self::assertSame(
            1,
            $counts->warnings,
            'One row is at warning level.',
        );
        self::assertSame(
            1,
            $counts->info,
            'One row is at info level.',
        );
        self::assertSame(
            1,
            $counts->trace,
            'One row is at trace level.',
        );
    }

    public function testFromRowsExposesHasFlagsForNonZeroCounts(): void
    {
        $counts = LogCounts::fromRows([self::row(LogLevel::ERROR), self::row(LogLevel::TRACE)]);

        self::assertTrue(
            $counts->hasErrors(),
            'A captured error must raise the flag.',
        );
        self::assertFalse(
            $counts->hasWarnings(),
            'No warning was captured.',
        );
        self::assertFalse(
            $counts->hasInfo(),
            'No info entry was captured.',
        );
        self::assertTrue(
            $counts->hasTrace(),
            'A captured trace must raise the flag.',
        );
    }

    public function testFromRowsIncludesUnknownLevelsOnlyInTotal(): void
    {
        $counts = LogCounts::fromRows([self::row(0x999)]);

        self::assertSame(
            1,
            $counts->total,
            'An unknown level must still contribute to the total.',
        );
        self::assertSame(
            0,
            $counts->errors,
            'An unknown level must not count as an error.',
        );
        self::assertSame(
            0,
            $counts->warnings,
            'An unknown level must not count as a warning.',
        );
        self::assertSame(
            0,
            $counts->info,
            'An unknown level must not count as info.',
        );
        self::assertSame(
            0,
            $counts->trace,
            'An unknown level must not count as trace.',
        );
    }

    public function testFromRowsReturnsAllZeroCountsWhenNoRowsWereCaptured(): void
    {
        $counts = LogCounts::fromRows([]);

        self::assertSame(
            0,
            $counts->total,
            'Empty capture must total zero.',
        );
        self::assertFalse(
            $counts->hasErrors(),
            'Empty capture must report no errors.',
        );
        self::assertFalse(
            $counts->hasWarnings(),
            'Empty capture must report no warnings.',
        );
        self::assertFalse(
            $counts->hasInfo(),
            'Empty capture must report no info entries.',
        );
        self::assertFalse(
            $counts->hasTrace(),
            'Empty capture must report no trace entries.',
        );
    }

    public function testTraceCountDefaultsToZeroForBackwardCompatibleConstruction(): void
    {
        $counts = new LogCounts(
            total: 1,
            errors: 0,
            warnings: 0,
            info: 1,
        );

        self::assertSame(
            0,
            $counts->trace,
            'Existing construction without an explicit trace count must remain valid.',
        );
    }

    private static function row(int $level): LogRow
    {
        return new LogRow(
            id: 1,
            message: 'message',
            level: $level,
            category: 'app',
            time: 1_000.0,
            timeOfPrevious: 1_000.0,
            timeSincePrevious: 0.0,
            idOfPrevious: null,
            idOfNext: null,
            memory: 0,
            trace: [],
        );
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Format;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for {@see Format} covering the megabyte readout, the trimmed CSS percentage formatter, the millisecond and
 * relative-age labels, the wall-clock readout, and the value type labels.
 */
#[Group('helpers')]
#[Group('format')]
final class FormatTest extends TestCase
{
    public function testBytesToMbFormatsWithRequestedPrecision(): void
    {
        self::assertSame(
            '2.00 MB',
            Format::bytesToMb(2_097_152),
            'Default precision must keep two decimals.',
        );
        self::assertSame(
            '2.000 MB',
            Format::bytesToMb(2_097_152, 3),
            'Explicit precision must widen the decimals.',
        );
    }

    public function testCssPercentTrimsTrailingZerosAndDot(): void
    {
        $cases = [
            '0%' => 0.0,
            '50%' => 50.0,
            '100%' => 100.0,
            '12.5%' => 12.5,
            '33.333%' => 1 / 3 * 100,
            '0.001%' => 0.001,
        ];

        foreach ($cases as $expected => $value) {
            self::assertSame(
                $expected,
                Format::cssPercent($value),
                "Value '{$value}' must format as '{$expected}'.",
            );
        }
    }

    public function testMillisecondsConvertsSecondsAndGroupsThousands(): void
    {
        self::assertSame(
            '0 ms',
            Format::milliseconds(0.0),
            'Zero seconds must render without decimals.',
        );
        self::assertSame(
            '123 ms',
            Format::milliseconds(0.1234),
            'Sub-second values must scale to milliseconds.',
        );
        self::assertSame(
            '1,235 ms',
            Format::milliseconds(1.2345),
            'Thousands must be grouped and the fraction rounded.',
        );
    }

    public function testMillisecondsHonoursRequestedDecimals(): void
    {
        self::assertSame(
            '12.5 ms',
            Format::milliseconds(0.0125, 1),
            'Explicit precision must widen the decimals.',
        );
        self::assertSame(
            '12,500.000 ms',
            Format::milliseconds(12.5, 3),
            'Grouping must survive a widened precision.',
        );
    }

    public function testRelativeTimeKeepsBucketBoundariesExclusive(): void
    {
        self::assertSame(
            'just now',
            Format::relativeTime(59, 'FALLBACK'),
            'Last second below a minute must stay in the first bucket.',
        );
        self::assertSame(
            'FALLBACK',
            Format::relativeTime(2_592_000, 'FALLBACK'),
            'Thirty days must switch to the absolute label.',
        );
    }

    public function testRelativeTimeLabelsEachAgeBucket(): void
    {
        $cases = [
            'just now' => 0,
            '1 min ago' => 60,
            '59 min ago' => 3599,
            '1 h ago' => 3600,
            '23 h ago' => 86399,
            '1 d ago' => 86400,
            '29 d ago' => 2591999,
        ];

        foreach ($cases as $expected => $elapsed) {
            self::assertSame(
                $expected,
                Format::relativeTime($elapsed, 'FALLBACK'),
                "Age of {$elapsed} s must read '{$expected}'.",
            );
        }
    }

    public function testTimeOfDayAppendsPaddedMillisecondFraction(): void
    {
        self::assertSame(
            '22:13:20.123',
            Format::timeOfDay(1_700_000_000_123),
            'Fraction must follow the second-precision part.',
        );
        self::assertSame(
            '22:13:20.007',
            Format::timeOfDay(1_700_000_000_007),
            'Fraction must be zero-padded to three digits.',
        );
        self::assertSame(
            '22:13:20.000',
            Format::timeOfDay(1_700_000_000_000),
            'Whole seconds must still carry a fraction.',
        );
    }

    public function testTimeOfDayAppliesTheRequestedDateFormat(): void
    {
        self::assertSame(
            '2023-11-14 22:13:20.123',
            Format::timeOfDay(1_700_000_000_123, 'Y-m-d H:i:s'),
            'Custom format must prefix the fraction.',
        );
    }

    public function testTimeOfDayNormalizesNegativeFractions(): void
    {
        self::assertSame(
            '23:59:59.500',
            Format::timeOfDay(-1_500),
            'Pre-epoch input must keep a three-digit unsigned fraction.',
        );
    }

    public function testTypeOfLabelsScalarsArraysStringsAndNull(): void
    {
        self::assertSame(
            'array(3)',
            Format::typeOf([1, 2, 3]),
            'Arrays must report their element count.',
        );
        self::assertSame(
            'array(0)',
            Format::typeOf([]),
            'Empty arrays must report a zero count.',
        );
        self::assertSame(
            'string(16)',
            Format::typeOf('Test application'),
            'Strings must report their byte length.',
        );
        self::assertSame(
            'string(0)',
            Format::typeOf(''),
            'Empty strings must report a zero length.',
        );
        self::assertSame(
            'int',
            Format::typeOf(42),
            "Integers must be labeled 'int'."
        );
        self::assertSame(
            'float',
            Format::typeOf(1.5),
            "Floats must be labeled 'float'.",
        );
        self::assertSame(
            'bool',
            Format::typeOf(true),
            "Booleans must be labeled 'bool'."
        );
        self::assertSame(
            'null',
            Format::typeOf(null),
            "'null' must be labeled 'null'."
        );
        self::assertSame(
            'object',
            Format::typeOf(new stdClass()),
            'Unlisted types must fall back to the native type name.',
        );
    }
}

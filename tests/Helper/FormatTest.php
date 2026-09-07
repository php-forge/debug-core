<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Format;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Unit tests for {@see Format} covering the megabyte readout, the trimmed CSS percentage formatter, and the value
 * type labels.
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

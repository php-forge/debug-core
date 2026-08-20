<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Coerce;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Stringable;

/**
 * Unit tests for {@see Coerce} normalizing mixed values.
 */
#[Group('helpers')]
#[Group('coerce')]
final class CoerceTest extends TestCase
{
    public function testFloatAcceptsNumericValuesAndUsesConfiguredDefault(): void
    {
        self::assertSame(
            12.5,
            Coerce::float('12.5'),
            'Numeric string must become a float.',
        );
        self::assertSame(
            0.0,
            Coerce::float([]),
            'Implicit float default must be zero.',
        );
        self::assertSame(
            7.5,
            Coerce::float([], 7.5),
            'Non-numeric input must use the configured float default.',
        );
        self::assertNull(
            Coerce::floatOrNull([]),
            "Non-numeric input must yield 'null'.",
        );
    }

    public function testIntAcceptsNumericValuesAndUsesConfiguredDefault(): void
    {
        self::assertSame(
            12,
            Coerce::int('12.9'),
            'Numeric string must become an int.',
        );
        self::assertSame(
            0,
            Coerce::int([]),
            'Implicit int default must be zero.',
        );
        self::assertSame(
            7,
            Coerce::int([], 7),
            'Non-numeric input must use the configured int default.',
        );
        self::assertNull(
            Coerce::intOrNull([]),
            "Non-numeric input must yield 'null'.",
        );
    }

    public function testStringAcceptsOnlyStringsAndUsesConfiguredDefault(): void
    {
        self::assertSame(
            'debug',
            Coerce::string('debug'),
            'String input must be preserved.',
        );
        self::assertSame(
            'fallback',
            Coerce::string(42, 'fallback'),
            'Non-string input must use the configured default.',
        );
    }

    public function testStringKeyedArrayDropsIntegerKeysAndPreservesOrder(): void
    {
        self::assertSame(
            ['first' => 1, 'second' => 3],
            Coerce::stringKeyedArray(['first' => 1, 2, 'second' => 3]),
            'Integer keys must be dropped and order preserved.',
        );
    }

    public function testStringListKeepsOnlyStringEntriesAndFallsBackOnNonArray(): void
    {
        self::assertSame(
            ['kept-a', 'kept-b'],
            Coerce::stringList(['kept-a', 42, null, 'kept-b']),
            'Only string entries must survive, in order.',
        );
        self::assertSame(
            [],
            Coerce::stringList('not-an-array'),
            "Non-array input must collapse to '[]'.",
        );
    }

    public function testStringOrNullAcceptsScalarsAndStringableObjects(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable';
            }
        };

        self::assertSame(
            '42',
            Coerce::stringOrNull(42),
            'Scalar input must be converted to a string.',
        );
        self::assertSame(
            'stringable',
            Coerce::stringOrNull($stringable),
            'Stringable input must use its string representation.',
        );
        self::assertNull(
            Coerce::stringOrNull([]),
            "Unsupported input must yield 'null'.",
        );
    }

    public function testTraceFramesDropsMalformedFramesAndIntegerKeys(): void
    {
        self::assertSame(
            [
                [
                    'file' => '/app/index.php',
                    'line' => 10,
                ],
                ['function' => 'run'],
            ],
            Coerce::traceFrames(
                [
                    [
                        'file' => '/app/index.php',
                        'line' => 10,
                        0 => 'drop',
                    ],
                    'drop',
                    ['function' => 'run'],
                ],
            ),
            'Only array frames and string-keyed entries must survive.',
        );
        self::assertSame(
            [],
            Coerce::traceFrames('invalid'),
            "Non-array trace input must collapse to '[]'.",
        );
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Profile;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Profile\ProfileTimings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ProfileTimings} pairing repeated and interleaved profiler tokens.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfileTimingsTest extends TestCase
{
    public function testCalculatePairsNestedIdenticalTokensWithoutLosingOuterFrame(): void
    {
        $timings = ProfileTimings::calculate(
            [
                ['same', LogLevel::PROFILE_BEGIN, 'application', 1.0, [], 100],
                ['same', LogLevel::PROFILE_BEGIN, 'application', 1.1, [], 110],
                ['same', LogLevel::PROFILE_END, 'application', 1.2, [], 120],
                ['same', LogLevel::PROFILE_END, 'application', 1.3, [], 140],
            ],
        );

        self::assertCount(
            2,
            $timings,
            'Both nested frames with the same token must survive pairing.',
        );

        $outer = $timings[0] ?? self::fail('Expected the outer repeated-token timing.');
        $inner = $timings[1] ?? self::fail('Expected the inner repeated-token timing.');

        self::assertSame(
            0,
            $outer['level'],
            'Outer repeated token must retain nesting level zero.',
        );
        self::assertSame(
            1,
            $inner['level'],
            'Inner repeated token must retain nesting level one.',
        );
        self::assertEqualsWithDelta(
            0.3,
            $outer['duration'],
            PHP_FLOAT_EPSILON,
            'Outer duration must pair last.',
        );
        self::assertEqualsWithDelta(
            0.1,
            $inner['duration'],
            PHP_FLOAT_EPSILON,
            'Inner duration must pair first.',
        );
        self::assertSame(
            40,
            $outer['memoryDiff'],
            'Outer memory delta must use the outer begin frame.',
        );
        self::assertSame(
            10,
            $inner['memoryDiff'],
            'Inner memory delta must use the inner begin frame.',
        );
    }

    public function testCalculateResetsNestingAfterEachSequentialPair(): void
    {
        self::assertSame(
            [
                [
                    'info' => 'first',
                    'category' => 'application',
                    'timestamp' => 1.0,
                    'trace' => [],
                    'level' => 0,
                    'duration' => 0.5,
                    'memory' => 110,
                    'memoryDiff' => 10,
                ],
                [
                    'info' => 'second',
                    'category' => 'database',
                    'timestamp' => 2.0,
                    'trace' => [],
                    'level' => 0,
                    'duration' => 0.25,
                    'memory' => 0,
                    'memoryDiff' => 0,
                ],
            ],
            ProfileTimings::calculate(
                [
                    [
                        'first',
                        LogLevel::PROFILE_BEGIN,
                        'application',
                        1.0,
                        [],
                        100,
                    ],
                    [
                        'first',
                        LogLevel::PROFILE_END,
                        'application',
                        1.5,
                        [],
                        110,
                    ],
                    [
                        'second',
                        LogLevel::PROFILE_BEGIN,
                        'database',
                        2.0,
                        [],
                    ],
                    [
                        'second',
                        LogLevel::PROFILE_END,
                        'database',
                        2.25,
                        [],
                    ],
                ],
            ),
            'Completed pairs must restore nesting level zero while preserving canonical tuple fields.',
        );
    }
}

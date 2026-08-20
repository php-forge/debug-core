<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Timeline;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPForge\Debug\Panel\Timeline\TimelineGeometry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see TimelineGeometry} producing adaptive ruler ticks and positioned span rows.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelineGeometryTest extends TestCase
{
    public function testRulersUseAdaptiveRoundSteps(): void
    {
        self::assertSame(
            [
                0 => 0.0,
                20 => 20.0,
                40 => 40.0,
                60 => 60.0,
                80 => 80.0,
            ],
            TimelineGeometry::rulers(100.0),
            'A 100 ms request must use uncluttered 20 ms ticks.',
        );
        self::assertSame(
            [],
            TimelineGeometry::rulers(0.0),
            'A zero duration must not emit rulers.',
        );
        self::assertSame(
            [],
            TimelineGeometry::rulers(100.0, 0),
            'A disabled ruler must not emit ticks.',
        );
        self::assertSame(
            [0 => 0.0],
            TimelineGeometry::rulers(100.0, 1),
            'A one-line ruler target must remain valid and retain the origin.',
        );
        self::assertSame(
            [
                0 => 0.0,
                5 => 27.77777777777778,
                10 => 55.55555555555556,
                15 => 83.33333333333334,
            ],
            TimelineGeometry::rulers(18.0),
            'A normalized duration up to five must use five-unit ticks.',
        );
        self::assertSame(
            [
                0 => 0.0,
                10 => 32.25806451612903,
                20 => 64.51612903225806,
            ],
            TimelineGeometry::rulers(31.0),
            'A normalized duration above five must use ten-unit ticks.',
        );
        self::assertSame(
            [0 => 0.0],
            TimelineGeometry::rulers(1.0),
            'A duration shorter than one complete step must keep only the origin.',
        );
        self::assertSame(
            [
                0 => 0.0,
                1 => 16.666666666666664,
                2 => 33.33333333333333,
                3 => 50.0, 4 => 66.66666666666666,
                5 => 83.33333333333334,
            ],
            TimelineGeometry::rulers(6.0),
            'The default six-line target and normalized-one boundary must use one-unit ticks.',
        );
        self::assertSame(
            [
                0 => 0.0,
                2 => 16.666666666666664,
                4 => 33.33333333333333,
                6 => 50.0,
                8 => 66.66666666666666,
                10 => 83.33333333333334,
            ],
            TimelineGeometry::rulers(12.0),
            'The normalized-two boundary must use two-unit ticks.',
        );
        self::assertSame(
            [
                0 => 0.0,
                5 => 16.666666666666664,
                10 => 33.33333333333333,
                15 => 50.0,
                20 => 66.66666666666666,
                25 => 83.33333333333334,
            ],
            TimelineGeometry::rulers(30.0),
            'The normalized-five boundary must use five-unit ticks.',
        );
        self::assertSame(
            [
                0 => 0.0,
                5 => 20.833333333333336,
                10 => 41.66666666666667,
                15 => 62.5,
                20 => 83.33333333333334,
            ],
            TimelineGeometry::rulers(24.0),
            'Ruler magnitude selection must use floor rather than nearest rounding.',
        );
        self::assertSame(
            [0 => 0.0],
            TimelineGeometry::rulers(1.2),
            'A tick below the one-quarter end margin must be omitted.',
        );
        self::assertSame(
            [
                0 => 0.0,
                1 => 80.0,
            ],
            TimelineGeometry::rulers(1.25),
            'A tick exactly on the one-quarter end margin must be retained.',
        );
        self::assertSame(
            [
                0 => 0.0,
                50 => 16.666666666666664,
                100 => 33.33333333333333,
                150 => 50.0,
                200 => 66.66666666666666,
                250 => 83.33333333333334,
            ],
            TimelineGeometry::rulers(300.0),
            'Five-step scaling must multiply by the magnitude.',
        );
        self::assertSame(
            [
                0 => 0.0,
                100 => 27.77777777777778,
                200 => 55.55555555555556,
                300 => 83.33333333333334,
            ],
            TimelineGeometry::rulers(360.0),
            'Ten-step scaling must multiply by the magnitude.',
        );
    }

    public function testSpansUseTheSharedRequestGeometry(): void
    {
        $spans = TimelineGeometry::spans(
            [
                new ProfileRow(1_025.0, 10.0, 'Yiisoft\\Db\\Command::query', 'SELECT 1', 1, 0, 0, 0, []),
            ],
            1_000.0,
            100.0,
        );

        self::assertCount(
            1,
            $spans,
            'Every profile row must produce one positioned span.',
        );

        $span = $spans[0] ?? self::fail('Expected one positioned span.');

        self::assertSame(
            '25',
            $span->cssLeft,
            'Timestamp offset must become a percentage.',
        );
        self::assertSame(
            '10',
            $span->cssWidth,
            'Duration must become a percentage.',
        );
        self::assertSame(
            1,
            $span->depth,
            'Profiler nesting must reach the span row.',
        );
        self::assertSame(
            [],
            TimelineGeometry::spans(
                [new ProfileRow(0.0, 1.0, 'category', 'info', 0, 0, 0, 0, [])],
                0.0,
                0.0,
            ),
            'A zero duration must not produce spans.',
        );
    }
}

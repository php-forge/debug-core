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
            [0 => 0.0, 20 => 20.0, 40 => 40.0, 60 => 60.0, 80 => 80.0],
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
            TimelineGeometry::spans([], 0.0, 0.0),
            'A zero duration must not produce spans.',
        );
    }
}

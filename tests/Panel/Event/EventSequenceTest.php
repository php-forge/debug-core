<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Event;

use PHPForge\Debug\Panel\Event\{EventInspection, EventRow, EventSequence};
use PHPForge\Debug\Tests\Provider\EventSequenceProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Tests unavailable chronology and the consistency requirements for observed lifecycle intervals.
 */
#[Group('event')]
final class EventSequenceTest extends TestCase
{
    public function testEmptySequenceDoesNotInventChronology(): void
    {
        $sequence = new EventSequence([]);

        $row = new EventRow(100.0, 'event', 'Event', '0', 'Worker');

        self::assertSame(
            0,
            $sequence->index($row),
            'An unobserved row must not receive a capture identity.',
        );
        self::assertSame(
            0.0,
            $sequence->elapsed($row),
            'An empty capture has no earlier time reference.',
        );
        self::assertNull(
            $sequence->gap($row),
            'An empty capture cannot have a previous observation.',
        );
        self::assertNull(
            $sequence->interval($row),
            'An empty capture cannot imply a measured interval.',
        );
    }

    public function testIntervalAcceptsEqualMonotonicClocks(): void
    {
        $start = self::row('enter', 10.0);
        $end = self::row('leave', 10.0);

        $sequence = new EventSequence([$start, $end]);

        self::assertSame(
            0.0,
            $sequence->interval($start),
            'A valid zero-length interval must remain distinct from unavailable data.',
        );
        self::assertNull(
            $sequence->interval($end),
            'Only the entry marker may display the paired interval.',
        );
    }

    #[DataProviderExternal(EventSequenceProvider::class, 'inconsistentPairs')]
    public function testIntervalRejectsInconsistentPairs(string $sender, string $phase, int $depth, float|null $clock): void
    {
        $start = self::row('enter', 10.0);
        $end = self::row($phase, $clock, $depth, $sender);

        $sequence = new EventSequence([$start, $end]);

        self::assertNull(
            $sequence->interval($start),
            'Matching correlation IDs alone must not turn inconsistent lifecycle observations into an interval.',
        );
    }

    private static function row(string $phase, float|null $clock, int $depth = 1, string $sender = 'Worker'): EventRow
    {
        return (new EventRow(100.0, $phase, 'Event', '0', $sender))
            ->withInspection((new EventInspection())->withLifecycle(1, $phase, $depth, $clock));
    }
}

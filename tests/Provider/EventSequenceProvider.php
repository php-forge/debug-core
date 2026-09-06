<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Event\EventSequenceTest;

/**
 * Provides inconsistent lifecycle pairs for {@see EventSequenceTest}.
 */
final class EventSequenceProvider
{
    /**
     * @return iterable<string, array{string, string, int, float|null}>
     */
    public static function inconsistentPairs(): iterable
    {
        yield 'decreasing monotonic clock' => ['Worker', 'leave', 1, 9.5];
        yield 'different nesting depth' => ['Worker', 'leave', 2, 10.25];
        yield 'different source' => ['OtherWorker', 'leave', 1, 10.25];
        yield 'missing leave clock' => ['Worker', 'leave', 1, null];
        yield 'missing leave phase' => ['Worker', 'enter', 1, 10.25];
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Event\EventRowTest;

/**
 * Provides captured fields whose readonly contract is verified by {@see EventRowTest}.
 */
final class EventRowProvider
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function capturedProperties(): iterable
    {
        yield 'event class' => ['class'];
        yield 'event name' => ['name'];
        yield 'sender class' => ['senderClass'];
        yield 'static flag' => ['isStatic'];
        yield 'timestamp' => ['time'];
    }
}

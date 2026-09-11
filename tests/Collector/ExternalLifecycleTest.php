<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Collector;

use Acme\Debug\{Cache, CacheCollector, CachePanel};
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\RequestSummary;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Unit tests for {@see CollectorCoordinator} running an application-owned collector through a failed request.
 */
final class ExternalLifecycleTest extends TestCase
{
    public function testApplicationFailureStopsExternalCaptureBeforeNextRequest(): void
    {
        $collector = new CacheCollector(
            (new CachePanel())->id(),
            new NullLogger(),
        );
        $cache = new Cache($collector);
        $coordinator = new CollectorCoordinator([$collector]);
        $failure = new RuntimeException(
            'Application failed',
        );

        try {
            $coordinator->run(
                static function () use ($cache, $failure): never {
                    $cache->set('first', 'private');

                    throw $failure;
                }
            );
        } catch (RuntimeException $caught) {
            self::assertSame(
                $failure,
                $caught,
                'The application failure must stay primary.',
            );
        }

        self::assertNull(
            $collector->capture(),
            'A stopped collector must not expose the failed request data.',
        );

        $cache->get('outside');

        $snapshot = $coordinator->run(static fn() => $coordinator->capture(RequestSummary::create('next')));

        self::assertSame(
            ['schema' => 1, 'operations' => []],
            $snapshot->panels[$collector->id()] ?? null,
            'The next request must start from an observed empty capture.',
        );
        self::assertSame(
            [],
            $snapshot->failures,
            'A clean lifecycle must not record failures.',
        );
    }
}

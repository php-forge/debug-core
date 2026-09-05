<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\RequestHero;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for immutable Request identity and optional metadata.
 *
 * Test coverage.
 * - Constructor defaults and identity preservation.
 * - Independent copies, fluent composition, and array isolation.
 */
#[Group('panel')]
#[Group('request')]
final class RequestHeroTest extends TestCase
{
    public function testDefaultsRepresentUnavailableMetadata(): void
    {
        $hero = RequestHero::create('GET', '/orders');

        self::assertSame(
            'GET',
            $hero->getMethod(),
            'The method must retain the supplied identity.',
        );
        self::assertSame(
            '/orders',
            $hero->getUrl(),
            'The URL must retain the supplied identity.',
        );
        self::assertSame(
            0,
            $hero->getStatusCode(),
            'Missing response metadata must not imply success.',
        );
        self::assertSame(
            'none',
            $hero->getStatusVariant(),
            'Missing status must retain the neutral variant.',
        );
        self::assertSame(
            '',
            $hero->getIp(),
            'An unavailable client address must remain empty.',
        );
        self::assertSame(
            '',
            $hero->getTime(),
            'An unavailable capture time must remain empty.',
        );
        self::assertSame(
            '',
            $hero->getDurationMs(),
            'An unavailable duration must remain empty.',
        );
        self::assertSame(
            [],
            $hero->getFlags(),
            'No display flags must be inferred.',
        );
    }

    public function testEveryOptionReturnsAnIndependentCopy(): void
    {
        $hero = RequestHero::create('POST', '/orders');

        foreach ([
            $hero->withStatus(201, '2xx'),
            $hero->withIp('127.0.0.1'),
            $hero->withTiming('12:00:00', '3.5 ms'),
            $hero->withFlags(['AJAX']),
        ] as $copy) {
            self::assertNotSame(
                $hero,
                $copy,
                'Each optional change must return a separate Request identity object.',
            );
        }

        self::assertEquals(
            RequestHero::create('POST', '/orders'),
            $hero,
            'Configuring optional metadata must not mutate the original Request identity.',
        );
    }

    public function testFlagsDoNotExposeWritableArrayState(): void
    {
        $flags = ['AJAX'];

        $hero = RequestHero::create('GET', '/')->withFlags($flags);

        $flags[] = 'PJAX';

        $returned = $hero->getFlags();

        $returned[] = 'HTTPS';

        self::assertSame(
            ['AJAX'],
            $hero->getFlags(),
            'Input and returned arrays must not mutate stored flags.',
        );
        self::assertSame(
            [],
            $hero->withFlags([])->getFlags(),
            'A separate copy must support clearing flags.',
        );
    }

    public function testFluentOptionsPreserveIdentityAndSiblingCopies(): void
    {
        $hero = RequestHero::create('POST', '/orders')
            ->withStatus(201, '2xx')
            ->withIp('127.0.0.1')
            ->withTiming('12:00:00', '3.5 ms')
            ->withFlags(['AJAX']);

        $failed = $hero->withStatus(500, '5xx');
        $untimed = $hero->withTiming('', '');

        self::assertSame(
            'POST',
            $hero->getMethod(),
            'Fluent options must preserve the method.',
        );
        self::assertSame(
            '/orders',
            $hero->getUrl(),
            'Fluent options must preserve the URL.',
        );
        self::assertSame(
            201,
            $hero->getStatusCode(),
            'A sibling response must not change the original status.',
        );
        self::assertSame(
            '2xx',
            $hero->getStatusVariant(),
            'The original status variant must remain paired with its code.',
        );
        self::assertSame(
            '127.0.0.1',
            $hero->getIp(),
            'Later options must retain the client address.',
        );
        self::assertSame(
            '12:00:00',
            $hero->getTime(),
            'A sibling without timing must not clear the original time.',
        );
        self::assertSame(
            '3.5 ms',
            $hero->getDurationMs(),
            'A sibling without timing must not clear the original duration.',
        );
        self::assertSame(
            ['AJAX'],
            $hero->getFlags(),
            'Fluent options must retain display flags.',
        );
        self::assertSame(
            500,
            $failed->getStatusCode(),
            'The changed copy must expose its response status.',
        );
        self::assertSame(
            '5xx',
            $failed->getStatusVariant(),
            'Response code and variant must change together.',
        );
        self::assertSame(
            '3.5 ms',
            $failed->getDurationMs(),
            'Changing the response must preserve timing.',
        );
        self::assertSame(
            '',
            $untimed->getTime(),
            'Timing must support clearing the capture time.',
        );
        self::assertSame(
            '',
            $untimed->getDurationMs(),
            'Timing must support clearing the duration.',
        );
        self::assertSame(
            201,
            $untimed->getStatusCode(),
            'Clearing timing must preserve the response.',
        );
    }
}

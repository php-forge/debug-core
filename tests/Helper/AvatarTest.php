<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Avatar;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Avatar} deriving deterministic avatar hues.
 */
#[Group('avatar')]
#[Group('helpers')]
final class AvatarTest extends TestCase
{
    public function testHueForNormalizesCaseAndReturnsStableHue(): void
    {
        self::assertSame(
            335,
            Avatar::hueFor('Alice'),
            'Known seed must retain its stable hue.',
        );
        self::assertSame(
            Avatar::hueFor('Alice'),
            Avatar::hueFor('ALICE'),
            'Case differences must not change the hue.',
        );
    }

    public function testHueForReturnsFallbackForEmptySeed(): void
    {
        self::assertSame(
            210,
            Avatar::hueFor(''),
            'Empty seeds must use the fallback hue.',
        );
    }
}

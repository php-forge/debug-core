<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Avatar;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Avatar} deriving deterministic avatar hues and monogram initials.
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

    public function testInitialReturnsFallbackForEmptySeed(): void
    {
        self::assertSame(
            '?',
            Avatar::initial(''),
            'Empty seeds must fall back to a question mark.',
        );
    }

    public function testInitialUppercasesTheFirstMultibyteCharacter(): void
    {
        self::assertSame(
            'A',
            Avatar::initial('alice'),
            'Leading letter must be uppercased.',
        );
        self::assertSame(
            'Á',
            Avatar::initial('álvaro'),
            'Accented letter must survive as a single character.',
        );
        self::assertSame(
            '9',
            Avatar::initial('9lives'),
            'Non-letter seeds must keep their first character.',
        );
    }
}

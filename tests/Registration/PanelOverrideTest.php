<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Registration;

use InvalidArgumentException;
use PHPForge\Debug\Registration\PanelOverride;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PanelOverride} covering the accepted keys and the per-key validation of a panel entry.
 */
#[Group('registration')]
final class PanelOverrideTest extends TestCase
{
    public function testFromArrayLeavesEveryPropertyNullForAnEmptyConfiguration(): void
    {
        $override = PanelOverride::fromArray([]);

        self::assertNull(
            $override->title,
            'An absent `title` must stay `null`.',
        );
        self::assertNull(
            $override->icon,
            'An absent `icon` must stay `null`.',
        );
        self::assertNull(
            $override->enabled,
            'An absent `enabled` must stay `null`.',
        );
        self::assertNull(
            $override->position,
            'An absent `position` must stay `null`.',
        );
    }

    public function testFromArrayMapsEveryAcceptedKeyToItsProperty(): void
    {
        $override = PanelOverride::fromArray(
            [
                'title' => 'Cache operations',
                'icon' => 'asset',
                'enabled' => false,
                'position' => 3,
            ],
        );

        self::assertSame(
            'Cache operations',
            $override->title,
            'Configured title must be carried verbatim.',
        );
        self::assertSame(
            'asset',
            $override->icon,
            'Configured icon key must be carried verbatim.',
        );
        self::assertFalse(
            $override->enabled,
            'Configured disabling flag must be carried verbatim.',
        );
        self::assertSame(
            3,
            $override->position,
            'Configured position must be carried verbatim.',
        );
    }

    public function testKeysListsEveryAcceptedOptionAlphabetically(): void
    {
        self::assertSame(
            ['enabled', 'icon', 'position', 'title'],
            PanelOverride::KEYS,
            'Accepted keys must stay alphabetical.',
        );
    }

    public function testThrowInvalidArgumentExceptionForEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'title',
        );

        PanelOverride::fromArray(['title' => '']);
    }

    public function testThrowInvalidArgumentExceptionForInvalidIconKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'icon',
        );

        PanelOverride::fromArray(['icon' => 'Bad Key.svg']);
    }

    public function testThrowInvalidArgumentExceptionForNonBoolEnabled(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'enabled',
        );

        PanelOverride::fromArray(['enabled' => 'no']);
    }

    public function testThrowInvalidArgumentExceptionForNonIntPosition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'position',
        );

        PanelOverride::fromArray(['position' => '1']);
    }

    public function testThrowInvalidArgumentExceptionForNonStringIcon(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Debug panel option 'icon' must be a string.",
        );

        PanelOverride::fromArray(['icon' => 5]);
    }

    public function testThrowInvalidArgumentExceptionForNonStringTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'title',
        );

        PanelOverride::fromArray(['title' => 5]);
    }

    public function testThrowInvalidArgumentExceptionForUnknownKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'colour',
        );

        PanelOverride::fromArray(['colour' => 'red']);
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Theme;

use PHPForge\Debug\Theme\ThemeResolver;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ThemeResolver} covering the cookie-over-query precedence and the light fallback.
 */
#[Group('theme')]
final class ThemeResolverTest extends TestCase
{
    public function testResolveDefaultsToLightWithoutAnySignal(): void
    {
        self::assertSame(
            'light',
            ThemeResolver::resolve([], []),
            'No signal must resolve to light.',
        );
    }

    public function testResolveIgnoresNonStringAndUnknownValues(): void
    {
        self::assertSame(
            'light',
            ThemeResolver::resolve(['yii-debug-toolbar-theme' => ['dark']], []),
            'Non-string cookie values must resolve to light.',
        );
        self::assertSame(
            'light',
            ThemeResolver::resolve([], ['yii_debug_theme' => 'solarized']),
            'Unknown theme names must resolve to light.',
        );
    }

    public function testResolveMatchesDarkCaseInsensitively(): void
    {
        self::assertSame(
            'dark',
            ThemeResolver::resolve(['yii-debug-toolbar-theme' => 'DARK'], []),
            'The dark keyword must match case-insensitively.',
        );
    }

    public function testResolvePrefersTheCookieOverTheQueryParameter(): void
    {
        self::assertSame(
            'light',
            ThemeResolver::resolve(['yii-debug-toolbar-theme' => 'light'], ['yii_debug_theme' => 'dark']),
            'The persisted cookie must outrank the link-time query parameter.',
        );
    }

    public function testResolveReadsTheQueryParameterWhenNoCookieIsSet(): void
    {
        self::assertSame(
            'dark',
            ThemeResolver::resolve([], ['yii_debug_theme' => 'dark']),
            'The query parameter must apply when no cookie is present.',
        );
    }
}

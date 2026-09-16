<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Theme;

use PHPForge\Debug\Tests\Provider\CssProvider;
use PHPForge\Debug\Theme\Css;
use PHPForge\Debug\Tone;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Css} covering the tone-mapped class lists and the variant-suffixed families.
 *
 * {@see CssProvider} for test case data providers.
 */
#[Group('theme')]
final class CssTest extends TestCase
{
    #[DataProviderExternal(CssProvider::class, 'badges')]
    public function testBadgeMapsEveryToneToItsChipClasses(Tone $tone, string $expected): void
    {
        self::assertSame(
            $expected,
            Css::badge($tone),
            'Chip must carry the base class before the tone class.',
        );
    }

    #[DataProviderExternal(CssProvider::class, 'callouts')]
    public function testCalloutMapsEveryToneToItsParagraphClasses(Tone $tone, string $expected): void
    {
        self::assertSame(
            $expected,
            Css::callout($tone),
            'Callout must carry the base class before the tone class.',
        );
    }

    #[DataProviderExternal(CssProvider::class, 'fileTypes')]
    public function testFileTypeMapsEveryToneToItsPillClasses(Tone $tone, string $expected): void
    {
        self::assertSame(
            $expected,
            Css::fileType($tone),
            'Pill must carry the base class before the tone class.',
        );
    }

    public function testRowPrefixesTheVariant(): void
    {
        self::assertSame(
            'yii-debug-row-warning',
            Css::row('warning'),
            'Variant must follow the row prefix.',
        );
    }

    #[DataProviderExternal(CssProvider::class, 'stats')]
    public function testStatMapsEveryToneToItsTileClasses(Tone $tone, string $expected): void
    {
        self::assertSame(
            $expected,
            Css::stat($tone),
            'Tile must carry the base class before the tone class.',
        );
    }

    public function testStatusPrefixesTheVariant(): void
    {
        self::assertSame(
            'yii-debug-status-4xx',
            Css::status('4xx'),
            'Variant must follow the status prefix.',
        );
    }

    public function testSummaryStatPrefixesTheVariant(): void
    {
        self::assertSame(
            'yii-debug-grid-summary-stat-warn',
            Css::summaryStat('warn'),
            'Variant must follow the summary-statistic prefix.',
        );
    }

    public function testVerbPrefixesTheSuffix(): void
    {
        self::assertSame(
            'yii-debug-verb-post',
            Css::verb('post'),
            'Suffix must follow the verb prefix.',
        );
    }
}

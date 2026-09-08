<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Badge;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Badge} covering the variant class and the optional extra modifier.
 */
#[Group('badge')]
#[Group('helpers')]
final class BadgeTest extends TestCase
{
    public function testRenderAppendsTheModifierAfterTheVariantClass(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-badge yii-debug-badge-success yii-debug-route-match">Matched</span>
            HTML,
            Badge::render('Matched', 'success', 'yii-debug-route-match')->render(),
            'Modifier must follow the variant class.',
        );
    }

    public function testRenderCarriesTheVariantClassWithoutAModifier(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-badge yii-debug-badge-muted">Not matched</span>
            HTML,
            Badge::render('Not matched', 'muted')->render(),
            'Chip must carry the base and variant classes only.',
        );
    }
}

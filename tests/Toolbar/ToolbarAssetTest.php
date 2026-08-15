<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Toolbar\ToolbarAsset;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function strlen;
use function substr;

/**
 * Unit tests for {@see ToolbarAsset} loading the packaged Web Component runtime.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class ToolbarAssetTest extends TestCase
{
    public function testScriptLoadsSelfContainedToolbarRuntime(): void
    {
        $script = ToolbarAsset::script();

        self::assertStringContainsString(
            'yii-debug-toolbar',
            $script,
            'Runtime must register the shared element name.',
        );
        self::assertStringContainsString(
            'customElements',
            $script,
            'Runtime must use the browser Web Components registry.',
        );
        self::assertSame(
            $script,
            ToolbarAsset::script(),
            'Repeated reads must return the cached runtime.',
        );
    }

    public function testYiiLogoLoadsSelfContainedSvgDataUri(): void
    {
        $logo = ToolbarAsset::yiiLogo();
        $prefix = 'data:image/svg+xml;base64,';

        self::assertStringStartsWith($prefix, $logo, 'Logo must use an SVG data URI.');

        $svg = base64_decode(substr($logo, strlen($prefix)), true);

        self::assertIsString($svg, 'Logo payload must contain valid base64.');
        self::assertStringContainsString('<svg', $svg, 'Decoded logo payload must contain SVG markup.');
        self::assertSame($logo, ToolbarAsset::yiiLogo(), 'Repeated reads must return the cached logo.');
    }
}

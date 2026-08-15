<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Asset;

use PHPForge\Debug\Asset\DebugAsset;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DebugAsset} exposing the shared debugger frontend.
 *
 * @since 0.1
 */
#[Group('asset')]
final class DebugAssetTest extends TestCase
{
    public function testIconRejectsInvalidOrUnknownNames(): void
    {
        self::assertNull(DebugAsset::iconPath('../request'), 'Traversal-like icon names must be rejected.');
        self::assertSame('', DebugAsset::icon('unknown-icon'), 'Unknown icons must return an empty string.');
    }

    public function testIconReturnsBundledSvg(): void
    {
        $path = DebugAsset::iconPath('request');
        $svg = DebugAsset::icon('request');

        self::assertNotNull($path, 'Bundled icon path must resolve.');
        self::assertStringContainsString('/resources/assets/svg/request.svg', $path, 'Icon path must use core assets.');
        self::assertStringContainsString('<svg', $svg, 'Bundled icon must contain SVG markup.');
        self::assertSame($svg, DebugAsset::icon('request'), 'Repeated icon reads must use the cached markup.');
    }

    public function testInlineStylesheetEmbedsPackagedFonts(): void
    {
        $stylesheet = DebugAsset::inlineStylesheet();

        self::assertStringContainsString(
            'url(data:font/woff2;base64,',
            $stylesheet,
            'Inline stylesheet must embed its packaged fonts.',
        );
        self::assertStringNotContainsString(
            'url(../fonts/',
            $stylesheet,
            'Inline stylesheet must not retain relative font URLs.',
        );
        self::assertSame(
            $stylesheet,
            DebugAsset::inlineStylesheet(),
            'Repeated inline stylesheet reads must use the cached asset.',
        );
    }

    public function testScriptAndStylesheetLoadCompiledDebuggerFrontend(): void
    {
        $script = DebugAsset::script();
        $stylesheet = DebugAsset::stylesheet();

        self::assertStringContainsString('yii-debug-theme', $script, 'Script must include debugger theme behavior.');
        self::assertStringContainsString('.yii-debug', $stylesheet, 'Stylesheet must scope the shared debugger UI.');
        self::assertSame($script, DebugAsset::script(), 'Repeated script reads must use the cached asset.');
        self::assertSame($stylesheet, DebugAsset::stylesheet(), 'Repeated stylesheet reads must use the cached asset.');
    }
}

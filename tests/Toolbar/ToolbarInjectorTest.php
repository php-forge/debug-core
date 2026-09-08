<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Toolbar\ToolbarInjector;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ToolbarInjector} placing toolbar markup in an already-rendered response.
 */
#[Group('toolbar')]
final class ToolbarInjectorTest extends TestCase
{
    public function testInjectAppendsWhenNoClosingBodyTagExists(): void
    {
        self::assertSame(
            '<p>body</p><toolbar>',
            ToolbarInjector::inject('<p>body</p>', '<toolbar>'),
            'Markup must be appended verbatim at the end.',
        );
        self::assertSame(
            '<toolbar>',
            ToolbarInjector::inject('', '<toolbar>'),
            'An empty response must yield the toolbar alone.',
        );
    }

    public function testInjectMatchesTheClosingBodyTagCaseInsensitively(): void
    {
        self::assertSame(
            '<html><BODY>page<toolbar></BODY></html>',
            ToolbarInjector::inject('<html><BODY>page</BODY></html>', '<toolbar>'),
            'An uppercase closing tag must still be matched.',
        );
    }

    public function testInjectPlacesMarkupBeforeTheLastClosingBodyTag(): void
    {
        self::assertSame(
            '<body>first</body><body>second<toolbar></body>',
            ToolbarInjector::inject('<body>first</body><body>second</body>', '<toolbar>'),
            'Only the final closing tag must receive the toolbar.',
        );
    }

    public function testInjectPreservesSurroundingMarkupWithoutReplacingIt(): void
    {
        self::assertSame(
            '<html><body>page<toolbar></body></html>',
            ToolbarInjector::inject('<html><body>page</body></html>', '<toolbar>'),
            'The closing tag and the trailing markup must survive.',
        );
    }
}

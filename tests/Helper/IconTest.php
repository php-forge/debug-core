<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Icon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for {@see Icon} covering the file-resolution and in-memory cache branches of the SVG renderer.
 */
#[Group('helpers')]
#[Group('icon')]
final class IconTest extends TestCase
{
    public function testRenderReturnsCachedMarkupWithoutReadingFile(): void
    {
        self::setCache(['cached-only' => '<svg id="cached"></svg>']);

        self::assertSame(
            '<svg id="cached"></svg>',
            Icon::render('cached-only'),
            'A cached icon must be returned even when no matching file exists.',
        );

        self::setCache([]);
    }

    public function testRenderReturnsEmptyStringWhenNameIsUnsafeOrMissing(): void
    {
        self::assertSame(
            '',
            Icon::render('../svg/yii'),
            'Traversal-like icon names must be rejected before lookup.',
        );
        self::assertSame(
            '',
            Icon::render("yii\n"),
            'Trailing newlines must invalidate an icon name.',
        );
        self::assertSame(
            '',
            Icon::render('this-icon-file-does-not-exist'),
            'Missing icon files must collapse to an empty string.',
        );
    }

    public function testRenderReturnsSanitizedMarkupForBundledIcon(): void
    {
        $markup = Icon::render('clock');

        self::assertNotSame(
            '',
            $markup,
            'Bundled icons must produce non-empty SVG markup.',
        );
        self::assertSame(
            <<<HTML
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            HTML,
            $markup,
            'Rendered markup must include the opening `<svg>` tag.',
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::setCache([]);
    }

    /**
     * Replaces the internal icon cache for isolated test scenarios.
     *
     * @param array<string, string> $cache Cached markup indexed by icon name.
     */
    private static function setCache(array $cache): void
    {
        (new ReflectionClass(Icon::class))->setStaticPropertyValue('cache', $cache);
    }
}

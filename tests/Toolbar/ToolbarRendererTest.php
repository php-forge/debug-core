<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Toolbar\ToolbarRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ToolbarRenderer} generating and injecting safe toolbar markup.
 *
 * @since 0.1
 */
#[Group('toolbar')]
final class ToolbarRendererTest extends TestCase
{
    public function testInjectAppendsWhenBodyMarkerIsMissing(): void
    {
        $result = (new ToolbarRenderer())->inject('plain text', '<toolbar></toolbar>');

        self::assertSame(
            'plain text<toolbar></toolbar>',
            $result,
            'Markup must be appended when no closing body marker exists.',
        );
    }

    public function testInjectUsesFinalCaseInsensitiveBodyMarker(): void
    {
        $html = '<html><body>text</BODY><template></body></template></html>';
        $result = (new ToolbarRenderer())->inject($html, '<toolbar></toolbar>');

        self::assertSame(
            '<html><body>text</BODY><template><toolbar></toolbar></body></template></html>',
            $result,
            'Markup must precede the final closing body marker.',
        );
    }
    public function testRenderElementEscapesAttributes(): void
    {
        $html = (new ToolbarRenderer())->renderElement(
            '/debug/toolbar?tag=a&next="value"',
            ['/health?ready=1&deep=2'],
            'top',
            40,
        );

        self::assertStringContainsString(
            'data-url="/debug/toolbar?tag=a&amp;next=&quot;value&quot;"',
            $html,
            'Data URL must be safe for an HTML attribute.',
        );
        self::assertStringContainsString(
            'data-skip-urls="[&quot;/health?ready=1&amp;deep=2&quot;]"',
            $html,
            'Skip URLs must be encoded as escaped JSON.',
        );
        self::assertStringContainsString('data-position="top"', $html, 'Position must be rendered.');
        self::assertStringContainsString('data-height="40"', $html, 'Drawer height must be rendered.');
    }

    public function testRenderIncludesRuntime(): void
    {
        $html = (new ToolbarRenderer())->render('/debug/toolbar?tag=request-1');

        self::assertStringContainsString('<yii-debug-toolbar', $html, 'Custom element must be included.');
        self::assertStringContainsString('<script>', $html, 'Inline runtime wrapper must be included.');
        self::assertStringContainsString('customElements', $html, 'Web Component runtime must be included.');
    }
}

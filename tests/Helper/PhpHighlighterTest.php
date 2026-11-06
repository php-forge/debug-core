<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\PhpHighlighter;
use PHPForge\Debug\Tests\Provider\PhpHighlighterProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PhpHighlighter} covering the opening tag removal and the escaping of captured payloads and of
 * PHP source text.
 *
 * {@see PhpHighlighterProvider} for test case data providers.
 */
#[Group('helpers')]
#[Group('php-highlighter')]
final class PhpHighlighterTest extends TestCase
{
    #[DataProviderExternal(PhpHighlighterProvider::class, 'code')]
    public function testHighlightCodeRendersSourceWithoutTheOpeningTag(string $code, string $expected): void
    {
        self::assertSame(
            $expected,
            PhpHighlighter::highlightCode($code),
            'Highlighted source markup must match exactly.',
        );
    }

    public function testHighlightEscapesMarkupCarriedByTheCapturedValue(): void
    {
        $html = PhpHighlighter::highlight("'quoted' <script>alert(1)</script>");

        self::assertStringNotContainsString(
            '<script>',
            $html,
            'A captured payload must never become executable markup.',
        );
        self::assertSame(
            <<<HTML
            <pre tabindex="0"><code style="color: #000000"><span style="color: #DD0000">'\'quoted\' &lt;script&gt;alert(1)&lt;/script&gt;'</span></code></pre>
            HTML,
            $html,
            'A string must render as the quoted expression that recreates it.',
        );
    }

    public function testHighlightKeepsScalarsAndDropsTheOpeningTag(): void
    {
        self::assertSame(
            <<<HTML
            <pre tabindex="0"><code style="color: #000000"><span style="color: #0000BB">1</span></code></pre>
            HTML,
            PhpHighlighter::highlight(1),
            'A scalar sharing the opening tag color must survive its removal.',
        );
        self::assertStringNotContainsString(
            '?php',
            PhpHighlighter::highlight(['id' => 7]),
            'The opening tag the highlighter needs must never reach the panel.',
        );
    }

    public function testHighlightRendersStructuredValuesAsIndentedExpressions(): void
    {
        self::assertSame(
            <<<HTML
            <pre tabindex="0"><code style="color: #000000"><span style="color: #007700">[
                </span><span style="color: #DD0000">'id' </span><span style="color: #007700">=&gt; </span><span style="color: #0000BB">7</span><span style="color: #007700">,
            ]</span></code></pre>
            HTML,
            PhpHighlighter::highlight(['id' => 7]),
            'An array must render as the indented expression that recreates it.',
        );
    }
}

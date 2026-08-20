<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Dump;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Dump\{DumpCardRenderer, DumpRow};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DumpCardRenderer} covering the dump card composition: index badge, type sniff, time and trace
 * meta line, and the optional trace list.
 */
#[Group('panel')]
#[Group('dump')]
final class DumpCardRendererTest extends TestCase
{
    public function testRenderMessageCellAllowsOnlyDumpHighlighterMarkup(): void
    {
        $message = <<<HTML
        <pre><code style="color: #000000"><span style="color: #0000BB">safe</span></code></pre>
        <span onclick="alert(1)">unsafe attribute</span><script>alert(1)</script>
        HTML;

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="object">safe</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            <pre><code style="color: #000000"><span style="color: #0000BB">safe</span></code></pre>
            &lt;span onclick="alert(1)"&gt;unsafe attribute&lt;/span&gt;&lt;script&gt;alert(1)&lt;/script&gt;
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: $message),
                self::traceLine(),
                0,
            ),
            'Only the exact fixed dump-highlighter markup may survive in the complete card HTML.',
        );
    }

    public function testRenderMessageCellDecodesHtml5QuoteEntitiesBeforeTypeDetection(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php &apos;hello&apos;
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php &apos;hello&apos;'),
                self::traceLine(),
                0,
            ),
            'HTML5 apostrophe entities must decode to a quoted string payload.',
        );
    }
    public function testRenderMessageCellEmitsIndexBadgeBasedOnIndex(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"
            </div>
            </div>
            HTML,
            $html,
            'Index badge must carry the dedicated class.',
        );

    }

    public function testRenderMessageCellEmitsTraceListWhenTraceHasFrames(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"><span class="yii-debug-dump-trace" title="/app/User.php:42">User.php:42</span></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"<ul class="yii-debug-trace">
            <li>
            /app/User.php:
            </li>
            </ul>
            </div>
            </div>
            HTML,
            $html,
            "Trace list '<ul>' must carry the dedicated class.",
        );

    }

    public function testRenderMessageCellEscapesMalformedAndMismatchedDumpTags(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(
                message: '<span style="color: #0000BB">safe&lt;</code></span><broken',
            ),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="object">safe</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            <span style="color: #0000BB">safe&lt;&lt;/code&gt;</span>&lt;broken
            </div>
            </div>
            HTML,
            $html,
            'Only balanced allowlisted tags may be reconstructed around malformed persisted input.',
        );

    }

    public function testRenderMessageCellFormatsMillisecondsAtTheUpperBoundary(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(time: 1_700_000_000.1239),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"><span class="yii-debug-dump-time">22:13:20.123</span></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"
            </div>
            </div>
            HTML,
            $html,
            'Millisecond conversion must use exactly one thousand units per second.',
        );
    }

    public function testRenderMessageCellKeepsPollutedAllowlistTokensEscaped(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;x&lt;span style="color: #0000BB"&gt;value&lt;/span&gt;&gt;
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '<x<span style="color: #0000BB">value</span>>'),
                self::traceLine(),
                0,
            ),
            'Allowlisted-looking tokens polluted with nested delimiters must remain inert.',
        );
    }

    public function testRenderMessageCellKeepsPollutedClosingTokensEscaped(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="object">value</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;span style="color: #0000BB"&gt;value&lt;x&lt;/span&gt;&gt;
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '<span style="color: #0000BB">value<x</span>>'),
                self::traceLine(),
                0,
            ),
            'A closing token polluted with a leading nested delimiter must not balance an allowlisted opening tag.',
        );
    }

    public function testRenderMessageCellKeepsTimeAndTraceMetadataTogether(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(time: 1_700_000_000.5, trace: [['file' => '/app/User.php', 'line' => 42]]),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"><span class="yii-debug-dump-time">22:13:20.500</span><span class="yii-debug-dump-trace" title="/app/User.php:42">User.php:42</span></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"<ul class="yii-debug-trace">
            <li>
            /app/User.php:
            </li>
            </ul>
            </div>
            </div>
            HTML,
            $html,
            'Time metadata must be retained.',
        );

    }

    public function testRenderMessageCellNormalizesUppercaseScalarIdentifiers(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="bool">bool</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php TRUE
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php TRUE'),
                self::traceLine(),
                0,
            ),
            'Boolean identifier matching must remain case-insensitive.',
        );
    }

    public function testRenderMessageCellOmitsNonPositiveTraceLineSuffix(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 0]]),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"><span class="yii-debug-dump-trace" title="/app/User.php">User.php</span></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"<ul class="yii-debug-trace">
            <li>
            /app/User.php:
            </li>
            </ul>
            </div>
            </div>
            HTML,
            $html,
            'Line zero must not be exposed as a source location suffix.',
        );

    }

    public function testRenderMessageCellOmitsTimeWhenTimeIsZero(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(time: 0.0),
                self::traceLine(),
                0,
            ),
            'Zero time must not produce a time span.',
        );
    }

    public function testRenderMessageCellOmitsTraceLabelWhenFirstFrameHasNoFile(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"<ul class="yii-debug-trace">
            <li>
            :
            </li>
            </ul>
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(trace: [['line' => 42]]),
                self::traceLine(),
                0,
            ),
            'Missing file in first frame must hide the trace label.',
        );
    }

    public function testRenderMessageCellOmitsTraceListWhenTraceIsEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(trace: []),
                self::traceLine(),
                0,
            ),
            'Empty trace must omit the trace list.',
        );
    }

    public function testRenderMessageCellOmitsTypeBadgeWhenPayloadIsEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: ''),
                self::traceLine(),
                0,
            ),
            'Empty payload must not produce a type badge.',
        );
    }

    public function testRenderMessageCellOmitsTypeBadgeWhenPayloadStartsWithSymbol(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php +42'),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php +42
            </div>
            </div>
            HTML,
            $html,
            'Unrecognized payload prefix must not produce a type badge.',
        );
    }

    public function testRenderMessageCellRendersFormattedTimeWhenTimeIsPositive(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(time: 1_700_000_000.789),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"><span class="yii-debug-dump-time">22:13:20.789</span></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"
            </div>
            </div>
            HTML,
            $html,
            'Time span must carry the dedicated class.',
        );

    }

    public function testRenderMessageCellRendersTraceLabelWithBasenameAndLine(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"><span class="yii-debug-dump-trace" title="/app/User.php:42">User.php:42</span></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"<ul class="yii-debug-trace">
            <li>
            /app/User.php:
            </li>
            </ul>
            </div>
            </div>
            HTML,
            $html,
            'Trace label span must carry the dedicated class.',
        );
    }

    public function testRenderMessageCellRequiresIdentifierAtThePayloadStart(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php +Widget
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php +Widget'),
                self::traceLine(),
                0,
            ),
            'An identifier after an unsupported leading symbol must not be classified as an object.',
        );
    }

    public function testRenderMessageCellSniffsArrayTypeFromOpeningBracket(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php [1, 2, 3]'),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="array">array</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php [1, 2, 3]
            </div>
            </div>
            HTML,
            $html,
            "Array type key must be tagged via 'data-type'.",
        );

    }

    public function testRenderMessageCellSniffsBoolFromTrueOrFalseLiteral(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="bool">bool</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php true
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php true'),
                self::traceLine(),
                0,
            ),
            "'true' literal must be tagged as 'bool'.",
        );
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="bool">bool</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php false
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php false'),
                self::traceLine(),
                0,
            ),
            "'false' literal must be tagged as 'bool'.",
        );
    }

    public function testRenderMessageCellSniffsNullFromNullLiteral(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="null">null</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php null
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php null'),
                self::traceLine(),
                0,
            ),
            "'null' literal must be tagged as 'null'.",
        );
    }

    public function testRenderMessageCellSniffsNumberFromLeadingDigit(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="number">number</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php 42
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php 42'),
                self::traceLine(),
                0,
            ),
            "'42' literal must be tagged as 'number'.",
        );
    }

    public function testRenderMessageCellSniffsObjectFromIdentifierAndKeepsTheClassName(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php yii\\base\\Component#42'),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="object">yii\base\Component</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php yii\base\Component#42
            </div>
            </div>
            HTML,
            $html,
            "Identifier payload must be tagged as 'object'.",
        );

    }

    public function testRenderMessageCellSniffsStringTypeFromQuoteCharacter(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php 'hello'
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: "&lt;?php 'hello'"),
                self::traceLine(),
                0,
            ),
            "Single-quoted payload must be tagged as 'string'.",
        );
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "hello"
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php "hello"'),
                self::traceLine(),
                0,
            ),
            "Double-quoted payload must be tagged as 'string'.",
        );
    }

    public function testRenderMessageCellSubstitutesInvalidUtf8InTheExactCardHtml(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="object">safe</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            safe�tail
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: "safe\xFFtail"),
                self::traceLine(),
                0,
            ),
            'Invalid UTF-8 must be substituted rather than blanking or truncating the card.',
        );
    }

    public function testRenderMessageCellTrimsWhitespaceBeforeTypeDetection(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="number">number</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
                42
            </div>
            </div>
            HTML,
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '    42'),
                self::traceLine(),
                0,
            ),
            'Leading whitespace without a PHP prefix must not hide a numeric payload.',
        );
    }

    public function testRenderMessageCellWrapsPayloadInTheDumpCardContainer(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: '&lt;?php "x"'),
            self::traceLine(),
            0,
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-dump">
            <header class="yii-debug-dump-card-head">
            <span class="yii-debug-dump-index" aria-hidden="true">#1</span><span class="yii-debug-dump-type" data-type="string">string</span><span class="yii-debug-dump-meta"></span>
            </header><div class="yii-debug-dump-body">
            &lt;?php "x"
            </div>
            </div>
            HTML,
            $html,
            'Outer wrapper class must be present.',
        );

    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private static function makeRow(
        string $message = '&lt;?php "hello"',
        string $category = 'application',
        float $time = 0.0,
        array $trace = [],
    ): DumpRow {
        return new DumpRow(
            message: $message,
            level: LogLevel::TRACE,
            category: $category,
            time: $time,
            trace: $trace,
        );
    }

    /**
     * Returns a deterministic trace-line closure standing in for the adapter's IDE-link renderer.
     *
     * @return \Closure(array<string, mixed>): string Trace-line renderer.
     */
    private static function traceLine(): \Closure
    {
        return static fn(array $frame): string => Coerce::string($frame['file']
            ?? null) . ':' . Coerce::string($frame['line'] ?? null);
    }
}

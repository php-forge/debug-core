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
        $message = <<<'HTML'
            <pre><code style="color: #000000"><span style="color: #0000BB">safe</span></code></pre>
            <span onclick="alert(1)">unsafe attribute</span><script>alert(1)</script>
            HTML;

        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(message: $message),
            self::traceLine(),
            0,
        );

        self::assertStringContainsString(
            '<pre><code style="color: #000000"><span style="color: #0000BB">safe</span></code></pre>',
            $html,
            'The exact fixed markup emitted by PHP dump highlighters must remain available.',
        );
        self::assertStringContainsString(
            '&lt;span onclick="alert(1)"&gt;unsafe attribute&lt;/span&gt;',
            $html,
            'Highlighter tags with arbitrary attributes must be escaped.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'Executable tags from a callback or manipulated snapshot must be escaped.',
        );
        self::assertStringNotContainsString('<script>', $html, 'Executable dump markup must never reach the UI.');
        self::assertStringNotContainsString(
            '<span onclick=',
            $html,
            'Arbitrary dump attributes must never reach the UI as markup.',
        );
    }

    public function testRenderMessageCellDecodesHtml5QuoteEntitiesBeforeTypeDetection(): void
    {
        self::assertStringContainsString(
            'data-type="string"',
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

        self::assertStringContainsString(
            'class="yii-debug-dump-index"',
            $html,
            'Index badge must carry the dedicated class.',
        );
        self::assertStringContainsString(
            '#1',
            $html,
            'Index badge must show the 1-based row number.',
        );
    }

    public function testRenderMessageCellEmitsTraceListWhenTraceHasFrames(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            self::traceLine(),
            0,
        );

        self::assertStringContainsString(
            'class="yii-debug-trace"',
            $html,
            "Trace list '<ul>' must carry the dedicated class.",
        );
        self::assertStringContainsString(
            'User.php',
            $html,
            'Trace list must render frame metadata.',
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

        self::assertStringContainsString(
            '<span style="color: #0000BB">safe&lt;&lt;/code&gt;</span>&lt;broken',
            $html,
            'Only balanced allowlisted tags may be reconstructed around malformed persisted input.',
        );
        self::assertStringNotContainsString('</code></span><broken', $html, 'Mismatched and incomplete tags must stay inert.');
    }

    public function testRenderMessageCellFormatsMillisecondsAtTheUpperBoundary(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(time: 1_700_000_000.1239),
            self::traceLine(),
            0,
        );

        self::assertStringContainsString(
            date('H:i:s', 1_700_000_000) . '.123',
            $html,
            'Millisecond conversion must use exactly one thousand units per second.',
        );
    }

    public function testRenderMessageCellKeepsTimeAndTraceMetadataTogether(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(time: 1_700_000_000.5, trace: [['file' => '/app/User.php', 'line' => 42]]),
            self::traceLine(),
            0,
        );

        self::assertStringContainsString('yii-debug-dump-time', $html, 'Time metadata must be retained.');
        self::assertStringContainsString('yii-debug-dump-trace', $html, 'Trace metadata must be retained.');
    }

    public function testRenderMessageCellNormalizesUppercaseScalarIdentifiers(): void
    {
        self::assertStringContainsString(
            'data-type="bool"',
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

        self::assertStringContainsString(
            'class="yii-debug-dump-trace" title="/app/User.php">User.php</span>',
            $html,
            'Line zero must not be exposed as a source location suffix.',
        );
        self::assertStringNotContainsString(
            'User.php:0',
            $html,
            'Line zero must remain absent from the label and tooltip.',
        );
    }

    public function testRenderMessageCellOmitsTimeWhenTimeIsZero(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-dump-time',
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
        self::assertStringNotContainsString(
            'yii-debug-dump-trace',
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
        self::assertStringNotContainsString(
            'yii-debug-trace',
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
        self::assertStringNotContainsString(
            'yii-debug-dump-type',
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

        self::assertStringNotContainsString(
            'yii-debug-dump-type',
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

        $expected = date('H:i:s', 1_700_000_000) . '.789';

        self::assertStringContainsString(
            'class="yii-debug-dump-time"',
            $html,
            'Time span must carry the dedicated class.',
        );
        self::assertStringContainsString(
            $expected,
            $html,
            "Time must format as 'H:i:s.mmm'.",
        );
    }

    public function testRenderMessageCellRendersTraceLabelWithBasenameAndLine(): void
    {
        $html = DumpCardRenderer::renderMessageCell(
            self::makeRow(trace: [['file' => '/app/User.php', 'line' => 42]]),
            self::traceLine(),
            0,
        );

        self::assertStringContainsString(
            'class="yii-debug-dump-trace"',
            $html,
            'Trace label span must carry the dedicated class.',
        );
        self::assertStringContainsString(
            'User.php:42',
            $html,
            "Trace label must show 'basename:line'.",
        );
        self::assertStringContainsString(
            'title="/app/User.php:42"',
            $html,
            'Trace label tooltip must keep the full path.',
        );
    }

    public function testRenderMessageCellRequiresIdentifierAtThePayloadStart(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-dump-type',
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

        self::assertStringContainsString(
            'data-type="array"',
            $html,
            "Array type key must be tagged via 'data-type'.",
        );
        self::assertStringContainsString(
            '>array<',
            $html,
            'Array label must be visible.',
        );
    }

    public function testRenderMessageCellSniffsBoolFromTrueOrFalseLiteral(): void
    {
        self::assertStringContainsString(
            'data-type="bool"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php true'),
                self::traceLine(),
                0,
            ),
            "'true' literal must be tagged as 'bool'.",
        );
        self::assertStringContainsString(
            'data-type="bool"',
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
        self::assertStringContainsString(
            'data-type="null"',
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
        self::assertStringContainsString(
            'data-type="number"',
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

        self::assertStringContainsString(
            'data-type="object"',
            $html,
            "Identifier payload must be tagged as 'object'.",
        );
        self::assertStringContainsString(
            'yii\\base\\Component',
            $html,
            'The full class identifier must be exposed as the badge label.',
        );
    }

    public function testRenderMessageCellSniffsStringTypeFromQuoteCharacter(): void
    {
        self::assertStringContainsString(
            'data-type="string"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: "&lt;?php 'hello'"),
                self::traceLine(),
                0,
            ),
            "Single-quoted payload must be tagged as 'string'.",
        );
        self::assertStringContainsString(
            'data-type="string"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '&lt;?php "hello"'),
                self::traceLine(),
                0,
            ),
            "Double-quoted payload must be tagged as 'string'.",
        );
    }

    public function testRenderMessageCellTrimsWhitespaceBeforeTypeDetection(): void
    {
        self::assertStringContainsString(
            'data-type="number"',
            DumpCardRenderer::renderMessageCell(
                self::makeRow(message: '   42'),
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

        self::assertStringContainsString(
            'class="yii-debug-dump"',
            $html,
            'Outer wrapper class must be present.',
        );
        self::assertStringContainsString(
            'class="yii-debug-dump-body"',
            $html,
            'Body wrapper class must be present.',
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

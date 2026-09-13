<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Helper\CellMore;
use PHPForge\Debug\Panel\Request\RequestDiagnosticValueRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Unit tests for {@see RequestDiagnosticValueRenderer} covering escaping, repeated header lines, and structured values.
 */
#[Group('panel')]
#[Group('request')]
final class RequestDiagnosticValueRendererTest extends TestCase
{
    public function testEscapeEncodesMarkupWithoutDoubleEncodingGuard(): void
    {
        self::assertSame(
            '&lt;a href=&quot;x&quot;&gt;&amp;amp;&lt;/a&gt;',
            RequestDiagnosticValueRenderer::escape('<a href="x">&amp;</a>'),
            'Entities must be encoded again, never left raw.',
        );
        self::assertSame(
            '&#039;quoted&#039;',
            RequestDiagnosticValueRenderer::escape("'quoted'"),
            'Single quotes must be encoded.',
        );
    }

    public function testEscapeSubstitutesMalformedUtf8Bytes(): void
    {
        self::assertSame(
            "caf\u{FFFD} bar",
            RequestDiagnosticValueRenderer::escape("caf\xE9 bar"),
            'Invalid bytes must become the replacement character.',
        );
    }

    public function testHeaderClampsRepeatedLinesWhenJoinedSourceExceedsThreshold(): void
    {
        $line = str_repeat('a', CellMore::THRESHOLD + 1);

        self::assertSame(
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            <div class="yii-debug-diagnostic-values">
            <span class="yii-debug-diagnostic-value-count">1 value</span><ul class="yii-debug-diagnostic-value-list">
            <li>
            {$line}
            </li>
            </ul>
            </div>
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">Show more</button>
            </div>
            HTML,
            RequestDiagnosticValueRenderer::header([$line]),
            'Clamp must measure the joined lines, not the markup.',
        );
    }

    public function testHeaderFallsBackToValueForNonStringLists(): void
    {
        self::assertSame(
            'text/html',
            RequestDiagnosticValueRenderer::header('text/html'),
            'A scalar header must render as a plain value.',
        );
        self::assertSame(
            '[]',
            RequestDiagnosticValueRenderer::header([]),
            'An empty array must dump, not list.',
        );
        self::assertSame(
            <<<HTML
            [
                &#039;a&#039; =&gt; &#039;b&#039;
            ]
            HTML,
            RequestDiagnosticValueRenderer::header(['a' => 'b']),
            'A map must dump, not list.',
        );
        self::assertSame(
            <<<HTML
            [
                0 =&gt; &#039;gzip&#039;
                1 =&gt; 1
            ]
            HTML,
            RequestDiagnosticValueRenderer::header(['gzip', 1]),
            'A list holding a non-string must dump, not list.',
        );
    }

    public function testHeaderRendersRepeatedLinesAsCountedList(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-diagnostic-values">
            <span class="yii-debug-diagnostic-value-count">2 values</span><ul class="yii-debug-diagnostic-value-list">
            <li>
            gzip
            </li><li>
            deflate
            </li>
            </ul>
            </div>
            HTML,
            RequestDiagnosticValueRenderer::header(['gzip', 'deflate']),
            'Every repeated line must survive as its own item.',
        );
        self::assertSame(
            <<<HTML
            <div class="yii-debug-diagnostic-values">
            <span class="yii-debug-diagnostic-value-count">1 value</span><ul class="yii-debug-diagnostic-value-list">
            <li>
            gzip
            </li>
            </ul>
            </div>
            HTML,
            RequestDiagnosticValueRenderer::header(['gzip']),
            'A single line must read `1 value`.',
        );
    }

    public function testHeaderRendersRepeatedLinesWithEmptyPlaceholders(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-diagnostic-values">
            <span class="yii-debug-diagnostic-value-count">2 values</span><ul class="yii-debug-diagnostic-value-list">
            <li>
            <span class="yii-debug-diagnostic-empty-value">Empty value</span>
            </li><li>
            &lt;b&gt;
            </li>
            </ul>
            </div>
            HTML,
            RequestDiagnosticValueRenderer::header(['', '<b>']),
            'Blank lines must keep their slot as a placeholder.',
        );
    }

    public function testValueClampsOnlySourcesBeyondTheThreshold(): void
    {
        $source = str_repeat('a', CellMore::THRESHOLD);

        self::assertSame(
            $source,
            RequestDiagnosticValueRenderer::value($source),
            'Threshold-length source must stay unclamped.',
        );
        self::assertSame(
            <<<HTML
            <div class="yii-debug-cell-more">
            <div class="yii-debug-cell-more-body">
            {$source}a
            </div><button class="yii-debug-cell-more-toggle" type="button" aria-expanded="false" data-yii-debug-toggle="cell-more">Show more</button>
            </div>
            HTML,
            RequestDiagnosticValueRenderer::value($source . 'a'),
            'Source beyond the threshold must be clamped.',
        );
    }

    public function testValueDumpsStructuredValuesAndFlagsEmptyStrings(): void
    {
        self::assertSame(
            '<span class="yii-debug-diagnostic-empty-value">Empty value</span>',
            RequestDiagnosticValueRenderer::value(''),
            'An empty string must render as a placeholder.',
        );
        self::assertSame(
            'plain &lt;b&gt;',
            RequestDiagnosticValueRenderer::value('plain <b>'),
            'A non-empty string must be escaped verbatim.',
        );
        self::assertSame(
            <<<HTML
            [
                &#039;a&#039; =&gt; 1
            ]
            HTML,
            RequestDiagnosticValueRenderer::value(['a' => 1]),
            'A structured value must be dumped, then escaped.',
        );
    }
}

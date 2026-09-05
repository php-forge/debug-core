<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Helper\CellMore;
use PHPForge\Debug\Panel\Request\RequestHeadersRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Unit tests for the directional Request header exchange.
 */
#[Group('panel')]
#[Group('request')]
final class RequestHeadersRendererTest extends TestCase
{
    public function testRenderBuildsOneSearchableInboundAndOutboundLedger(): void
    {
        $html = RequestHeadersRenderer::render(
            [
                'Accept' => 'text/html',
                'X-Empty' => '',
            ],
            [
                'Set-Cookie' => ['theme=dark', 'session=redacted'],
                0 => 'HTTP/1.1 200 OK',
            ],
        );

        self::assertStringContainsString(
            'yii-debug-header-exchange',
            $html,
            'Headers need one exchange shell.',
        );
        self::assertStringContainsString(
            'Header exchange',
            $html,
            'The shell must identify the HTTP subject.',
        );
        self::assertStringContainsString(
            '2</span> inbound',
            $html,
            'Inbound fields need an explicit count.',
        );
        self::assertStringContainsString(
            '2</span> outbound',
            $html,
            'Outbound fields need an explicit count.',
        );
        self::assertStringContainsString(
            'aria-label="Filter request and response headers"',
            $html,
            'One filter must search the complete exchange.',
        );
        self::assertSame(
            1,
            substr_count($html, 'data-yii-debug-filter-target="true"'),
            'Both directions must live in one filter target.',
        );
        self::assertMatchesRegularExpression(
            '~Inbound.*Request headers.*Accept.*text/html.*Outbound.*Response headers.*Set-Cookie~s',
            $html,
            'The exchange must read from inbound request to outbound response.',
        );
        self::assertStringNotContainsString(
            '&#039;text/html&#039;',
            $html,
            'Header strings must not be wrapped in PHP dump quotes.',
        );
        self::assertStringContainsString(
            'Empty value',
            $html,
            'An empty header value must remain explicit.',
        );
        self::assertStringContainsString(
            '2 values',
            $html,
            'Repeated values need an honest count.',
        );
        self::assertMatchesRegularExpression(
            '~theme=dark.*session=redacted~s',
            $html,
            'Repeated header values must remain separate and ordered.',
        );
        self::assertStringContainsString(
            'Raw response line 0',
            $html,
            'Colonless SAPI response lines must remain inspectable.',
        );
        self::assertStringNotContainsString('<th', $html, 'The exchange must not regress to a generic table header.');
    }

    public function testRenderEscapesMalformedAndLongDiagnosticsWithoutDroppingThem(): void
    {
        $long = str_repeat('a', CellMore::THRESHOLD + 1);

        $html = RequestHeadersRenderer::render(
            ["X-<script>-\xFF" => "<script>alert(1)</script>\xFF"],
            [
                'X-Long' => $long,
                'X-Legacy' => ['nested' => '<value>'],
            ],
        );

        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Header diagnostics must never become executable.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Escaped diagnostics must remain readable.',
        );
        self::assertStringContainsString(
            "\u{FFFD}",
            $html,
            'Malformed UTF-8 must degrade to a visible replacement.',
        );
        self::assertStringContainsString(
            'yii-debug-cell-more',
            $html,
            'Long header values must use the shared visual clamp.',
        );
        self::assertStringContainsString(
            'nested',
            $html,
            'Malformed legacy arrays must remain inspectable.',
        );
        self::assertStringContainsString(
            '&lt;value&gt;',
            $html,
            'Nested legacy values must remain escaped.',
        );
    }

    public function testRenderKeepsBothDirectionalEmptyStatesWithoutADeadFilter(): void
    {
        $html = RequestHeadersRenderer::render([], []);

        self::assertStringContainsString(
            'No request headers captured.',
            $html,
            'Inbound absence must be explicit.',
        );
        self::assertStringContainsString(
            'No response headers captured.',
            $html,
            'Outbound absence must be explicit.',
        );
        self::assertStringNotContainsString(
            'data-yii-debug-filter="true"',
            $html,
            'An empty exchange must not expose an inert search control.',
        );
    }
}

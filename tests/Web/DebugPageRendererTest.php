<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Web;

use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPForge\Debug\Web\DebugPageRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see DebugPageRenderer} rendering the shared self-contained debugger shell.
 *
 * @since 0.1
 */
#[Group('web')]
final class DebugPageRendererTest extends TestCase
{
    public function testHistoryRendersSharedShellAndEscapesCapturedUrl(): void
    {
        $summary = $this->summary();
        $html = (new DebugPageRenderer())->history([$summary->tag => $summary]);

        self::assertStringContainsString(
            '<body class="yii-debug">',
            $html,
            'History must use the shared debugger scope.',
        );
        self::assertStringContainsString(
            'class="yii-debug-brand-bar"',
            $html,
            'History must render the shared brand bar.',
        );
        self::assertStringContainsString(
            'url(data:font/woff2;base64,',
            $html,
            'History must embed the shared fonts.',
        );
        self::assertStringContainsString(
            'https://example.test/?value=&lt;script&gt;',
            $html,
            'Captured URL must be escaped before rendering.',
        );
        self::assertStringContainsString(
            '/debug/view?tag=request-1&amp;panel=summary',
            $html,
            'History row must link to its request summary.',
        );
    }

    public function testSnapshotRendersSelectedPanelAndFailureAsEscapedContent(): void
    {
        $snapshot = new DebugSnapshot(
            $this->summary(),
            ['collector' => ['value' => '</pre><script>alert(1)</script>']],
            [
                'collector' => PanelFailure::fromThrowable(
                    PanelFailure::CAPTURE,
                    new RuntimeException('<script>failed</script>'),
                ),
            ],
        );

        $html = (new DebugPageRenderer())->snapshot($snapshot, 'collector');

        self::assertStringContainsString('Collector', $html, 'Selected panel label must be visible.');
        self::assertStringContainsString(
            '&lt;/pre&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
            $html,
            'Stored JSON must not break out of the payload block.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;failed&lt;/script&gt;',
            $html,
            'Captured failure must be escaped before rendering.',
        );
        self::assertStringNotContainsString(
            '</pre><script>alert(1)</script>',
            $html,
            'Raw stored markup must not reach the document.',
        );
    }

    /**
     * Creates representative request metadata.
     *
     * @return RequestSummary Representative request summary.
     */
    private function summary(): RequestSummary
    {
        return new RequestSummary(
            tag: 'request-1',
            url: 'https://example.test/?value=<script>',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: 0.01,
            peakMemory: 2_097_152,
        );
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Queue;

use PHPForge\Debug\Panel\Queue\QueueGridRenderer;
use PHPForge\Debug\Tests\Support\JobRecordFixture;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see QueueGridRenderer} covering the per-cell HTML output that drives the Queue panel grid view.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueGridRendererTest extends TestCase
{
    public function testRenderAttemptCellFormatsAsHashedNumber(): void
    {
        self::assertSame(
            '#3',
            QueueGridRenderer::renderAttemptCell(JobRecordFixture::create(attempt: 3)),
            "Non-zero attempt must render as '#N'.",
        );
    }

    public function testRenderAttemptCellReturnsDashWhenAttemptIsNullOrZero(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderAttemptCell(JobRecordFixture::create(attempt: null)),
            "'null' attempt must yield '—'.",
        );

        self::assertSame(
            '—',
            QueueGridRenderer::renderAttemptCell(JobRecordFixture::create(attempt: 0)),
            "Zero attempt must yield '—'.",
        );
    }

    public function testRenderComponentCellReturnsRawComponentId(): void
    {
        self::assertSame(
            'queueRedis',
            QueueGridRenderer::renderComponentCell(JobRecordFixture::create(componentId: 'queueRedis')),
            'Component cell must echo the component id verbatim.',
        );
    }

    public function testRenderDriverCellAddsAsyncModifier(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-queue-driver yii-debug-queue-driver-is-async" title="yii\queue\sync\Queue">Redis</span>
            HTML,
            QueueGridRenderer::renderDriverCell(JobRecordFixture::create(driverName: 'Redis', isAsync: true)),
            "Async drivers must carry the 'is-async' modifier.",
        );


    }

    public function testRenderDriverCellAddsSyncModifierWhenInProcess(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span>
            HTML,
            QueueGridRenderer::renderDriverCell(JobRecordFixture::create(driverName: 'Sync', isAsync: false)),
            "Sync drivers must carry the 'is-sync' modifier.",
        );
    }

    public function testRenderDriverCellReturnsEmptyWhenDriverNameIsMissing(): void
    {
        self::assertSame(
            '',
            QueueGridRenderer::renderDriverCell(JobRecordFixture::create(driverName: '')),
            'Empty driver name must yield an empty cell.',
        );
    }

    public function testRenderDriverCellUsesFallbackTooltipWhenDriverClassIsMissing(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="Unknown driver">Custom</span>
            HTML,
            QueueGridRenderer::renderDriverCell(JobRecordFixture::create(driverName: 'Custom', driverClass: '')),
            'Missing driver class must use the explicit fallback tooltip.',
        );
    }

    public function testRenderDurationCellFormatsMilliseconds(): void
    {
        self::assertSame(
            '12.3 ms',
            QueueGridRenderer::renderDurationCell(JobRecordFixture::create(duration: 0.0123)),
            "Seconds must be formatted as 'XX.X ms'.",
        );
        self::assertSame(
            '1,000.0 ms',
            QueueGridRenderer::renderDurationCell(JobRecordFixture::create(duration: 1.0)),
            'One second must convert using exactly one thousand milliseconds.',
        );
    }

    public function testRenderDurationCellReturnsDashWhenDurationIsNull(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderDurationCell(JobRecordFixture::create(duration: null)),
            "Missing duration must yield '—'.",
        );
    }

    public function testRenderIdCellReturnsDashWhenJobIdIsEmpty(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderIdCell(JobRecordFixture::create(jobId: '')),
            "Empty job id must yield '—' to keep the column readable.",
        );
    }

    public function testRenderIdCellWrapsJobIdInTagLinkSpan(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-tag-link">69ffbbf2a6830</span>
            HTML,
            QueueGridRenderer::renderIdCell(JobRecordFixture::create(jobId: '69ffbbf2a6830')),
            'Id must reuse the History tag-link styling.',
        );

    }

    public function testRenderJobCellSplitsFqcnAndWiresHref(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-queue-grid-job">
            <a class="yii-debug-queue-grid-job-link" href="/debug/queue?seq=2" title="app\jobs\HelloJob"><strong>HelloJob</strong></a><span class="yii-debug-queue-grid-job-namespace">app\jobs\</span>
            </div>
            HTML,
            QueueGridRenderer::renderJobCell(
                JobRecordFixture::create(jobClass: 'app\\jobs\\HelloJob'),
                '/debug/queue?seq=2',
            ),
            'Short class name must render in bold inside the link.',
        );
    }

    public function testRenderStatusCellRendersFailedVariantForErrorEvents(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-queue-status yii-debug-queue-status-failed">Failed</span>
            HTML,
            QueueGridRenderer::renderStatusCell(JobRecordFixture::create(eventType: 'error')),
            "Error events must produce the 'failed' modifier.",
        );
    }

    public function testRenderStatusCellRendersQueuedVariantForPushEvents(): void
    {
        self::assertSame(
            <<<'HTML'
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span>
            HTML,
            QueueGridRenderer::renderStatusCell(JobRecordFixture::create(eventType: 'push')),
            "Push events must produce the 'queued' modifier.",
        );

    }

    public function testRenderTimeCellFormatsMicrotimeAsHmsWithMilliseconds(): void
    {
        self::assertSame(
            date('H:i:s', 1_704_112_496) . '.789',
            QueueGridRenderer::renderTimeCell(JobRecordFixture::create(time: 1_704_112_496.789)),
            "Time cell must preserve the exact 'HH:MM:SS.mmm' value.",
        );
    }

    public function testRenderTimeCellTruncatesSubMillisecondPrecision(): void
    {
        self::assertSame(
            date('H:i:s', 1_704_112_496) . '.123',
            QueueGridRenderer::renderTimeCell(JobRecordFixture::create(time: 1_704_112_496.1239)),
            'Sub-millisecond precision must truncate after three digits.',
        );
    }

    public function testRenderTtrCellAppendsSecondsSuffix(): void
    {
        self::assertSame(
            '300s',
            QueueGridRenderer::renderTtrCell(JobRecordFixture::create(ttr: 300)),
            "Non-zero TTR must render as 'Ns'.",
        );
    }

    public function testRenderTtrCellReturnsDashWhenTtrIsNullOrZero(): void
    {
        self::assertSame(
            '—',
            QueueGridRenderer::renderTtrCell(JobRecordFixture::create(ttr: null)),
            "Null TTR must yield '—'.",
        );
        self::assertSame(
            '—',
            QueueGridRenderer::renderTtrCell(JobRecordFixture::create(ttr: 0)),
            "Zero TTR must yield '—'.",
        );
    }
}

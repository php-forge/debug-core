<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Queue;

use PHPForge\Debug\Panel\Queue\{JobRecord, QueueCardRenderer, QueueSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see QueueCardRenderer} covering the typed Queue panel composition: summary header counts, status
 * pill variants, conditional tab strip, namespace splitting, avatar hue stability, and the meta footer.
 */
#[Group('panel')]
#[Group('queue')]
final class QueueCardRendererTest extends TestCase
{
    public function testRenderAsyncHintListsDistinctAsyncDrivers(): void
    {
        $summary = new QueueSummary(
            [
                self::makeRecord(driverName: 'AMQP', isAsync: true),
                self::makeRecord(driverName: 'Redis', isAsync: true),
                self::makeRecord(driverName: 'AMQP', isAsync: true),
            ],
        );
        $hint = QueueCardRenderer::renderAsyncHint($summary);

        self::assertNotNull(
            $hint,
            'At least one async driver must produce a hint banner.',
        );

        $html = $hint->render();

        self::assertSame(
            <<<HTML
            <div class="yii-debug-queue-hint">
            <strong>Async driver: AMQP, Redis.</strong> Push events show here, but jobs run in a separate worker process; see the History sidebar for <strong>CLI</strong> debug snapshots that capture the matching exec/error events.
            </div>
            HTML,
            $html,
            'AMQP must appear in the driver list.',
        );
    }

    public function testRenderAsyncHintReturnsNullWhenAllRecordsAreSync(): void
    {
        $summary = new QueueSummary(
            [self::makeRecord(driverName: 'Sync', isAsync: false)],
        );

        self::assertNull(
            QueueCardRenderer::renderAsyncHint($summary),
            'All-sync summary must omit the hint banner.',
        );
    }

    public function testRenderItemDriverPillUsesTheDriverClassOrFallbackTooltip(): void
    {
        $known = QueueCardRenderer::renderItem(
            self::makeRecord(driverName: 'Redis', driverClass: 'yii\\queue\\redis\\Queue'),
        )->render();
        $unknown = QueueCardRenderer::renderItem(
            self::makeRecord(driverName: 'Custom', driverClass: ''),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\\queue\\redis\\Queue">Redis</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            $known,
            'Known driver class must be the tooltip.',
        );
        self::assertSame(
            <<<'HTML'
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="Unknown driver">Custom</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            $unknown,
            'Missing driver class must use the fallback tooltip.',
        );
    }

    public function testRenderItemEmitsCardWithClassAndStatusPill(): void
    {
        $record = self::makeRecord(jobClass: 'app\\jobs\\HelloJob', eventType: 'push');

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem($record)->render(),
            'Outer wrapper class must be present.',
        );
    }

    public function testRenderItemMapsErrorToFailedStatusVariant(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-failed">Failed</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(eventType: 'error'))->render(),
            "Error event must use the 'failed' status variant.",
        );
    }

    public function testRenderItemMapsExecToDoneStatusVariant(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-done">Done</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(eventType: 'exec'))->render(),
            "Exec event must use the 'done' status variant.",
        );

    }

    public function testRenderItemOmitsComponentIdFromMetaStrip(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(componentId: 'queueEmail'))->render(),
            'Component meta item must be hidden the sidebar/tab strip surfaces it instead.',
        );
    }

    public function testRenderItemOmitsDriverPillWhenDriverNameIsEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(driverName: ''))->render(),
            'Empty driver name must hide the driver pill.',
        );
    }

    public function testRenderItemOmitsMetaWhenNoOptionalFieldsPresent(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord())->render(),
            'No optional fields must omit the meta strip.',
        );
    }

    public function testRenderItemOmitsPayloadBlockWhenFieldsAreEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(payloadFields: []))->render(),
            'Empty payload fields must omit the block.',
        );
    }

    public function testRenderItemRendersAvatarHueDeterministicallyFromJobClass(): void
    {
        $first = QueueCardRenderer::renderItem(self::makeRecord(jobClass: 'app\\jobs\\Hello'))->render();
        $second = QueueCardRenderer::renderItem(self::makeRecord(jobClass: 'app\\jobs\\Hello'))->render();
        $third = QueueCardRenderer::renderItem(self::makeRecord(jobClass: 'app\\jobs\\World'))->render();

        self::assertSame(
            self::extractHue($first),
            self::extractHue($second),
            'Same job class must produce the same hue.',
        );
        self::assertNotSame(
            self::extractHue($first),
            self::extractHue($third),
            'Different job classes must produce different hues.',
        );
    }

    public function testRenderItemRendersCollapsibleBlockForNestedObjects(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'inner' => [
                        '__class' => 'app\\models\\Inner',
                        'value' => 42,
                    ],
                ],
            ),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <details class="yii-debug-queue-tree-collapse">
            <summary class="yii-debug-queue-tree-summary">
            <span class="yii-debug-queue-tree-key">inner</span><span class="yii-debug-queue-tree-type">object</span><span class="yii-debug-queue-tree-class" title="app\models\Inner">app\models\Inner</span>
            </summary><div class="yii-debug-queue-tree-children">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">value</span><span class="yii-debug-queue-tree-type">int</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-number">42</span>
            </div>
            </div>
            </details>
            </div>
            </div>
            </article>
            HTML,
            $html,
            "Nested object must render inside a collapsible '<details>'.",
        );
    }

    public function testRenderItemRendersCollapsibleBlockForRegularArray(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'items' => [
                        'a',
                        'b',
                        'c',
                    ],
                ],
            ),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <details class="yii-debug-queue-tree-collapse">
            <summary class="yii-debug-queue-tree-summary">
            <span class="yii-debug-queue-tree-key">items</span><span class="yii-debug-queue-tree-type">list</span><span class="yii-debug-queue-tree-meta">(3)</span>
            </summary><div class="yii-debug-queue-tree-children">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">0</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="a">"a"</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">1</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="b">"b"</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">2</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="c">"c"</span>
            </div>
            </div>
            </details>
            </div>
            </div>
            </article>
            HTML,
            $html,
            "Nested array must render inside '<details>'.",
        );
    }

    public function testRenderItemRendersDriverPillWithName(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-async" title="yii\queue\sync\Queue">AMQP</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(driverName: 'AMQP', isAsync: true))->render(),
            "Async driver must use the 'is-async' modifier.",
        );
    }

    public function testRenderItemRendersErrorBlockOnlyWhenErrorMessagePresent(): void
    {
        $withError = QueueCardRenderer::renderItem(
            self::makeRecord(
                eventType: 'error',
                error: 'Boom: something failed',
            ),
        )->render();

        $withoutError = QueueCardRenderer::renderItem(self::makeRecord())
            ->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-failed">Failed</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-error">
            <strong>Error: </strong>Boom: something failed
            </div>
            </article>
            HTML,
            $withError,
            'Error message must be rendered.',
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            $withoutError,
            'Records without error must omit the block.',
        );
    }

    public function testRenderItemRendersFallbackInitialAndHueWhenJobClassIsEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 210' aria-hidden="true">?</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            (unknown)
            </h2>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(jobClass: ''))->render(),
            "Empty class name must fall back to hue '210'.",
        );
    }

    public function testRenderItemRendersMetaItemsWhenOptionalFieldsPresent(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                jobId: 'msg-7',
                ttr: 30,
                delay: 5,
                priority: 10,
                attempt: 2,
                duration: 0.123,
            ),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-meta">
            <span class="yii-debug-queue-meta-item" data-field="id"><span class="yii-debug-queue-meta-label">id</span><span class="yii-debug-queue-meta-value">msg-7</span></span><span class="yii-debug-queue-meta-item" data-field="ttr"><span class="yii-debug-queue-meta-label">ttr</span><span class="yii-debug-queue-meta-value">30s</span></span><span class="yii-debug-queue-meta-item" data-field="delay"><span class="yii-debug-queue-meta-label">delay</span><span class="yii-debug-queue-meta-value">5s</span></span><span class="yii-debug-queue-meta-item" data-field="priority"><span class="yii-debug-queue-meta-label">priority</span><span class="yii-debug-queue-meta-value">10</span></span><span class="yii-debug-queue-meta-item" data-field="attempt"><span class="yii-debug-queue-meta-label">attempt</span><span class="yii-debug-queue-meta-value">#2</span></span><span class="yii-debug-queue-meta-item" data-field="duration"><span class="yii-debug-queue-meta-label">duration</span><span class="yii-debug-queue-meta-value">123.0 ms</span></span>
            </div>
            </article>
            HTML,
            $html,
            "'jobId' meta item must be rendered.",
        );
    }

    public function testRenderItemRendersPayloadTreeWhenFieldsPresent(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'message' => 'first',
                    'priority' => 5,
                    'flag' => true,
                ],
            ),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">message</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="first">"first"</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">priority</span><span class="yii-debug-queue-tree-type">int</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-number">5</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">flag</span><span class="yii-debug-queue-tree-type">bool</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-bool">true</span>
            </div>
            </div>
            </div>
            </article>
            HTML,
            $html,
            'Payload block must carry the dedicated class.',
        );
    }

    public function testRenderItemRendersSyncDriverPillWithSyncModifier(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(driverName: 'Sync', isAsync: false))->render(),
            "Sync driver must use the 'is-sync' modifier.",
        );
    }

    public function testRenderItemRendersTruncatedMarkerInCollapsibleBlocks(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'items' => [
                        'a',
                        '__truncated' => true,
                    ],
                ],
            ),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <details class="yii-debug-queue-tree-collapse">
            <summary class="yii-debug-queue-tree-summary">
            <span class="yii-debug-queue-tree-key">items</span><span class="yii-debug-queue-tree-type">array</span><span class="yii-debug-queue-tree-meta">(2)</span><span class="yii-debug-queue-tree-truncated">truncated</span>
            </summary><div class="yii-debug-queue-tree-children">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">0</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="a">"a"</span>
            </div>
            </div>
            </details>
            </div>
            </div>
            </article>
            HTML,
            $html,
            "Truncated arrays must surface the 'truncated' marker chip.",
        );
    }

    public function testRenderItemRendersTypeLabelsForEachScalarKind(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'msg' => 'x',
                    'count' => 10,
                    'ratio' => 1.5,
                    'flag' => false,
                    'empty' => null,
                ],
            ),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">msg</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="x">"x"</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">count</span><span class="yii-debug-queue-tree-type">int</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-number">10</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">ratio</span><span class="yii-debug-queue-tree-type">float</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-number">1.5</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">flag</span><span class="yii-debug-queue-tree-type">bool</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-bool">false</span>
            </div><div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">empty</span><span class="yii-debug-queue-tree-type">null</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-null">null</span>
            </div>
            </div>
            </div>
            </article>
            HTML,
            $html,
            'String type label must be present.',
        );
        self::assertMatchesRegularExpression(
            '/>count<.*?>int<.*?>10</s',
            $html,
            'Integer values must remain paired with the int type label.',
        );
        self::assertMatchesRegularExpression(
            '/>ratio<.*?>float<.*?>1\.5</s',
            $html,
            'Float values must remain paired with the float type label.',
        );
    }

    public function testRenderItemRendersUnsupportedRowForNonRenderableValues(): void
    {
        $html = QueueCardRenderer::renderItem(
            self::makeRecord(
                payloadFields: [
                    'handle' => fopen('php://memory', 'rb'),
                ],
            ),
        )->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">handle</span><span class="yii-debug-queue-tree-type">unknown</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-unknown">(unsupported)</span>
            </div>
            </div>
            </div>
            </article>
            HTML,
            $html,
            "Unsupported value types must render the '(unsupported)' placeholder.",
        );
    }

    public function testRenderItemSkipsZeroDelayMetaItem(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-meta">
            <span class="yii-debug-queue-meta-item" data-field="id"><span class="yii-debug-queue-meta-label">id</span><span class="yii-debug-queue-meta-value">msg-1</span></span>
            </div>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(jobId: 'msg-1', delay: 0))->render(),
            'Zero delay must be hidden only positive delays render.',
        );
    }

    public function testRenderItemTruncatesLongStringValuesAndKeepsFullValueInTitle(): void
    {
        $longValue = str_repeat('x', 200);

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">data</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">"xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx…"</span>
            </div>
            </div>
            </div>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(payloadFields: ['data' => $longValue]))->render(),
            'Long strings must be truncated with an ellipsis.',
        );

    }

    public function testRenderItemTruncatesStringsAtUnicodeCharacterBoundaries(): void
    {
        $exactValue = str_repeat('é', 80);
        $longValue = 'É' . str_repeat('é', 80);

        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">data</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé">"éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé"</span>
            </div>
            </div>
            </div>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(payloadFields: ['data' => $exactValue]))->render(),
            'Exactly 80 characters must not truncate.',
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 58' aria-hidden="true">H</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            HelloJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header><div class="yii-debug-queue-payload">
            <div class="yii-debug-queue-tree">
            <div class="yii-debug-queue-tree-row">
            <span class="yii-debug-queue-tree-key">data</span><span class="yii-debug-queue-tree-type">string</span><span class="yii-debug-queue-tree-value yii-debug-queue-tree-value-string" title="Ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé">"Éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé…"</span>
            </div>
            </div>
            </div>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(payloadFields: ['data' => $longValue]))->render(),
            'Long Unicode strings must preserve the first 80 characters.',
        );
    }

    public function testRenderItemUsesOneUnicodeCharacterForTheAvatarInitial(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-queue-card">
            <header class="yii-debug-queue-card-head">
            <span class="yii-debug-queue-avatar" style='--queue-hue: 182' aria-hidden="true">É</span><div class="yii-debug-queue-headline">
            <h2 class="yii-debug-queue-class">
            éclairJob
            </h2><span class="yii-debug-queue-namespace">app\jobs\</span>
            </div><div class="yii-debug-queue-meta-pills">
            <span class="yii-debug-queue-status yii-debug-queue-status-queued">Queued</span><span class="yii-debug-queue-driver yii-debug-queue-driver-is-sync" title="yii\queue\sync\Queue">Sync</span><span class="yii-debug-queue-time">00:00:00</span>
            </div>
            </header>
            </article>
            HTML,
            QueueCardRenderer::renderItem(self::makeRecord(jobClass: 'app\\jobs\\éclairJob'))->render(),
            'Avatar initial must be one complete Unicode character.',
        );
    }

    /**
     * Extracts the queue avatar hue value from rendered HTML for hue-stability assertions.
     */
    private static function extractHue(string $html): int
    {
        if (preg_match('/--queue-hue: (\d+)/', $html, $m) === 1) {
            return (int) $m[1];
        }

        self::fail('No avatar hue found in rendered HTML.');
    }

    /**
     * @param array<string, mixed> $payloadFields
     */
    private static function makeRecord(
        string $eventType = 'push',
        string $componentId = 'queue',
        string $driverName = 'Sync',
        string $driverClass = 'yii\\queue\\sync\\Queue',
        bool $isAsync = false,
        string $jobClass = 'app\\jobs\\HelloJob',
        array $payloadFields = [],
        float $time = 0.0,
        string $jobId = '',
        int|null $ttr = null,
        int|null $delay = null,
        int|null $priority = null,
        int|null $attempt = null,
        float|null $duration = null,
        string $error = '',
    ): JobRecord {
        return new JobRecord(
            eventType: $eventType,
            componentId: $componentId,
            driverName: $driverName,
            driverClass: $driverClass,
            isAsync: $isAsync,
            jobClass: $jobClass,
            payloadFields: $payloadFields,
            time: $time,
            jobId: $jobId,
            ttr: $ttr,
            delay: $delay,
            priority: $priority,
            attempt: $attempt,
            duration: $duration,
            error: $error,
        );
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Event;

use Closure;
use PHPForge\Debug\Panel\Event\{EventCapture, EventInspection, EventInspectorRenderer, EventRow, EventSequence, EventSnapshot};
use PHPForge\Debug\Storage\HydrationException;
use PHPForge\Debug\Tests\Provider\EventInspectionProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * Tests immutable diagnostics, hydration, privacy, original chronology, and shared inspector rendering.
 */
#[Group('event')]
final class EventInspectionTest extends TestCase
{
    public function testCaptureRedactsAndBoundsSelectedContext(): void
    {
        $fields = EventCapture::context(
            [
                'password' => 'sensitive-value',
                'diagnostic' => 'password=hidden-value',
                'view' => str_repeat('x', 3000),
                'class' => "anonymous\0/private/declaration.php",
            ],
        );

        self::assertSame(
            '[redacted]',
            ($fields['password'] ?? self::fail('Expected password context.')),
            'Sensitive field values must be redacted before persistence.',
        );
        self::assertStringNotContainsString(
            'hidden-value',
            ($fields['diagnostic'] ?? self::fail('Expected diagnostic context.')),
            'Diagnostic assignments must be sanitized.',
        );
        self::assertLessThanOrEqual(
            2048,
            strlen(($fields['view'] ?? self::fail('Expected view context.'))),
            'Fields must be byte-bounded.',
        );
        self::assertSame(
            'anonymous',
            ($fields['class'] ?? self::fail('Expected class context.')),
            'Anonymous declaration paths must be removed.',
        );
        self::assertCount(
            16,
            EventCapture::context(array_fill_keys(range('a', 'z'), 'x')),
            'Field counts must be bounded.',
        );
    }

    public function testChronologyUsesTheOriginalCaptureAcrossFilteringAndSorting(): void
    {
        $first = self::row(10.0);
        $second = self::row(10.125);
        $last = self::row(10.5);

        $sequence = new EventSequence([$first, $second, $last]);

        self::assertSame(
            3,
            $sequence->index($last),
            'Stable identities must refer to the original sequence.',
        );
        self::assertSame(
            500.0,
            $sequence->elapsed($last),
            'Offsets must remain relative to the first captured event.',
        );
        self::assertSame(
            375.0,
            $sequence->gap($last),
            'Gaps must not be recomputed from filtered neighbors.',
        );
        self::assertNull(
            $sequence->gap($first),
            'The first observation has no previous event.',
        );
        self::assertNull(
            $sequence->gap(self::row(20.0)),
            'Unrelated rows must not manufacture predecessors.',
        );
        self::assertNull(
            $sequence->interval($last),
            'Timestamps alone must never imply a duration.',
        );
    }

    public function testDefaultsExposeDisabledCaptureStates(): void
    {
        $inspection = new EventInspection();

        self::assertSame(
            [
                'context' => [],
                'trace' => [],
                'contextStatus' => 'disabled',
                'traceStatus' => 'disabled',
                'pairId' => null,
                'phase' => '',
                'depth' => 0,
                'clock' => null,
            ],
            $inspection->jsonSerialize(),
            'Empty construction must preserve the existing diagnostic payload defaults.',
        );
        self::assertSame(
            [],
            $inspection->getContext(),
            'Context must be empty by default.',
        );
        self::assertSame(
            [],
            $inspection->getTrace(),
            'Trace frames must be empty by default.',
        );
        self::assertSame(
            'disabled',
            $inspection->getContextStatus(),
            'Context capture must be disabled by default.',
        );
        self::assertSame(
            'disabled',
            $inspection->getTraceStatus(),
            'Trace capture must be disabled by default.',
        );
        self::assertNull(
            $inspection->getPairId(),
            'Empty diagnostics must not invent a correlation.',
        );
        self::assertSame(
            '',
            $inspection->getPhase(),
            'Ordinary events must not imply a lifecycle phase.',
        );
        self::assertSame(
            0,
            $inspection->getDepth(),
            'Default nesting depth must remain zero.',
        );
        self::assertNull(
            $inspection->getClock(),
            'Empty diagnostics must not invent a clock observation.',
        );
    }

    public function testEnrichmentPreservesOriginalRowsAndSnapshots(): void
    {
        $row = self::row(100.0);

        $original = $row->jsonSerialize();

        $inspection = (new EventInspection())
            ->withContext(['View file' => '/views/site.php'], 'captured')
            ->withTrace(['/app/action.php:42'], 'captured');

        $enriched = $row->withInspection($inspection);

        $snapshot = new EventSnapshot([$row, $enriched]);

        self::assertNull(
            $row->inspection(),
            'Enrichment must not mutate the original row.',
        );
        self::assertSame(
            $original,
            $row->jsonSerialize(),
            'Unenriched row serialization must not add diagnostic fields.',
        );
        self::assertEquals(
            $snapshot,
            EventSnapshot::fromArray($snapshot->jsonSerialize(), '$'),
            'Mixed captures must round-trip.',
        );
        self::assertSame(
            $inspection,
            $enriched->inspection(),
            'The enriched copy must expose its immutable diagnostics.',
        );
        self::assertSame(
            $inspection,
            $enriched->withInspection($inspection)->inspection(),
            'Repeated enrichment must produce a fresh copy.',
        );
    }

    public function testFluentConfigurationPreservesEarlierCopiesAndSerializedFields(): void
    {
        $empty = new EventInspection();

        $defaults = $empty->jsonSerialize();

        $context = ['View file' => '/views/site.php'];
        $trace = ['/app/action.php:42'];

        $withContext = $empty->withContext($context, 'captured');
        $withTrace = $withContext->withTrace($trace, 'captured');
        $complete = $withTrace->withLifecycle(1, 'enter', 2, 10.25);

        $expected = [
            'context' => $context,
            'trace' => $trace,
            'contextStatus' => 'captured',
            'traceStatus' => 'captured',
            'pairId' => 1,
            'phase' => 'enter',
            'depth' => 2,
            'clock' => 10.25,
        ];

        self::assertNotSame(
            $empty,
            $withContext,
            'Context enrichment must return a new instance.',
        );
        self::assertNotSame(
            $withContext,
            $withTrace,
            'Trace enrichment must return a new instance.',
        );
        self::assertNotSame(
            $withTrace,
            $complete,
            'Lifecycle enrichment must return a new instance.',
        );
        self::assertSame(
            $defaults,
            $empty->jsonSerialize(),
            'Chaining must not change the empty instance.',
        );
        self::assertSame(
            [...$defaults, 'context' => $context, 'contextStatus' => 'captured'],
            $withContext->jsonSerialize(),
            'Later options must not change the context-only copy.',
        );
        self::assertSame(
            [...$expected, 'pairId' => null, 'phase' => '', 'depth' => 0, 'clock' => null],
            $withTrace->jsonSerialize(),
            'Lifecycle options must not change earlier copies.',
        );
        self::assertSame(
            $expected,
            $complete->jsonSerialize(),
            'Fluent construction must preserve the serialized keys, order, and values.',
        );

        $hydrated = EventInspection::fromArray($expected, '$.inspection');

        self::assertEquals(
            $complete,
            $hydrated,
            'Existing diagnostic payloads must hydrate to equivalent instances.',
        );
        self::assertSame(
            $context,
            $hydrated->getContext(),
            'Hydration must retain selected context.',
        );
        self::assertSame(
            $trace,
            $hydrated->getTrace(),
            'Hydration must retain source frames.',
        );
        self::assertSame(
            'captured',
            $hydrated->getContextStatus(),
            'Hydration must retain context state.',
        );
        self::assertSame(
            'captured',
            $hydrated->getTraceStatus(),
            'Hydration must retain trace state.',
        );
        self::assertSame(
            1,
            $hydrated->getPairId(),
            'Hydration must retain correlation identity.',
        );
        self::assertSame(
            'enter',
            $hydrated->getPhase(),
            'Hydration must retain lifecycle phase.',
        );
        self::assertSame(
            2,
            $hydrated->getDepth(),
            'Hydration must retain nesting depth.',
        );
        self::assertSame(
            10.25,
            $hydrated->getClock(),
            'Hydration must retain the monotonic observation.',
        );
    }

    /**
     * @param Closure(EventInspection): EventInspection $update
     * @param array<string, mixed> $changes
     */
    #[DataProviderExternal(EventInspectionProvider::class, 'inspectionUpdates')]
    public function testFluentMethodsReplaceOnlyTheirOwnGroup(Closure $update, array $changes): void
    {
        $original = (new EventInspection())
            ->withContext(['View file' => '/views/site.php'], 'captured')
            ->withTrace(['/app/action.php:42'], 'captured')
            ->withLifecycle(1, 'enter', 2, 10.25);

        $payload = $original->jsonSerialize();
        $copy = $update($original);

        self::assertNotSame(
            $original,
            $copy,
            'Replacing an optional group must return a new instance.',
        );
        self::assertSame(
            $payload,
            $original->jsonSerialize(),
            'Replacing options must preserve the original instance.',
        );
        self::assertSame(
            [...$payload, ...$changes],
            $copy->jsonSerialize(),
            'Only the selected group may change.',
        );
        self::assertEquals(
            $copy,
            EventInspection::fromArray($copy->jsonSerialize(), '$.inspection'),
            'Replacement options must round-trip.',
        );
    }

    public function testFluentOptionsCanBeReorderedAndReset(): void
    {
        $inspection = (new EventInspection())
            ->withLifecycle(1, 'enter', 2, 10.25)
            ->withTrace(['/app/action.php:42'], 'captured')
            ->withContext(['View file' => '/views/site.php'], 'captured');
        $reordered = (new EventInspection())
            ->withContext(['View file' => '/views/site.php'], 'captured')
            ->withTrace(['/app/action.php:42'], 'captured')
            ->withLifecycle(1, 'enter', 2, 10.25);

        $reset = $inspection
            ->withContext([], 'disabled')
            ->withTrace([], 'disabled')
            ->withLifecycle(null, '', 0, null);

        self::assertEquals(
            $inspection,
            $reordered,
            'Independent option groups must not depend on configuration order.',
        );
        self::assertEquals(
            new EventInspection(),
            $reset,
            'All optional diagnostics must be resettable to their defaults.',
        );
        self::assertEquals(
            $reordered,
            $inspection,
            'Resetting a copy must leave the complete inspection unchanged.',
        );
    }

    #[DataProviderExternal(EventInspectionProvider::class, 'invalidDiagnostics')]
    public function testHydrationRejectsInvalidDiagnostics(string $key, mixed $value, string $expectedMessage): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            $expectedMessage,
        );

        EventInspection::fromArray([...(new EventInspection())->jsonSerialize(), $key => $value], '$.inspection');
    }

    public function testHydrationRejectsNullInspection(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.event.inspection': expected an object.",
        );

        EventRow::fromArray([...self::row(1.0)->jsonSerialize(), 'inspection' => null], '$.event');
    }

    public function testInputAndReturnedArraysCannotMutateInspection(): void
    {
        $context = ['View file' => '/views/site.php'];
        $trace = ['/app/action.php:42'];

        $inspection = (new EventInspection())
            ->withContext($context, 'captured')
            ->withTrace($trace, 'captured');

        $payload = $inspection->jsonSerialize();

        $context['View file'] = '/changed/input.php';
        $trace[] = '/changed/input.php:1';

        $returnedContext = $inspection->getContext();
        $returnedTrace = $inspection->getTrace();

        $returnedContext['View file'] = '/changed/output.php';
        $returnedTrace[] = '/changed/output.php:2';

        self::assertNotSame(
            $context,
            $inspection->getContext(),
            'Changing context input must not mutate captured values.',
        );
        self::assertNotSame(
            $trace,
            $inspection->getTrace(),
            'Changing trace input must not mutate captured frames.',
        );
        self::assertNotSame(
            $returnedContext,
            $inspection->getContext(),
            'Context getters must not expose mutable state.',
        );
        self::assertNotSame(
            $returnedTrace,
            $inspection->getTrace(),
            'Trace getters must not expose mutable state.',
        );
        self::assertSame(
            $payload,
            $inspection->jsonSerialize(),
            'External array changes must not affect persisted diagnostics.',
        );
    }

    public function testInspectorEscapesDiagnosticsAndExplainsMissingCapabilities(): void
    {
        $row = new EventRow(10.0, '<event>', '<class>', '0', '<sender>');

        $row = $row->withInspection(
            (new EventInspection())
                ->withContext(['<key>' => '<script>'], 'captured')
                ->withTrace(['<source>'], 'captured'),
        );

        $html = EventInspectorRenderer::renderControls(
            [$row],
            static fn(string $attribute, string $value): string => '/debug?filter=' . urlencode($value),
            'name',
            'Events stopped by instance handlers may be absent',
        ) . EventInspectorRenderer::renderEventCell($row, new EventSequence([$row]))
            . EventInspectorRenderer::renderDetailRow($row, new EventSequence([$row]), 6);

        self::assertStringContainsString(
            'Open an event for diagnostics.',
            $html,
            'The shared table must explain its diagnostic disclosures.',
        );
        self::assertStringContainsString(
            'id="event-1"',
            $html,
            'Each observation must have a stable link target.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Context values must be escaped.',
        );
        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Captured values must never become markup.',
        );
        self::assertStringContainsString(
            'Events stopped by instance handlers may be absent',
            $html,
            'Yii2 capture limitations must be explicit.',
        );
        self::assertStringContainsString(
            'Listeners, their durations, and final propagation results are not captured.',
            $html,
            'Unknown listener results must not masquerade as success.',
        );
        self::assertStringContainsString(
            'Existing snapshots cannot recover missing data.',
            $html,
            'Unavailable diagnostics must not imply recoverable capture data.',
        );
    }

    public function testInspectorShowsPairedIntervalsAndFailureStates(): void
    {
        $start = self::row(10.0)
            ->withInspection(
                (new EventInspection())
                    ->withContext([], 'failed')
                    ->withTrace([], 'failed')
                    ->withLifecycle(1, 'enter', 0, 1.0),
            );
        $end = self::row(11.0)
            ->withInspection((new EventInspection())
            ->withLifecycle(1, 'leave', 0, 1.25));

        $html = EventInspectorRenderer::renderControls(
            [$start, $end],
            null,
            'class',
            'Direct calls to other dispatchers are not captured',
        ) . EventInspectorRenderer::renderEventCell($start, new EventSequence([$start, $end]))
            . EventInspectorRenderer::renderTimeCell($start, new EventSequence([$start, $end]))
            . EventInspectorRenderer::renderDetailRow($start, new EventSequence([$start, $end]), 4);

        self::assertStringContainsString(
            '250.000 ms inclusive interval',
            $html,
            'Only correlated scopes may display an interval.',
        );
        self::assertStringContainsString(
            'Context capture failed',
            $html,
            'Capture failures must be visible.',
        );
        self::assertStringContainsString(
            'Source trace capture failed',
            $html,
            'Trace failures must be visible.',
        );
        self::assertStringContainsString(
            'Direct calls to other dispatchers are not captured',
            $html,
            'PSR-14 coverage must be explicit.',
        );
    }

    public function testIntervalsRequireUniqueExplicitPairsAndMonotonicClocks(): void
    {
        $start = self::row(10.0)
            ->withInspection(
                (new EventInspection())
                    ->withLifecycle(1, 'enter', 0, 1.0),
            );
        $end = self::row(9.0)
            ->withInspection(
                (new EventInspection())
                    ->withLifecycle(1, 'leave', 0, 1.25),
            );

        self::assertSame(
            250.0,
            (new EventSequence([$start, $end]))->interval($start),
            'Wall-clock adjustments must not corrupt measured intervals.',
        );
        self::assertNull(
            (new EventSequence([$start]))->interval($start),
            'Incomplete scopes must not display invented durations.',
        );
        self::assertNull(
            (new EventSequence([$start, $end, $end]))->interval($start),
            'Ambiguous matching leaves must not be guessed.',
        );
        self::assertNull(
            (new EventSequence([$start, $start, $end]))->interval($start),
            'Duplicate entries must not be guessed.',
        );
        self::assertNull(
            (new EventSequence([$start, $end]))->interval($end),
            'A leave marker must not be interpreted as an entry.',
        );
    }

    public function testTraceNeverRetainsArgumentsOrObjects(): void
    {
        $frames = [
            [
                'function' => 'internal',
                'args' => ['hidden-value'],
            ],
            [
                'file' => '/app/event.php',
                'line' => 42,
                'args' => ['hidden-value'],
                'object' => new \stdClass(),
            ],
            ['file' => '/app/bootstrap.php'],
        ];

        self::assertSame(
            ['/app/event.php:42'],
            EventCapture::trace($frames, 1),
            'Only selected source location strings may be retained.',
        );
        self::assertSame(
            ['/app/event.php:42', '/app/bootstrap.php'],
            EventCapture::trace($frames, 16),
            'Line-less source frames remain useful.',
        );
        self::assertSame(
            [],
            EventCapture::trace($frames, 0),
            'Zero trace depth must disable source capture.',
        );
        self::assertCount(
            16,
            EventCapture::trace(array_fill(0, 30, ['file' => '/app/file.php']), 100),
            'Trace depth must be bounded.',
        );
    }

    private static function row(float $time): EventRow
    {
        return new EventRow($time, 'render', 'ViewEvent', '0', 'View');
    }
}

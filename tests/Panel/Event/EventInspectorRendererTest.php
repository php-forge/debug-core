<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Event;

use PHPForge\Debug\Panel\Event\{EventInspection, EventInspectorRenderer, EventRow, EventSequence};
use PHPForge\Debug\Tests\Provider\EventInspectorRendererProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function substr_count;

/**
 * Tests full-width diagnostics, non-duplicated context, grouped filters, and lifecycle presentation.
 */
#[Group('event')]
final class EventInspectorRendererTest extends TestCase
{
    public function testControlsDoNotRenderASecondEventList(): void
    {
        $row = new EventRow(10.0, 'event', 'Event', '0', 'Worker');

        $html = EventInspectorRenderer::renderControls([$row], null, 'name', '<coverage>');

        self::assertStringNotContainsString(
            '<table',
            $html,
            'Controls must not duplicate the adapter table.',
        );
        self::assertStringNotContainsString(
            'yii-debug-event-item',
            $html,
            'Controls must not render captured rows.',
        );
        self::assertStringContainsString(
            '&lt;coverage&gt;',
            $html,
            'Adapter capture guidance must be escaped.',
        );
        self::assertStringContainsString(
            'regardless of sorting or filtering',
            $html,
            'Controls must explain original chronology.',
        );
    }

    /**
     * @param int<1, 1000> $columns
     */
    #[DataProviderExternal(EventInspectorRendererProvider::class, 'tableColumns')]
    public function testDetailRowSpansTheTableWithoutRepeatingRowFields(int $columns): void
    {
        $row = new EventRow(10.0, 'observed-event', 'App\\ObservedEvent', '1', 'App\\EventSender');
        $sequence = new EventSequence([$row]);

        $cell = EventInspectorRenderer::renderEventCell($row, $sequence);
        $detail = EventInspectorRenderer::renderDetailRow($row, $sequence, $columns);

        self::assertStringContainsString(
            'aria-controls="event-1-detail"',
            $cell,
            'The summary must identify its diagnostic region.',
        );
        self::assertStringNotContainsString(
            'yii-debug-event-detail"',
            $cell,
            'Diagnostics must not be constrained to the event column.',
        );
        self::assertStringContainsString(
            'class="yii-debug-event-detail-row"',
            $detail,
            'Diagnostics must have a companion table row.',
        );
        self::assertStringContainsString(
            "colspan=\"{$columns}\"",
            $detail,
            'Diagnostics must span every adapter column.',
        );
        self::assertStringContainsString(
            'id="event-1-detail"',
            $detail,
            'The diagnostic region must match the summary control.',
        );
        self::assertStringContainsString(
            'role="region"',
            $detail,
            'The named diagnostic region must expose an accessible role.',
        );
        self::assertStringNotContainsString(
            'observed-event',
            $detail,
            'Diagnostics must not repeat the event name.',
        );
        self::assertStringNotContainsString(
            'App\\ObservedEvent',
            $detail,
            'Diagnostics must not repeat the event class.',
        );
        self::assertStringNotContainsString(
            'App\\EventSender',
            $detail,
            'Diagnostics must not repeat the sender.',
        );
        self::assertStringNotContainsString(
            'Observed at',
            $detail,
            'Diagnostics must not repeat the timestamp.',
        );
        self::assertStringNotContainsString(
            'Static',
            $detail,
            'Diagnostics must not repeat the static flag.',
        );
        self::assertStringContainsString(
            'href="#event-1"',
            $detail,
            'Each event must retain its permalink.',
        );
    }

    #[DataProviderExternal(EventInspectorRendererProvider::class, 'captureStates')]
    public function testRenderExplainsCaptureStates(
        string $contextStatus,
        string $traceStatus,
        string $expectedContext,
        string $expectedTrace,
    ): void {
        $row = (new EventRow(10.0, 'event', 'Event', '0', 'Worker'))
            ->withInspection(
                (new EventInspection())
                    ->withContext([], $contextStatus)
                    ->withTrace([], $traceStatus),
            );

        $sequence = new EventSequence([$row]);

        $html = EventInspectorRenderer::renderEventCell($row, $sequence)
            . EventInspectorRenderer::renderDetailRow($row, $sequence, 6);

        self::assertStringContainsString(
            $expectedContext,
            $html,
            'Context availability must reflect its captured state.',
        );
        self::assertStringContainsString(
            $expectedTrace,
            $html,
            'Trace availability must reflect its captured state.',
        );
        self::assertStringNotContainsString(
            'yii-debug-event-metadata',
            $html,
            'Empty context must not produce an empty definition list.',
        );
        self::assertStringNotContainsString(
            '<pre>',
            $html,
            'Empty traces must not produce a source block.',
        );
    }

    public function testRenderExplainsUnmatchedLifecycleMarkers(): void
    {
        $row = (new EventRow(10.0, 'App\\AfterMiddleware', 'App\\AfterMiddleware', '0', 'App\\Worker'))
            ->withInspection((new EventInspection())->withLifecycle(null, 'leave', 9, 10.0));

        $sequence = new EventSequence([$row]);

        $html = EventInspectorRenderer::renderEventCell($row, $sequence)
            . EventInspectorRenderer::renderDetailRow($row, $sequence, 6);

        self::assertStringContainsString(
            '<strong>AfterMiddleware</strong>',
            $html,
            'Lifecycle summaries must retain the event name rather than replacing it with the source.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-event-phase yii-debug-muted">'
                . str_repeat(' ', 16) . 'leave / nesting level 9</span>',
            $html,
            'Visible indentation must be capped while preserving the actual nesting depth.',
        );
        self::assertStringContainsString(
            'No matching entry captured',
            $html,
            'An unmatched leave must explain the missing correlation.',
        );
        self::assertStringNotContainsString(
            ' ms inclusive interval',
            $html,
            'An unmatched leave must not invent a duration.',
        );
    }

    #[DataProviderExternal(EventInspectorRendererProvider::class, 'groupLimits')]
    public function testRenderLimitsGroupsUsingWholeCaptureCounts(int $groupCount, int $overflow): void
    {
        $rows = [];

        for ($index = 0; $index < $groupCount; $index++) {
            $rows[] = new EventRow(10.0, "event{$index}", "App\\Event{$index}", '0', "App\\Worker{$index}");
        }

        for ($index = 0; $index < 3; $index++) {
            $rows[] = new EventRow(10.0, 'event0', 'App\\Event0', '0', 'App\\Worker0');
        }

        $filters = [];

        $html = EventInspectorRenderer::renderControls(
            $rows,
            static function (string $attribute, string $value) use (&$filters): string {
                $filters[] = [$attribute, $value];

                return "/debug?group={$attribute}";
            },
            'class',
            'Coverage',
        );

        $expectedFilters = [];

        for ($index = 0; $index < 8; $index++) {
            $expectedFilters[] = ['class', "App\\Event{$index}"];
        }

        for ($index = 0; $index < 8; $index++) {
            $expectedFilters[] = ['senderClass', "App\\Worker{$index}"];
        }

        self::assertSame(
            $expectedFilters,
            $filters,
            'Both group lists must use the eight most frequent full values and the correct filter attributes.',
        );
        self::assertSame(
            16,
            substr_count($html, '<a class="yii-debug-event-group"'),
            'Each group list must contain at most eight links.',
        );
        self::assertSame(
            2,
            substr_count($html, '<span>4</span>'),
            'Whole-capture totals must not depend on the empty visible page.',
        );
        self::assertStringNotContainsString(
            'title="App\Event8"',
            $html,
            'Overflow event groups must not create extra chips.',
        );
        self::assertStringNotContainsString(
            'title="App\Worker8"',
            $html,
            'Overflow source groups must not create extra chips.',
        );

        if ($overflow === 0) {
            self::assertStringNotContainsString(
                'more groups in the table',
                $html,
                'Exactly eight groups must not imply hidden groups.',
            );
        } else {
            self::assertSame(
                2,
                substr_count($html, "{$overflow} more groups in the table"),
                'Each group list must report its exact remaining group count.',
            );
        }
    }

    public function testRenderOmitsEmptyGroupKeys(): void
    {
        $row = new EventRow(10.0, '', '', '1', '');

        $html = EventInspectorRenderer::renderControls([$row], null, 'name', 'Coverage');

        self::assertStringNotContainsString(
            'class="yii-debug-event-group"',
            $html,
            'Missing event and source values must not become synthetic groups.',
        );
        self::assertStringContainsString(
            'By event (whole capture)',
            $html,
            'Empty group lists must retain their explanatory label.',
        );
        self::assertStringContainsString(
            'By source (whole capture)',
            $html,
            'Source grouping must remain available without named sources.',
        );
    }

    #[DataProviderExternal(EventInspectorRendererProvider::class, 'contextValues')]
    public function testRenderPreservesContextWithoutRepeatingIt(string $value): void
    {
        $row = (new EventRow(10.0, 'event', 'Event', '0', 'Worker'))
            ->withInspection(
                (new EventInspection())
                    ->withContext(['Value' => $value, 'Other' => 'Second field'], 'captured'),
            );
        $sequence = new EventSequence([$row]);

        $cell = EventInspectorRenderer::renderEventCell($row, $sequence);
        $detail = EventInspectorRenderer::renderDetailRow($row, $sequence, 6);

        self::assertStringNotContainsString(
            $value,
            $cell,
            'The event summary must not duplicate captured context.',
        );
        self::assertSame(
            1,
            substr_count($detail, $value),
            'The detail must preserve each complete context value once.',
        );
        self::assertStringContainsString(
            "<dd>\nSecond field\n</dd>",
            $detail,
            'All selected context fields must remain inspectable.',
        );
    }

    #[DataProviderExternal(EventInspectorRendererProvider::class, 'numericGroupKeys')]
    public function testRenderPreservesNumericGroupKeys(string $attribute, string $value, bool $withFilter): void
    {
        $row = new EventRow(
            10.0,
            $attribute === 'name' ? $value : 'event',
            $attribute === 'class' ? $value : 'Event',
            '0',
            $attribute === 'senderClass' ? $value : 'Worker',
        );

        $eventAttribute = $attribute === 'class' ? 'class' : 'name';
        $filters = [];

        $filterUrl = static function (string $attribute, string $value) use (&$filters): string {
            $filters[] = [$attribute, $value];

            return "/debug?{$attribute}={$value}";
        };

        $html = EventInspectorRenderer::renderControls(
            [$row, $row],
            $withFilter ? $filterUrl : null,
            $eventAttribute,
            'Coverage',
        );

        self::assertStringContainsString(
            "<span title=\"{$value}\"><strong>{$value}</strong></span><span>2</span>",
            $html,
            'Numeric group labels must retain their original string representation and complete capture count.',
        );
        self::assertSame(
            2,
            substr_count($html, 'class="yii-debug-event-group"'),
            'Repeated observations must share one event group and one source group.',
        );
        self::assertSame(
            $withFilter
                ? [
                    [$eventAttribute, $eventAttribute === 'class' ? $row->class : $row->name],
                    ['senderClass', $row->senderClass],
                ]
                : [],
            $filters,
            'Filter callbacks must receive the original group values as strings.',
        );

        if ($withFilter) {
            self::assertStringContainsString(
                "href=\"/debug?{$attribute}={$value}\"",
                $html,
                'Numeric group links must retain their filter attribute and value.',
            );
        }
    }

    public function testRenderUsesEventClassAsNameWithoutInspection(): void
    {
        $row = new EventRow(10.0, 'App\\Started', 'App\\Started', '1', '');

        $sequence = new EventSequence([$row]);

        $html = EventInspectorRenderer::renderEventCell($row, $sequence)
            . EventInspectorRenderer::renderDetailRow($row, $sequence, 6);

        self::assertStringContainsString(
            '<span class="yii-debug-event-name"><span title="App\Started"><span class="yii-debug-muted">App\</span><wbr><strong>Started</strong></span></span>',
            $html,
            'Class-based event names must use the shared two-tone label inside the event summary.',
        );
        self::assertStringContainsString(
            'Not captured (context capture is opt-in)',
            $html,
            'Rows without optional diagnostics must not imply captured context.',
        );
        self::assertStringContainsString(
            'Not captured (source trace capture is opt-in)',
            $html,
            'Rows without optional diagnostics must not imply captured traces.',
        );
        self::assertSame(
            1,
            substr_count(EventInspectorRenderer::renderControls([$row], null, 'class', 'Coverage'), 'class="yii-debug-event-group"'),
            'An empty source must not create a second group chip.',
        );
        self::assertStringNotContainsString(
            'yii-debug-event-metadata',
            $html,
            'Rows without optional diagnostics must not manufacture context fields.',
        );
    }

    #[DataProviderExternal(EventInspectorRendererProvider::class, 'observationTimes')]
    public function testTimeCellUsesOriginalObservations(int $index, string $offset, string $gap): void
    {
        $rows = [
            new EventRow(10.0, 'first', 'Event', '0', 'Worker'),
            new EventRow(10.125, 'second', 'Event', '0', 'Worker'),
            new EventRow(10.125, 'third', 'Event', '0', 'Worker'),
            new EventRow(9.875, 'fourth', 'Event', '0', 'Worker'),
        ];

        $row = $rows[$index] ?? self::fail('The provider must select an existing observation.');

        $html = EventInspectorRenderer::renderTimeCell($row, new EventSequence($rows));

        self::assertStringContainsString(
            $offset,
            $html,
            'Time offsets must use the first original observation.',
        );
        self::assertStringContainsString(
            $gap,
            $html,
            'Gaps must use the previous original observation, including zero and negative values.',
        );
        self::assertStringNotContainsString(
            'inclusive interval',
            $html,
            'Observation gaps must not imply measured execution intervals.',
        );
        self::assertStringContainsString(
            'title="Observed at ',
            $html,
            'The wall-clock timestamp must remain available.',
        );
    }
}

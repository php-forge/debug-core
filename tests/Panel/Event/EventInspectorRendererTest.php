<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Event;

use PHPForge\Debug\Panel\Event\{EventInspection, EventInspectorRenderer, EventRow};
use PHPForge\Debug\Tests\Provider\EventInspectorRendererProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function substr_count;

/**
 * Tests legacy diagnostics, bounded previews, grouped filters, and unmatched lifecycle presentation.
 */
#[Group('event')]
final class EventInspectorRendererTest extends TestCase
{
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

        $html = EventInspectorRenderer::render([$row], [$row], null, 'name', 'Coverage');

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
            'yii-debug-event-preview',
            $html,
            'Empty context must not produce a preview.',
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

        $html = EventInspectorRenderer::render([$row], [$row], null, 'class', 'Coverage');

        self::assertStringContainsString(
            '<span class="yii-debug-event-name"><strong title="App\Worker">Worker</strong></span>',
            $html,
            'Lifecycle summaries must identify the middleware while retaining its fully qualified title.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-event-source yii-debug-muted">'
                . str_repeat(' ', 16) . 'AfterMiddleware / nesting level 9</span>',
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

        $html = EventInspectorRenderer::render(
            $rows,
            [],
            static function (string $attribute, string $value) use (&$filters): string {
                $filters[] = [$attribute, $value];

                return '/debug?group=' . $attribute;
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

        $html = EventInspectorRenderer::render([$row], [], null, 'name', 'Coverage');

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

    #[DataProviderExternal(EventInspectorRendererProvider::class, 'previews')]
    public function testRenderPreservesFullContextWhenBoundingPreview(string $value, string $expectedPreview): void
    {
        $row = (new EventRow(10.0, 'event', 'Event', '0', 'Worker'))
            ->withInspection(
                (new EventInspection())
                    ->withContext(['Value' => $value, 'Other' => 'Second field'], 'captured'),
            );

        $html = EventInspectorRenderer::render([$row], [$row], null, 'name', 'Coverage');

        self::assertStringContainsString(
            '<span class="yii-debug-event-preview">' . $expectedPreview . '</span>',
            $html,
            'The preview must use only the first field, respect its byte budget, and avoid splitting UTF-8 characters.',
        );
        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-event-preview"'),
            'A second context field must not produce another preview.',
        );
        self::assertStringContainsString(
            "<dd>\n{$value}\n</dd>",
            $html,
            'The disclosure must preserve the complete captured value.',
        );
        self::assertStringContainsString(
            "<dd>\nSecond field\n</dd>",
            $html,
            'Preview selection must not discard other context fields.',
        );
    }

    public function testRenderUsesEventClassAsNameWithoutInspection(): void
    {
        $row = new EventRow(10.0, 'App\\Started', 'App\\Started', '1', '');

        $html = EventInspectorRenderer::render([$row], [$row], null, 'class', 'Coverage');

        self::assertStringContainsString(
            '<span class="yii-debug-event-name"><span title="App\Started"><span class="yii-debug-muted">App\</span><wbr><strong>Started</strong></span></span>',
            $html,
            'Class-based event names must use the shared two-tone label inside the event summary.',
        );
        self::assertStringContainsString(
            'Source not captured',
            $html,
            'Legacy static events must explain the missing source.',
        );
        self::assertStringContainsString(
        self::assertStringContainsString(
            'Not captured (context capture is opt-in)',
            $html,
            'Legacy rows must not imply captured context.',
        );
        self::assertStringContainsString(
            'Not captured (source trace capture is opt-in)',
            $html,
            'Legacy rows must not imply captured traces.',
        );
        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-event-group"'),
            'An empty source must not create a second group chip.',
        );
        self::assertStringNotContainsString(
            'yii-debug-event-preview',
            $html,
            'Legacy rows must not manufacture context previews.',
        );
    }
}

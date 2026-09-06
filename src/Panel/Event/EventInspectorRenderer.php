<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use Closure;
use PHPForge\Debug\Helper\Fqcn;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};

use function array_slice;
use function arsort;
use function count;
use function implode;
use function mb_strcut;
use function min;
use function sprintf;
use function str_repeat;
use function strlen;

/**
 * Shared execution inspector with truthful timing, native disclosures, and adapter-owned filter URLs.
 */
final class EventInspectorRenderer
{
    /**
     * @param list<EventRow> $allRows Complete capture, in observation order.
     * @param list<EventRow> $visibleRows Current filtered, sorted page.
     * @param (Closure(string, string): string)|null $filterUrl Builds a filter URL for an attribute and value.
     */
    public static function render(
        array $allRows,
        array $visibleRows,
        Closure|null $filterUrl,
        string $eventAttribute,
        string $coverage,
    ): string {
        $sequence = new EventSequence($allRows);

        $items = [];

        foreach ($visibleRows as $row) {
            $items[] = self::event($row, $sequence);
        }

        return Div::tag()
            ->class('yii-debug-event-inspector')
            ->html(
                Div::tag()
                    ->class('yii-debug-section-header')
                    ->html(
                        H2::tag()->content('Execution flow'),
                        Span::tag()
                            ->class('yii-debug-muted')
                            ->content('Open an event to inspect its context'),
                    ),
                P::tag()
                    ->class('yii-debug-muted')
                    ->content('Offsets are relative to the first captured event. Gaps are not listener durations.'),
                self::groups($allRows, $filterUrl, $eventAttribute, 'By event (whole capture)'),
                Details::tag()
                    ->class('yii-debug-event-coverage yii-debug-muted')
                    ->html(
                        Summary::tag()->content('Group by source'),
                        self::groups($allRows, $filterUrl, 'senderClass', 'By source (whole capture)'),
                    ),
                Div::tag()
                    ->class('yii-debug-event-flow')
                    ->html(...$items),
                Details::tag()
                    ->class('yii-debug-event-coverage yii-debug-muted')
                    ->html(
                        Summary::tag()->content('Capture coverage and privacy'),
                        P::tag()->content($coverage),
                        P::tag()->content(
                            'Enable captureContext and set traceLimit (1-16) on the development Events collector to capture selected context and argument-free source traces. '
                            . 'Existing snapshots cannot recover missing data. '
                            . 'Listeners, their durations, and final propagation results are not captured.',
                        ),
                        P::tag()->content(
                            'Paired lifecycle intervals include nested work and dispatch overhead. A leave marker does not prove success.',
                        ),
                    ),
            )
            ->render();
    }

    private static function event(EventRow $row, EventSequence $sequence): Details
    {
        $inspection = $row->inspection();
        $index = $sequence->index($row);
        $interval = $sequence->interval($row);
        $gap = $sequence->gap($row);
        $phase = $inspection?->getPhase() ?? '';

        $name = $row->name === $row->class
            ? Fqcn::renderLabel($row->class)
            : Strong::tag()->content($row->name)->render();

        $sourceLabel = $row->senderClass === '' ? 'Source not captured' : $row->senderClass;

        if ($phase !== '' && $row->senderClass !== '') {
            $name = Strong::tag()->title($row->senderClass)->content(Fqcn::shortName($row->senderClass))->render();
            $sourceLabel = Fqcn::shortName($row->class) . ' / nesting level ' . ($inspection?->getDepth() ?? 0);
        }

        $preview = '';

        foreach ($inspection?->getContext() ?? [] as $key => $value) {
            $preview = "{$key}: {$value}";

            $preview = strlen($preview) > 160
                ? mb_strcut($preview, 0, 157) . '...'
                : $preview;

            break;
        }

        $timing = $interval === null
            ? ($gap === null ? 'First observation' : sprintf('%+.3f ms gap', $gap))
            : sprintf('%.3f ms inclusive interval', $interval);

        $metadata = [
            Dt::tag()->content('Observed at'),
            Dd::tag()->content(EventCellRenderer::renderTimeCell($row)),
            Dt::tag()->content('Event class'),
            Dd::tag()->content($row->class),
            Dt::tag()->content('Source'),
            Dd::tag()->content($row->senderClass === '' ? 'Not captured' : $row->senderClass),
            Dt::tag()->content('Listeners / outcome'),
            Dd::tag()->content('Not captured'),
        ];

        if ($phase !== '') {
            $metadata[] = Dt::tag()->content('Lifecycle correlation');
            $metadata[] = Dd::tag()->content(
                $inspection?->getPairId() === null
                    ? 'No matching entry captured'
                    : "Scope #{$inspection->getPairId()} / {$phase}",
            );
        }

        $trace = $inspection?->getTrace() ?? [];

        $context = [];

        foreach ($inspection?->getContext() ?? [] as $label => $value) {
            $context[] = Dt::tag()->content($label);
            $context[] = Dd::tag()->content($value);
        }

        $contextStatus = match ($inspection?->getContextStatus()) {
            'captured' => 'Selected context at observation time',
            'unsupported' => 'No context extractor for this event type',
            'failed' => 'Context capture failed',
            default => 'Not captured (context capture is opt-in)',
        };
        $traceStatus = match ($inspection?->getTraceStatus()) {
            'captured' => 'Argument-free source trace',
            'failed' => 'Source trace capture failed',
            default => 'Not captured (source trace capture is opt-in)',
        };

        return Details::tag()
            ->id("event-{$index}")
            ->class('yii-debug-event-item')
            ->html(
                Summary::tag()
                    ->html(
                        Span::tag()
                            ->class('yii-debug-event-time yii-debug-cell-mono')
                            ->content(sprintf('%+.3f ms', $sequence->elapsed($row))),
                        Span::tag()
                            ->class('yii-debug-event-identity yii-debug-cell-mono')
                            ->html(
                                Span::tag()
                                    ->class('yii-debug-event-name')
                                    ->html($name),
                                Span::tag()
                                    ->class('yii-debug-event-source yii-debug-muted')
                                    ->content(
                                        str_repeat('  ', min(8, $inspection?->getDepth() ?? 0)) . $sourceLabel,
                                    ),
                                ...$preview === ''
                                    ? []
                                    : [
                                        Span::tag()
                                            ->class('yii-debug-event-preview')
                                            ->content($preview),
                                    ],
                            ),
                        Span::tag()
                            ->class('yii-debug-event-timing yii-debug-muted')
                            ->html(
                                Span::tag()
                                    ->class('yii-debug-cell-mono')
                                    ->content($phase === '' ? "#{$index}" : $phase),
                                Span::tag()->content($timing),
                            ),
                    ),
                Div::tag()
                    ->class('yii-debug-event-detail')
                    ->html(
                        A::tag()
                            ->class('yii-debug-event-permalink')
                            ->href("#event-{$index}")
                            ->content("Link to event #{$index}"),
                        Dl::tag()
                            ->class('yii-debug-event-metadata')
                            ->html(...$metadata),
                        Div::tag()
                            ->class('yii-debug-event-context')
                            ->html(
                                Strong::tag()->content('Context'),
                                P::tag()->content($contextStatus),
                                Dl::tag()
                                    ->class('yii-debug-event-metadata')
                                    ->html(...$context),
                            ),
                        Div::tag()
                            ->class('yii-debug-event-trace')
                            ->html(
                                Strong::tag()->content('Source trace'),
                                P::tag()->content($traceStatus),
                                ...$trace === [] ? [] : [Pre::tag()->content(implode("\n", $trace))],
                            ),
                    ),
            );
    }

    /**
     * @param list<EventRow> $rows
     * @param (Closure(string, string): string)|null $filterUrl
     */
    private static function groups(array $rows, Closure|null $filterUrl, string $attribute, string $label): Div
    {
        $groups = [];

        foreach ($rows as $row) {
            $key = match ($attribute) {
                'name' => $row->name,
                'class' => $row->class,
                default => $row->senderClass,
            };

            if ($key === '') {
                continue;
            }

            $groups[$key] = ($groups[$key] ?? 0) + 1;
        }

        arsort($groups);

        $items = [
            Span::tag()->content($label),
        ];

        foreach (array_slice($groups, 0, 8, true) as $name => $total) {
            $label = Fqcn::renderLabel($name) . Span::tag()->content((string) $total)->render();
            $items[] = $filterUrl === null
                ? Span::tag()
                    ->class('yii-debug-event-group')
                    ->html($label)
                : A::tag()
                    ->class('yii-debug-event-group')
                    ->href($filterUrl($attribute, $name))
                    ->html($label);
        }

        if (count($groups) > 8) {
            $items[] = Span::tag()
                ->content(sprintf('%d more groups in the table', count($groups) - 8));
        }

        return Div::tag()
            ->class('yii-debug-event-groups')
            ->html(...$items);
    }
}

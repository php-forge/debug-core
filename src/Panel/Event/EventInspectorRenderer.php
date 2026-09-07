<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use Closure;
use PHPForge\Debug\Helper\Fqcn;
use PHPForge\Debug\Panel\PanelMessage;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Table\{Td, Tr};

use function array_slice;
use function arsort;
use function count;
use function implode;
use function min;
use function sprintf;
use function str_repeat;

/**
 * Shared event-table diagnostics with truthful timing, native disclosures, and adapter-owned filter URLs.
 */
final class EventInspectorRenderer
{
    /**
     * Renders whole-capture shortcuts and capture guidance without repeating event rows.
     *
     * @param list<EventRow> $allRows Complete capture, in observation order.
     * @param (Closure(string, string): string)|null $filterUrl Builds an adapter-owned filter URL.
     */
    public static function renderControls(
        array $allRows,
        Closure|null $filterUrl,
        string $eventAttribute,
        string $coverage,
    ): string {
        return Div::tag()
            ->class('yii-debug-event-controls')
            ->html(
                P::tag()
                    ->class('yii-debug-muted')
                    ->content(EventMessage::INSPECTION_GUIDANCE),
                Details::tag()
                    ->class('yii-debug-event-coverage')
                    ->html(
                        Summary::tag()->content(PanelMessage::GROUP_FILTERS),
                        self::groups($allRows, $filterUrl, $eventAttribute, EventMessage::GROUP_BY_EVENT),
                        self::groups($allRows, $filterUrl, 'senderClass', EventMessage::GROUP_BY_SOURCE),
                    ),
                Details::tag()
                    ->class('yii-debug-event-coverage yii-debug-muted')
                    ->html(
                        Summary::tag()->content(EventMessage::CAPTURE_COVERAGE),
                        P::tag()->content($coverage),
                        P::tag()->content(EventMessage::CAPTURE_GUIDANCE),
                        P::tag()->content(EventMessage::TIMING_GUIDANCE),
                    ),
            )
            ->render();
    }

    /**
     * Renders the diagnostic disclosure content of one event without the table row wrapper.
     *
     * {@see renderDetailRow()} wraps this content in a full-width table row.
     */
    public static function renderDetailCell(EventRow $row, EventSequence $sequence): string
    {
        $inspection = $row->inspection();
        $index = $sequence->index($row);
        $phase = $inspection?->getPhase() ?? '';
        $trace = $inspection?->getTrace() ?? [];

        $context = [];

        foreach ($inspection?->getContext() ?? [] as $label => $value) {
            $context[] = Dt::tag()->content($label);
            $context[] = Dd::tag()->content($value);
        }

        $contextStatus = match ($inspection?->getContextStatus()) {
            'captured' => EventMessage::CONTEXT_CAPTURED,
            'unsupported' => EventMessage::CONTEXT_UNSUPPORTED,
            'failed' => EventMessage::CONTEXT_FAILED,
            default => EventMessage::CONTEXT_NOT_CAPTURED,
        };
        $traceStatus = match ($inspection?->getTraceStatus()) {
            'captured' => EventMessage::TRACE_CAPTURED,
            'failed' => EventMessage::TRACE_FAILED,
            default => EventMessage::TRACE_NOT_CAPTURED,
        };

        return Div::tag()
            ->id("event-{$index}-detail")
            ->class('yii-debug-event-detail')
            ->role('region')
            ->addAriaAttribute('label', "Diagnostics for event #{$index}")
            ->html(
                Div::tag()->class('yii-debug-event-context')
                    ->html(
                        Strong::tag()->content(PanelMessage::CONTEXT),
                        P::tag()->content($contextStatus),
                        ...$context === []
                            ? []
                            : [
                                Dl::tag()
                                ->class('yii-debug-event-metadata')
                                ->html(...$context),
                            ],
                    ),
                Div::tag()
                    ->class('yii-debug-event-trace')
                    ->html(
                        Strong::tag()->content(PanelMessage::SOURCE_TRACE),
                        P::tag()->content($traceStatus),
                        ...$trace === [] ? [] : [Pre::tag()->content(implode("\n", $trace))],
                    ),
                Div::tag()
                    ->class('yii-debug-event-detail-footer')
                    ->html(
                        A::tag()
                            ->class('yii-debug-event-permalink')
                            ->href("#event-{$index}")
                            ->content("Link to event #{$index}"),
                        ...$phase === '' ? [] : [
                            Span::tag()
                                ->class('yii-debug-muted')
                                ->content(
                                    $inspection?->getPairId() === null
                                    ? EventMessage::UNMATCHED_ENTRY
                                    : "Lifecycle correlation: scope #{$inspection->getPairId()}",
                                ),
                        ],
                    ),
            )
            ->render();
    }

    /**
     * Renders a full-width diagnostic row controlled by the preceding event disclosure.
     *
     * @param int<1, 1000> $columns Number of visible columns in the adapter table.
     */
    public static function renderDetailRow(EventRow $row, EventSequence $sequence, int $columns): string
    {
        return Tr::tag()
            ->class('yii-debug-event-detail-row')
            ->html(
                Td::tag()
                    ->colspan($columns)
                    ->html(self::renderDetailCell($row, $sequence)),
            )
            ->render();
    }

    /**
     * Renders a native diagnostic disclosure inside the adapter's event column.
     */
    public static function renderEventCell(EventRow $row, EventSequence $sequence): string
    {
        $inspection = $row->inspection();
        $index = $sequence->index($row);
        $phase = $inspection?->getPhase() ?? '';

        $name = $row->name === $row->class
            ? Fqcn::renderLabel($row->class)
            : Strong::tag()->content($row->name)->render();

        return Details::tag()
            ->id("event-{$index}")
            ->class('yii-debug-event-item')
            ->html(
                Summary::tag()
                    ->addAriaAttribute('controls', "event-{$index}-detail")
                    ->html(
                        Span::tag()
                            ->class('yii-debug-event-identity yii-debug-cell-mono')
                            ->html(
                                Span::tag()
                                    ->class('yii-debug-event-name')
                                    ->html($name),
                                ...$phase === '' ? [] : [
                                    Span::tag()->class('yii-debug-event-phase yii-debug-muted')->content(
                                        str_repeat('  ', min(8, $inspection?->getDepth() ?? 0))
                                        . $phase . ' / nesting level ' . ($inspection?->getDepth() ?? 0),
                                    ),
                                ],
                            ),
                    ),
            )
            ->render();
    }

    /**
     * Renders an original-capture offset and an explicitly labeled gap or inclusive interval.
     */
    public static function renderTimeCell(EventRow $row, EventSequence $sequence): string
    {
        $interval = $sequence->interval($row);
        $gap = $sequence->gap($row);

        $timing = $interval === null
            ? ($gap === null ? EventMessage::FIRST_OBSERVATION : sprintf('%+.3f ms gap', $gap))
            : sprintf('%.3f ms inclusive interval', $interval);

        return Div::tag()
            ->class('yii-debug-event-clock')
            ->html(
                Span::tag()
                    ->class('yii-debug-event-time yii-debug-cell-mono')
                    ->title('Observed at ' . EventCellRenderer::renderTimeCell($row))
                    ->content(sprintf('%+.3f ms', $sequence->elapsed($row))),
                Span::tag()
                    ->class('yii-debug-event-timing yii-debug-muted')
                    ->content($timing),
            )
            ->render();
    }

    /**
     * @param list<EventRow> $rows
     * @param (Closure(string, string): string)|null $filterUrl
     */
    private static function groups(array $rows, Closure|null $filterUrl, string $attribute, EventMessage $label): Div
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
            // PHP converts integer-string array keys to integers.
            $name = "{$name}";

            $groupLabel = Fqcn::renderLabel($name) . Span::tag()->content((string) $total)->render();

            $items[] = $filterUrl === null
                ? Span::tag()
                    ->class('yii-debug-event-group')
                    ->html($groupLabel)
                : A::tag()
                    ->class('yii-debug-event-group')
                    ->href($filterUrl($attribute, $name))
                    ->html($groupLabel);
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

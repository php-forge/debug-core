<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\PanelMessage;
use PHPForge\Debug\Tests\Panel\PanelMessageTest;

/**
 * Provides the complete presentation-text catalog for {@see PanelMessageTest}.
 */
final class PanelMessageProvider
{
    /**
     * @return iterable<string, array{PanelMessage, string}>
     */
    public static function messages(): iterable
    {
        yield 'context' => [
            PanelMessage::CONTEXT,
            'Context',
        ];
        yield 'event_capture_coverage' => [
            PanelMessage::EVENT_CAPTURE_COVERAGE,
            'Capture coverage and privacy',
        ];
        yield 'event_capture_guidance' => [
            PanelMessage::EVENT_CAPTURE_GUIDANCE,
            'Enable captureContext and set traceLimit (1-16) on the development Events collector to capture selected '
            . 'context and argument-free source traces. Existing snapshots cannot recover missing data. '
            . 'Listeners, their durations, and final propagation results are not captured.',
        ];
        yield 'event_context_captured' => [
            PanelMessage::EVENT_CONTEXT_CAPTURED,
            'Selected context at observation time',
        ];
        yield 'event_context_failed' => [
            PanelMessage::EVENT_CONTEXT_FAILED,
            'Context capture failed',
        ];
        yield 'event_context_not_captured' => [
            PanelMessage::EVENT_CONTEXT_NOT_CAPTURED,
            'Not captured (context capture is opt-in)',
        ];
        yield 'event_context_unsupported' => [
            PanelMessage::EVENT_CONTEXT_UNSUPPORTED,
            'No context extractor for this event type',
        ];
        yield 'event_first_observation' => [
            PanelMessage::EVENT_FIRST_OBSERVATION,
            'First observation',
        ];
        yield 'event_group_by_event' => [
            PanelMessage::EVENT_GROUP_BY_EVENT,
            'By event (whole capture)',
        ];
        yield 'event_group_by_source' => [
            PanelMessage::EVENT_GROUP_BY_SOURCE,
            'By source (whole capture)',
        ];
        yield 'event_inspection_guidance' => [
            PanelMessage::EVENT_INSPECTION_GUIDANCE,
            'Open an event for diagnostics. Times and observation numbers refer to the original capture, '
            . 'regardless of sorting or filtering.',
        ];
        yield 'event_timing_guidance' => [
            PanelMessage::EVENT_TIMING_GUIDANCE,
            'Offsets are relative to the first captured event. Gaps are not listener durations. '
            . 'Paired lifecycle intervals include nested work and dispatch overhead. '
            . 'A leave marker does not prove success.',
        ];
        yield 'event_trace_captured' => [
            PanelMessage::EVENT_TRACE_CAPTURED,
            'Argument-free source trace',
        ];
        yield 'event_trace_failed' => [
            PanelMessage::EVENT_TRACE_FAILED,
            'Source trace capture failed',
        ];
        yield 'event_trace_not_captured' => [
            PanelMessage::EVENT_TRACE_NOT_CAPTURED,
            'Not captured (source trace capture is opt-in)',
        ];
        yield 'event_unmatched_entry' => [
            PanelMessage::EVENT_UNMATCHED_ENTRY,
            'No matching entry captured',
        ];
        yield 'group_filters' => [
            PanelMessage::GROUP_FILTERS,
            'Group filters',
        ];
        yield 'source_trace' => [
            PanelMessage::SOURCE_TRACE,
            'Source trace',
        ];
    }
}

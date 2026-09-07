<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Event\EventMessage;
use PHPForge\Debug\Tests\Panel\Event\EventMessageTest;

/**
 * Provides the complete presentation-text catalog for {@see EventMessageTest}.
 */
final class EventMessageProvider
{
    /**
     * @return iterable<string, array{EventMessage, string}>
     */
    public static function messages(): iterable
    {
        yield 'capture_coverage' => [
            EventMessage::CAPTURE_COVERAGE,
            'Capture coverage and privacy',
        ];
        yield 'capture_guidance' => [
            EventMessage::CAPTURE_GUIDANCE,
            'Enable captureContext and set traceLimit (1-16) on the development Events collector to capture selected '
            . 'context and argument-free source traces. Existing snapshots cannot recover missing data. '
            . 'Listeners, their durations, and final propagation results are not captured.',
        ];
        yield 'context_captured' => [
            EventMessage::CONTEXT_CAPTURED,
            'Selected context at observation time',
        ];
        yield 'context_failed' => [
            EventMessage::CONTEXT_FAILED,
            'Context capture failed',
        ];
        yield 'context_not_captured' => [
            EventMessage::CONTEXT_NOT_CAPTURED,
            'Not captured (context capture is opt-in)',
        ];
        yield 'context_unsupported' => [
            EventMessage::CONTEXT_UNSUPPORTED,
            'No context extractor for this event type',
        ];
        yield 'empty_call_to_action' => [
            EventMessage::EMPTY_CALL_TO_ACTION,
            'Dispatch an application event to populate this view:',
        ];
        yield 'empty_example' => [
            EventMessage::EMPTY_EXAMPLE,
            '$dispatcher->dispatch(new MyEvent());',
        ];
        yield 'empty_explanation' => [
            EventMessage::EMPTY_EXPLANATION,
            'The Events panel records PSR-14 objects sent through the configured debug dispatcher decorator, so this '
            . 'request completed without dispatching any.',
        ];
        yield 'empty_headline' => [
            EventMessage::EMPTY_HEADLINE,
            'No events dispatched in this request',
        ];
        yield 'first_observation' => [
            EventMessage::FIRST_OBSERVATION,
            'First observation',
        ];
        yield 'group_by_event' => [
            EventMessage::GROUP_BY_EVENT,
            'By event (whole capture)',
        ];
        yield 'group_by_source' => [
            EventMessage::GROUP_BY_SOURCE,
            'By source (whole capture)',
        ];
        yield 'inspection_guidance' => [
            EventMessage::INSPECTION_GUIDANCE,
            'Open an event for diagnostics. Times and observation numbers refer to the original capture, '
            . 'regardless of sorting or filtering.',
        ];
        yield 'no_match_explanation' => [
            EventMessage::NO_MATCH_EXPLANATION,
            'Adjust or clear the filters to show the dispatched events.',
        ];
        yield 'no_match_headline' => [
            EventMessage::NO_MATCH_HEADLINE,
            'No events match the active filters',
        ];
        yield 'timing_guidance' => [
            EventMessage::TIMING_GUIDANCE,
            'Offsets are relative to the first captured event. Gaps are not listener durations. '
            . 'Paired lifecycle intervals include nested work and dispatch overhead. '
            . 'A leave marker does not prove success.',
        ];
        yield 'trace_captured' => [
            EventMessage::TRACE_CAPTURED,
            'Argument-free source trace',
        ];
        yield 'trace_failed' => [
            EventMessage::TRACE_FAILED,
            'Source trace capture failed',
        ];
        yield 'trace_not_captured' => [
            EventMessage::TRACE_NOT_CAPTURED,
            'Not captured (source trace capture is opt-in)',
        ];
        yield 'unmatched_entry' => [
            EventMessage::UNMATCHED_ENTRY,
            'No matching entry captured',
        ];
    }
}

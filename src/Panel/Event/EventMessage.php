<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

/**
 * Text shown by the Events panel.
 */
enum EventMessage: string
{
    /**
     * Summary of the capture coverage disclosure above the events table.
     */
    case CAPTURE_COVERAGE = 'Capture coverage and privacy';

    /**
     * Guidance inside the capture coverage disclosure describing the opt-in collector settings.
     */
    case CAPTURE_GUIDANCE = 'Enable captureContext and set traceLimit (1-16) on the development Events '
        . 'collector to capture selected context and argument-free source traces. Existing snapshots cannot recover '
        . 'missing data. Listeners, their durations, and final propagation results are not captured.';

    /**
     * Status of the context section in the event detail when context was captured.
     */
    case CONTEXT_CAPTURED = 'Selected context at observation time';

    /**
     * Status of the context section in the event detail when the extractor raised an error.
     */
    case CONTEXT_FAILED = 'Context capture failed';

    /**
     * Status of the context section in the event detail when context capture is disabled.
     */
    case CONTEXT_NOT_CAPTURED = 'Not captured (context capture is opt-in)';

    /**
     * Status of the context section in the event detail when no extractor handles the event type.
     */
    case CONTEXT_UNSUPPORTED = 'No context extractor for this event type';

    /**
     * Call to action of the empty state, introducing the dispatch example.
     */
    case EMPTY_CALL_TO_ACTION = 'Dispatch an application event to populate this view:';

    /**
     * Code sample of the empty state, shown below the call to action.
     */
    case EMPTY_EXAMPLE = '$dispatcher->dispatch(new MyEvent());';

    /**
     * Explanation of the empty state, describing what the panel records.
     */
    case EMPTY_EXPLANATION = 'The Events panel records PSR-14 objects sent through the configured debug '
        . 'dispatcher decorator, so this request completed without dispatching any.';

    /**
     * Headline of the empty state when the request dispatched no event.
     */
    case EMPTY_HEADLINE = 'No events dispatched in this request';

    /**
     * Timing label of the time cell for the first observation of the capture.
     */
    case FIRST_OBSERVATION = 'First observation';

    /**
     * Label of the whole-capture event shortcuts inside the group filter disclosure.
     */
    case GROUP_BY_EVENT = 'By event (whole capture)';

    /**
     * Label of the whole-capture source shortcuts inside the group filter disclosure.
     */
    case GROUP_BY_SOURCE = 'By source (whole capture)';

    /**
     * Muted guidance above the events table, explaining how to read the diagnostics.
     */
    case INSPECTION_GUIDANCE = 'Open an event for diagnostics. Times and observation numbers refer to the '
        . 'original capture, regardless of sorting or filtering.';

    /**
     * Explanation of the no-match state, offering the filter reset.
     */
    case NO_MATCH_EXPLANATION = 'Adjust or clear the filters to show the dispatched events.';

    /**
     * Headline of the no-match state when the active filters exclude every captured event.
     */
    case NO_MATCH_HEADLINE = 'No events match the active filters';

    /**
     * Guidance inside the capture coverage disclosure about offsets, gaps, and lifecycle intervals.
     */
    case TIMING_GUIDANCE = 'Offsets are relative to the first captured event. Gaps are not listener durations. '
        . 'Paired lifecycle intervals include nested work and dispatch overhead. A leave marker does not prove success.';

    /**
     * Status of the source trace section in the event detail when a trace was captured.
     */
    case TRACE_CAPTURED = 'Argument-free source trace';

    /**
     * Status of the source trace section in the event detail when the capture raised an error.
     */
    case TRACE_FAILED = 'Source trace capture failed';

    /**
     * Status of the source trace section in the event detail when trace capture is disabled.
     */
    case TRACE_NOT_CAPTURED = 'Not captured (source trace capture is opt-in)';

    /**
     * Footer note of the event detail when a lifecycle marker has no correlated counterpart.
     */
    case UNMATCHED_ENTRY = 'No matching entry captured';
}

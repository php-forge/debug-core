<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

/**
 * Text shared by debugger panels, with panel-specific cases prefixed by their panel name.
 */
enum PanelMessage: string
{
    case CONTEXT = 'Context';
    case EVENT_CAPTURE_COVERAGE = 'Capture coverage and privacy';
    case EVENT_CAPTURE_GUIDANCE = 'Enable captureContext and set traceLimit (1-16) on the development Events '
        . 'collector to capture selected context and argument-free source traces. Existing snapshots cannot recover '
        . 'missing data. Listeners, their durations, and final propagation results are not captured.';
    case EVENT_CONTEXT_CAPTURED = 'Selected context at observation time';
    case EVENT_CONTEXT_FAILED = 'Context capture failed';
    case EVENT_CONTEXT_NOT_CAPTURED = 'Not captured (context capture is opt-in)';
    case EVENT_CONTEXT_UNSUPPORTED = 'No context extractor for this event type';
    case EVENT_FIRST_OBSERVATION = 'First observation';
    case EVENT_GROUP_BY_EVENT = 'By event (whole capture)';
    case EVENT_GROUP_BY_SOURCE = 'By source (whole capture)';
    case EVENT_INSPECTION_GUIDANCE = 'Open an event for diagnostics. Times and observation numbers refer to the '
        . 'original capture, regardless of sorting or filtering.';
    case EVENT_TIMING_GUIDANCE = 'Offsets are relative to the first captured event. Gaps are not listener durations. '
        . 'Paired lifecycle intervals include nested work and dispatch overhead. A leave marker does not prove success.';
    case EVENT_TRACE_CAPTURED = 'Argument-free source trace';
    case EVENT_TRACE_FAILED = 'Source trace capture failed';
    case EVENT_TRACE_NOT_CAPTURED = 'Not captured (source trace capture is opt-in)';
    case EVENT_UNMATCHED_ENTRY = 'No matching entry captured';
    case GROUP_FILTERS = 'Group filters';
    case SOURCE_TRACE = 'Source trace';
}

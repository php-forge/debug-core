<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Profile;

/**
 * Text shown by the Profiling panel.
 */
enum ProfileMessage: string
{
    /**
     * Call to action of the empty state, introducing the profile marker example.
     */
    case EMPTY_CALL_TO_ACTION = 'To populate this view, wrap interesting sections of code with profile '
        . 'markers:';

    /**
     * Closing note of the empty state, pointing at the automatically profiled database queries.
     */
    case EMPTY_DB_NOTE = 'Database queries are profiled automatically when the DB collector is configured.';

    /**
     * Code sample of the empty state, shown below the call to action.
     */
    case EMPTY_EXAMPLE = "\$profiler->begin('my-token');\n// …work…\n\$profiler->end('my-token');";

    /**
     * Headline of the empty state when the request captured no profiling span.
     */
    case EMPTY_HEADLINE = 'No profiling data captured';

    /**
     * Explanation of the no-match state, offering the filter reset.
     */
    case NO_MATCH_EXPLANATION = 'Adjust or clear the filters to show the captured spans.';

    /**
     * Headline of the no-match state when the active filters exclude every captured span.
     */
    case NO_MATCH_HEADLINE = 'No spans match the active filters';

    /**
     * Closing note of the timeline fallback, pointing at the profiling details below the chart.
     */
    case TIMELINE_UNAVAILABLE_DETAILS = 'The profiling details remain available below.';

    /**
     * Explanation of the timeline fallback, naming the capture values the chart requires.
     */
    case TIMELINE_UNAVAILABLE_EXPLANATION = 'This capture does not contain the valid request start, '
        . 'duration, and peak-memory values required to position the chart.';

    /**
     * Headline of the timeline fallback when the capture cannot position the chart.
     */
    case TIMELINE_UNAVAILABLE_HEADLINE = 'Timeline unavailable';
}

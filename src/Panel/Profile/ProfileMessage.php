<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Profile;

/**
 * Presentation text of the Profiling panel, shared by every adapter that renders it.
 */
enum ProfileMessage: string
{
    /**
     * Label of the filter form submit button.
     */
    case APPLY = 'Apply';

    /**
     * Label of the category filter.
     */
    case CATEGORY = 'Category';

    /**
     * Placeholder of the category filter, showing the category of an instrumented database query.
     */
    case CATEGORY_PLACEHOLDER = 'yii\db\Command::query';

    /**
     * Heading above the captured span table.
     */
    case DETAILS = 'Details';

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
     * Closing sentence of the empty state, following the profile markers it names.
     */
    case EMPTY_NO_SPANS = ' spans, so the Timeline and details are empty.';

    /**
     * Opening sentence of the empty state, preceding the profile markers it names.
     */
    case EMPTY_PRODUCED = 'This request did not produce any ';

    /**
     * Separator between the two profile markers named in the empty state.
     */
    case EMPTY_SEPARATOR = ' / ';

    /**
     * Accessible label of the filter form.
     */
    case FILTERS = 'Profiling filters';

    /**
     * Label of the info filter.
     */
    case INFO = 'Info';

    /**
     * Placeholder of the info filter, showing the leading verb of an instrumented statement.
     */
    case INFO_PLACEHOLDER = 'SELECT';

    /**
     * Label of the minimum-duration filter.
     */
    case MIN_DURATION = 'Min duration (ms)';

    /**
     * Explanation of the no-match state, offering the filter reset.
     */
    case NO_MATCH_EXPLANATION = 'Adjust or clear the filters to show the captured spans.';

    /**
     * Headline of the no-match state when the active filters exclude every captured span.
     */
    case NO_MATCH_HEADLINE = 'No spans match the active filters';

    /**
     * Suffix appended to the peak memory in the summary header.
     */
    case PEAK_SUFFIX = ' peak';

    /**
     * Suffix appended to a single captured span in the summary header.
     */
    case SPAN_SUFFIX = ' span';

    /**
     * Suffix appended to the captured span count in the summary header.
     */
    case SPANS_SUFFIX = ' spans';

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

    /**
     * Title of the toolbar chip reporting the peak memory.
     */
    case TOOLBAR_MEMORY = 'Peak memory';

    /**
     * Title of the toolbar chip reporting the total processing time.
     */
    case TOOLBAR_TIME = 'Total processing time';

    /**
     * Suffix appended to the total processing time in the summary header.
     */
    case TOTAL_SUFFIX = ' total';
}

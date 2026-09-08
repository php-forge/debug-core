<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

/**
 * Text shared by the Database panel and its query controls.
 */
enum DbMessage: string
{
    /**
     * Header of the column counting how many times the exact same statement ran.
     */
    case DUPLICATE = 'Dup';

    /**
     * Suffix appended to the duplicated-row count in the summary header.
     */
    case DUPLICATE_SUFFIX = ' duplicated';

    /**
     * Header of the execution-time column.
     */
    case DURATION = 'Duration';

    /**
     * Explanation of the empty state, naming the instrumented connection that feeds the panel.
     */
    case EMPTY_EXPLANATION = 'This request completed without executing SQL through an instrumented DB connection.';

    /**
     * Headline of the empty state when the request executed no query.
     */
    case EMPTY_HEADLINE = 'No database queries in this request';

    /**
     * Label of the button that expands every EXPLAIN plan on the page.
     */
    case EXPLAIN_ALL = 'Explain all';

    /**
     * Error rendered instead of a plan when the statement or the connection cannot be explained.
     */
    case EXPLAIN_UNAVAILABLE = 'EXPLAIN is not available for this query or connection.';

    /**
     * Explanation of the no-match state, offering the filter reset.
     */
    case NO_MATCH_EXPLANATION = 'Remove an active filter or clear all filters to see the captured queries.';

    /**
     * Headline of the no-match state when the active filters exclude every captured query.
     */
    case NO_MATCH_HEADLINE = 'No database queries match these filters';

    /**
     * Scope appended to the N+1 summary heading, since detection covers the current page only.
     */
    case PAGE_SCOPE = 'on this page';

    /**
     * Header of the SQL statement column, also used as its filter label.
     */
    case QUERY = 'Query';

    /**
     * Suffix appended to the query count in the summary header.
     */
    case QUERY_COUNT_SUFFIX = ' queries';

    /**
     * Header of the column showing the driver-reported row count.
     */
    case ROWS = 'Rows';

    /**
     * Header of the capture-time column, which sorts by sequence.
     */
    case TIME = 'Time';

    /**
     * `sprintf()` template of the toolbar warning when several call sites exceed the caller threshold.
     */
    case TOOLBAR_CALLERS_MANY = '%d callers are making too many calls.';

    /**
     * `sprintf()` template of the toolbar warning when one call site exceeds the caller threshold.
     */
    case TOOLBAR_CALLERS_ONE = '%d caller is making too many calls.';

    /**
     * `sprintf()` template of the toolbar warning when the query count exceeds the critical threshold.
     */
    case TOOLBAR_CRITICAL = 'Too many queries, allowed count is %d.';

    /**
     * `sprintf()` template of the toolbar tooltip when no threshold is exceeded.
     */
    case TOOLBAR_EXECUTED = 'Executed %d database queries.';

    /**
     * Suffix appended to the total duration in the summary header.
     */
    case TOTAL_SUFFIX = ' ms total';

    /**
     * Title of the toolbar chip showing the total query time.
     */
    case TOTAL_TIME = 'Total query time';

    /**
     * Label of the disclosure that reveals the captured backtrace of a query.
     */
    case TRACE = 'Trace';

    /**
     * Header of the statement-verb column, also used as its filter label.
     */
    case TYPE = 'Type';
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

/**
 * Presentation text of the Logs panel, shared by every adapter that renders it.
 */
enum LogMessage: string
{
    /**
     * Header of the log category column, also used as its filter label.
     */
    case CATEGORY = 'Category';

    /**
     * `sprintf()` template of the accessible label of a severity chip, naming the count, the plural noun, and the
     * severity it filters by.
     */
    case CHIP_ARIA = '%d %s; filter log messages by %s level';

    /**
     * `sprintf()` template of the tooltip of a severity chip, naming the severity it filters by.
     */
    case CHIP_TITLE = 'Show only %s log messages';

    /**
     * Header of the elapsed-since-previous column.
     */
    case DELTA = 'Delta';

    /**
     * Explanation of the empty state, naming the target that feeds the panel.
     */
    case EMPTY_EXPLANATION = 'This request did not emit log messages through the debug log target.';

    /**
     * Headline of the empty state when the request emitted no log message.
     */
    case EMPTY_HEADLINE = 'No log messages captured';

    /**
     * Label of the error severity in the level filter.
     */
    case FILTER_ERROR = 'Error';

    /**
     * Label of the info severity in the level filter.
     */
    case FILTER_INFO = 'Info';

    /**
     * Label of the trace severity in the level filter.
     */
    case FILTER_TRACE = 'Trace';

    /**
     * Label of the warning severity in the level filter.
     */
    case FILTER_WARNING = 'Warning';

    /**
     * Header of the severity column, also used as its filter label.
     */
    case LEVEL = 'Level';

    /**
     * Error severity as named in the chip tooltip and accessible label.
     */
    case LEVEL_ERROR = 'error';

    /**
     * Plural noun of the error chip.
     */
    case LEVEL_ERRORS = 'errors';

    /**
     * Info severity, used both as the chip noun and as the severity named in its tooltip.
     */
    case LEVEL_INFO = 'info';

    /**
     * Trace severity, used both as the chip noun and as the severity named in its tooltip.
     */
    case LEVEL_TRACE = 'trace';

    /**
     * Warning severity as named in the chip tooltip and accessible label.
     */
    case LEVEL_WARNING = 'warning';

    /**
     * Plural noun of the warning chip.
     */
    case LEVEL_WARNINGS = 'warnings';

    /**
     * Header of the log message column, also used as its filter label.
     */
    case MESSAGE = 'Message';

    /**
     * Suffix appended to the captured message count in the summary header.
     */
    case MESSAGES_SUFFIX = ' messages';

    /**
     * Explanation of the no-match state, offering the filter reset.
     */
    case NO_MATCH_EXPLANATION = 'Adjust or clear the filters to show the captured messages.';

    /**
     * Headline of the no-match state when the active filters exclude every captured message.
     */
    case NO_MATCH_HEADLINE = 'No log messages match the active filters';

    /**
     * Header of the position column, which sorts by capture order.
     */
    case NUMBER = '#';

    /**
     * Header of the capture-time column.
     */
    case TIME = 'Time';

    /**
     * Label of the toolbar metric counting the captured errors.
     */
    case TOOLBAR_ERRORS = 'Errors';

    /**
     * Label of the toolbar metric counting the captured warnings.
     */
    case TOOLBAR_WARNINGS = 'Warnings';
}

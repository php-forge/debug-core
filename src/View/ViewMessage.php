<?php

declare(strict_types=1);

namespace PHPForge\Debug\View;

/**
 * Presentation text of the debugger chrome shared by every adapter: request history, sidebar, and capture comparison.
 */
enum ViewMessage: string
{
    /**
     * Header of the AJAX column, also used as its filter label and as the tag of the sidebar card.
     */
    case AJAX = 'AJAX';

    /**
     * Label of the baseline selector in the comparison form.
     */
    case BASELINE = 'Baseline capture';

    /**
     * Verdict of a compared value that differs between the two captures.
     */
    case CHANGED = 'Changed';

    /**
     * Console request, as named in the method filter of the history grid.
     */
    case COMMAND = 'COMMAND';

    /**
     * Explanation returned when the comparison page is opened with fewer than two captures available.
     */
    case COMPARISON_REQUIRES_TWO = 'At least two captured requests are required for comparison.';

    /**
     * Action label of the sidebar entry leading to the configuration page.
     */
    case CONFIG = 'Config';

    /**
     * Heading of the sidebar card while the debugger shows the request being inspected.
     */
    case CURRENT_REQUEST = 'Current request';

    /**
     * Header of the processing-time column.
     */
    case DURATION = 'Duration';

    /**
     * Label of the sidebar group listing the installed extensions.
     */
    case EXTENSIONS = 'Extensions';

    /**
     * Header of the capture tag column.
     */
    case ID = 'ID';

    /**
     * Verdict of a compared value that matches in both captures.
     */
    case IDENTICAL = 'Identical';

    /**
     * Header of the client address column.
     */
    case IP = 'IP';

    /**
     * Header of the peak-memory column.
     */
    case MEMORY = 'Memory';

    /**
     * Header of the HTTP method column, also used as its filter label and as a comparison metric label.
     */
    case METHOD = 'Method';

    /**
     * Accessible label of the sidebar card while the debugger shows the latest capture.
     */
    case NEWEST_CAPTURED_REQUEST = 'Newest captured request';

    /**
     * Heading of the sidebar card while the debugger shows the latest capture.
     */
    case NEWEST_REQUEST = 'Newest request';

    /**
     * Verdict of a compared metric whose value is the same in both captures.
     */
    case NO_CHANGE = 'No change';

    /**
     * Label of the link opening a panel of the compared capture.
     */
    case OPEN_PANEL = 'Open panel';

    /**
     * Header of the panel column in the comparison table.
     */
    case PANEL = 'Panel';

    /**
     * Label of the peak-memory comparison metric.
     */
    case PEAK_MEMORY = 'Peak memory';

    /**
     * Header of the query-count column.
     */
    case QUERY = 'Query';

    /**
     * Label of the target selector in the comparison form.
     */
    case TARGET = 'Target capture';

    /**
     * Header of the capture-time column.
     */
    case TIME = 'Time';

    /**
     * Debugger name shown as the page title and carried by the toolbar payload.
     */
    case TITLE = 'Yii Debugger';

    /**
     * Header of the request URL column, also used as its filter label.
     */
    case URL = 'URL';
}

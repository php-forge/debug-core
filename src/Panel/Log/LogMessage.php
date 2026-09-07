<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

/**
 * Text shown by the Logs panel.
 */
enum LogMessage: string
{
    /**
     * Explanation of the empty state, naming the target that feeds the panel.
     */
    case EMPTY_EXPLANATION = 'This request did not emit log messages through the debug log target.';

    /**
     * Headline of the empty state when the request emitted no log message.
     */
    case EMPTY_HEADLINE = 'No log messages captured';

    /**
     * Explanation of the no-match state, offering the filter reset.
     */
    case NO_MATCH_EXPLANATION = 'Adjust or clear the filters to show the captured messages.';

    /**
     * Headline of the no-match state when the active filters exclude every captured message.
     */
    case NO_MATCH_HEADLINE = 'No log messages match the active filters';
}

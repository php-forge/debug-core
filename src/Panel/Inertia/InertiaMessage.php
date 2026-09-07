<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Inertia;

/**
 * Text shown by the Inertia panel.
 */
enum InertiaMessage: string
{
    /**
     * Closing note of the empty state, naming the requests that populate the panel.
     */
    case EMPTY_COVERAGE = 'Both full page loads and Inertia XHR visits populate this view; plain JSON '
        . 'endpoints, redirects, and asset requests do not.';

    /**
     * Headline of the empty state when the response carries no Inertia page object.
     */
    case EMPTY_HEADLINE = 'No Inertia page in this request';

    /**
     * Headline of the empty state when Inertia answered the visit with a version conflict.
     */
    case VERSION_CONFLICT_HEADLINE = 'Version conflict interrupted this visit';
}

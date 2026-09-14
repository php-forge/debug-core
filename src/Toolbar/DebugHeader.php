<?php

declare(strict_types=1);

namespace PHPForge\Debug\Toolbar;

/**
 * Response headers every adapter sets so a client can locate the capture of the request it just made.
 */
enum DebugHeader: string
{
    /**
     * Server-side processing time of the captured request, in milliseconds.
     */
    case DURATION = 'X-Debug-Duration';

    /**
     * Absolute URL of the debugger page showing the capture.
     */
    case LINK = 'X-Debug-Link';

    /**
     * Tag identifying the capture in the debugger storage.
     */
    case TAG = 'X-Debug-Tag';
}

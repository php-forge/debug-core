<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

/**
 * Text shared by every debugger panel.
 */
enum PanelMessage: string
{
    /**
     * Heading of the context section in a detail disclosure.
     */
    case CONTEXT = 'Context';

    /**
     * Summary of the group filter disclosure above a panel table.
     */
    case GROUP_FILTERS = 'Group filters';

    /**
     * Heading of the source trace section in a detail disclosure.
     */
    case SOURCE_TRACE = 'Source trace';
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

/**
 * Shared built-in panel names and page headings, preserving distinct navigation and detail labels.
 */
enum PanelTitle: string
{
    /**
     * Asset Bundles panel name and detail heading.
     */
    case ASSETS = 'Asset Bundles';

    /**
     * Capture comparison page title and action label.
     */
    case COMPARE = 'Compare captures';

    /**
     * Configuration panel name and page title.
     */
    case CONFIGURATION = 'Configuration';

    /**
     * Database panel name.
     */
    case DATABASE = 'Database';

    /**
     * Dump panel name and detail heading.
     */
    case DUMP = 'Dump';

    /**
     * Events panel name and detail heading.
     */
    case EVENTS = 'Events';

    /**
     * Database EXPLAIN page title and heading.
     */
    case EXPLAIN = 'EXPLAIN';

    /**
     * Request history navigation label.
     */
    case HISTORY = 'History';

    /**
     * Log panel detail heading.
     */
    case LOG_MESSAGES = 'Log Messages';

    /**
     * Log panel navigation label.
     */
    case LOGS = 'Logs';

    /**
     * Mail panel navigation label.
     */
    case MAIL = 'Mail';

    /**
     * Mail panel detail heading.
     */
    case MAIL_MESSAGES = 'Email messages';

    /**
     * PHP information page title.
     */
    case PHP_INFO = 'PHP Info';

    /**
     * PHP information page heading.
     */
    case PHPINFO = 'phpinfo';

    /**
     * Profiling panel navigation label.
     */
    case PROFILING = 'Profiling';

    /**
     * Profiling panel detail heading.
     */
    case PROFILING_DETAILS = 'Performance Profiling';

    /**
     * Queue panel name and detail heading.
     */
    case QUEUE = 'Queue';

    /**
     * Request panel name and detail heading.
     */
    case REQUEST = 'Request';

    /**
     * Request history page title and heading.
     */
    case REQUEST_HISTORY = 'Request history';

    /**
     * Router panel name and detail heading.
     */
    case ROUTER = 'Router';

    /**
     * Timeline panel name and profiling section heading.
     */
    case TIMELINE = 'Timeline';

    /**
     * Default user panel name, which adapters may override.
     */
    case USER = 'User';

    /**
     * Vite panel name and detail heading.
     */
    case VITE = 'Vite';
}

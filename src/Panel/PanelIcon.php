<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

/**
 * Shared SVG keys for built-in debugger panels.
 *
 * Use the backed value with string-based toolbar contracts and the shared icon renderer.
 */
enum PanelIcon: string
{
    /**
     * Icon key for the Asset Bundles panel.
     */
    case ASSETS = 'asset';

    /**
     * Icon key for the Configuration panel.
     */
    case CONFIGURATION = 'config';

    /**
     * Icon key for the Database panel.
     */
    case DATABASE = 'db';

    /**
     * Icon key for the Dump and fallback JSON panels.
     */
    case DUMP = 'dump';

    /**
     * Icon key for the Events panel.
     */
    case EVENTS = 'events';

    /**
     * Icon key for the Inertia panel.
     */
    case INERTIA = 'inertia';

    /**
     * Icon key for the Logs panel.
     */
    case LOGS = 'logs';

    /**
     * Icon key for the Mail panel.
     */
    case MAIL = 'mail';

    /**
     * Icon key for the Profiling panel.
     */
    case PROFILING = 'profiling';

    /**
     * Icon key for the Queue panel.
     */
    case QUEUE = 'queue';

    /**
     * Icon key for the Request panel.
     */
    case REQUEST = 'request';

    /**
     * Icon key for the Router panel.
     */
    case ROUTER = 'router';

    /**
     * Icon key for the Timeline panel.
     */
    case TIMELINE = 'timeline';

    /**
     * Icon key for the User panel.
     */
    case USER = 'user';

    /**
     * Icon key for the Vite panel.
     */
    case VITE = 'brand-javascript';
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Data;

/**
 * Freezes the `Prefix[attribute]` query-parameter vocabulary shared by every debug-panel filter form.
 */
final class FilterPrefix
{
    /**
     * Query-parameter prefix for the Asset Bundles panel filters.
     */
    public const string ASSET = 'Asset';

    /**
     * Query-parameter prefix for the Database panel filters.
     */
    public const string DB = 'Db';

    /**
     * Query-parameter prefix for the request history panel filters.
     */
    public const string DEBUG = 'Debug';

    /**
     * Query-parameter prefix for the Events panel filters.
     */
    public const string EVENT = 'Event';

    /**
     * Query-parameter prefix for the Logs panel filters.
     */
    public const string LOG = 'Log';

    /**
     * Query-parameter prefix for the Mail panel filters.
     */
    public const string MAIL = 'Mail';

    /**
     * Query-parameter prefix for the Profiling panel filters.
     */
    public const string PROFILE = 'Profile';

    /**
     * Query-parameter prefix for the Queue panel filters.
     */
    public const string QUEUE = 'Queue';

    /**
     * Query-parameter prefix for the Router panel filters.
     */
    public const string ROUTER = 'Router';

    /**
     * Query-parameter prefix for the Timeline panel filters.
     */
    public const string TIMELINE = 'Timeline';

    /**
     * Query-parameter prefix for the User panel filters.
     */
    public const string USER = 'User';
}

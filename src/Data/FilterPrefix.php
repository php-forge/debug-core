<?php

declare(strict_types=1);

namespace PHPForge\Debug\Data;

/**
 * Freezes the `Prefix[attribute]` query-parameter vocabulary shared by every debug-panel filter form.
 */
final class FilterPrefix
{
    public const string ASSET = 'Asset';

    public const string DB = 'Db';

    public const string DEBUG = 'Debug';

    public const string EVENT = 'Event';

    public const string LOG = 'Log';

    public const string MAIL = 'Mail';

    public const string PROFILE = 'Profile';

    public const string QUEUE = 'Queue';

    public const string ROUTER = 'Router';

    public const string TIMELINE = 'Timeline';

    public const string USER = 'User';
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use function in_array;

/**
 * Resolves which database drivers can be explained and the statement prefix each one expects.
 */
final class DbExplainSupport
{
    /**
     * Driver whose plan is requested with `EXPLAIN QUERY PLAN` instead of plain `EXPLAIN`.
     */
    private const string QUERY_PLAN_DRIVER = 'sqlite';
    /**
     * Driver names whose EXPLAIN output the panel can render.
     */
    private const array SUPPORTED_DRIVERS = [
        'mysql',
        'pgsql',
        'sqlite',
    ];

    /**
     * Returns whether the driver produces an EXPLAIN plan the panel can render.
     *
     * @param string $driverName Driver name reported by the connection.
     *
     * @return bool `true` when the driver is supported; `false` otherwise.
     */
    public static function isSupported(string $driverName): bool
    {
        return in_array($driverName, self::SUPPORTED_DRIVERS, true);
    }

    /**
     * Returns the statement prefix that asks the driver for its plan.
     *
     * @param string $driverName Driver name reported by the connection.
     *
     * @return string Prefix to prepend to the explained statement, separator included.
     */
    public static function prefix(string $driverName): string
    {
        return $driverName === self::QUERY_PLAN_DRIVER ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Db;

use PHPForge\Debug\Panel\Db\DbExplainSupport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DbExplainSupport} covering the driver allow-list and the per-driver statement prefix.
 */
#[Group('panel')]
#[Group('db')]
final class DbExplainSupportTest extends TestCase
{
    public function testIsSupportedAcceptsEveryDriverThePanelCanRender(): void
    {
        self::assertTrue(
            DbExplainSupport::isSupported('mysql'),
            'MySQL plans must be offered.',
        );
        self::assertTrue(
            DbExplainSupport::isSupported('pgsql'),
            'PostgreSQL plans must be offered.',
        );
        self::assertTrue(
            DbExplainSupport::isSupported('sqlite'),
            'SQLite plans must be offered.',
        );
    }

    public function testIsSupportedRejectsUnknownDrivers(): void
    {
        self::assertFalse(
            DbExplainSupport::isSupported('sqlsrv'),
            'An unlisted driver must not be offered.',
        );
        self::assertFalse(
            DbExplainSupport::isSupported('MYSQL'),
            'Driver matching must stay case-sensitive.',
        );
        self::assertFalse(
            DbExplainSupport::isSupported(''),
            'An empty driver name must not be offered.',
        );
    }

    public function testPrefixAsksSqliteForItsQueryPlan(): void
    {
        self::assertSame(
            'EXPLAIN QUERY PLAN ',
            DbExplainSupport::prefix('sqlite'),
            'SQLite needs the query-plan form.',
        );
        self::assertSame(
            'EXPLAIN ',
            DbExplainSupport::prefix('mysql'),
            'Other drivers take the plain form.',
        );
    }
}

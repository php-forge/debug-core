<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Db\SqlHighlighterTest;

/**
 * Provides the messages that open a SQL statement and the prose that only borrows its verbs for
 * {@see SqlHighlighterTest}.
 */
final class SqlHighlighterProvider
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function nonStatements(): iterable
    {
        yield 'empty value' => [''];
        yield 'markup' => ['<script>alert(1)</script>'];
        yield 'connection notice' => ['Opening DB connection: sqlite:/tmp/db.sqlite'];
        yield 'prose opening with BEGIN' => ['Begin processing the queue'];
        yield 'prose opening with CREATE' => ['Create something new'];
        yield 'prose opening with DROP' => ['Drop the cache directory'];
        yield 'prose opening with SELECT' => ['Select me'];
        yield 'prose opening with UPDATE' => ['Update available for the package'];
        yield 'projection without source' => ['SELECT 1'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statements(): iterable
    {
        yield 'alter table' => ['ALTER TABLE "post" ADD "views" integer'];
        yield 'begin terminated by a semicolon' => ['BEGIN;'];
        yield 'commit' => ['COMMIT'];
        yield 'common table expression' => ['WITH "recent" AS (SELECT 1) SELECT * FROM "recent"'];
        yield 'create table' => ['CREATE TABLE "post" ("id" integer)'];
        yield 'create unique index' => ['CREATE UNIQUE INDEX "idx_post_id" ON "post" ("id")'];
        yield 'delete' => ['DELETE FROM "post" WHERE "id" = 1'];
        yield 'drop view' => ['DROP VIEW "post_stats"'];
        yield 'explain' => ['EXPLAIN QUERY PLAN SELECT * FROM "post"'];
        yield 'insert' => ['INSERT INTO "post" ("id") VALUES (1)'];
        yield 'lowercase select' => ['select * from "post"'];
        yield 'pragma' => ['PRAGMA foreign_keys = ON'];
        yield 'replace' => ['REPLACE INTO "post" ("id") VALUES (1)'];
        yield 'rollback transaction' => ['ROLLBACK TRANSACTION'];
        yield 'select spanning several lines' => ["SELECT \"id\"\nFROM \"post\""];
        yield 'select with leading whitespace' => ["  \n SELECT * FROM \"post\""];
        yield 'show' => ['SHOW TABLES'];
        yield 'truncate table' => ['TRUNCATE TABLE "post"'];
        yield 'update' => ['UPDATE "post" SET "status" = 1'];
        yield 'vacuum' => ['VACUUM "main"'];
    }
}

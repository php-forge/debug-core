<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Db\DbCaptureTest;

/**
 * Provides query types and statements for EXPLAIN eligibility checks in {@see DbCaptureTest}.
 */
final class DbCaptureProvider
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function explainableStatements(): iterable
    {
        yield 'empty query type' => ['', 'SELECT 1', false];
        yield 'lowercase DELETE' => ['delete', 'SELECT 1', true];
        yield 'lowercase INSERT' => ['insert', 'SELECT 1', true];
        yield 'lowercase REPLACE' => ['replace', 'SELECT 1', true];
        yield 'lowercase SELECT' => ['select', 'SELECT 1', true];
        yield 'lowercase UPDATE' => ['update', 'SELECT 1', true];
        yield 'lowercase WITH' => ['with', 'SELECT 1', true];
        yield 'multiple statements' => ['SELECT', 'SELECT 1; SELECT 2', false];
        yield 'unsupported SHOW' => ['SHOW', 'SELECT 1', false];
        yield 'uppercase DELETE' => ['DELETE', 'SELECT 1', true];
        yield 'uppercase INSERT' => ['INSERT', 'SELECT 1', true];
        yield 'uppercase REPLACE' => ['REPLACE', 'SELECT 1', true];
        yield 'uppercase SELECT' => ['SELECT', 'SELECT 1', true];
        yield 'uppercase UPDATE' => ['UPDATE', 'SELECT 1', true];
        yield 'uppercase WITH' => ['WITH', 'SELECT 1', true];
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Db\DbQueryRendererTest;

/**
 * Provides explainable and non-explainable SQL types for {@see DbQueryRendererTest}.
 */
final class DbQueryRendererProvider
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function explainableVerbs(): iterable
    {
        yield 'lowercase DELETE' => ['delete'];
        yield 'lowercase INSERT' => ['insert'];
        yield 'lowercase REPLACE' => ['replace'];
        yield 'lowercase SELECT' => ['select'];
        yield 'lowercase UPDATE' => ['update'];
        yield 'lowercase WITH' => ['with'];
        yield 'uppercase DELETE' => ['DELETE'];
        yield 'uppercase INSERT' => ['INSERT'];
        yield 'uppercase REPLACE' => ['REPLACE'];
        yield 'uppercase SELECT' => ['SELECT'];
        yield 'uppercase UPDATE' => ['UPDATE'];
        yield 'uppercase WITH' => ['WITH'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonExplainableVerbs(): iterable
    {
        yield 'empty query type' => [''];
        yield 'unsupported PRAGMA' => ['PRAGMA'];
    }
}

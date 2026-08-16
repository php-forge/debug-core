<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Vocabulary;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Vocabulary} mapping HTTP and SQL values to semantic suffixes.
 *
 * @since 0.1
 */
#[Group('helpers')]
#[Group('vocabulary')]
final class VocabularyTest extends TestCase
{
    public function testSqlVerbMapsStatementFamiliesToRestVerbs(): void
    {
        $mappings = [
            'SELECT' => 'get', 'SHOW' => 'get', 'EXPLAIN' => 'get', 'DESCRIBE' => 'get', 'PRAGMA' => 'get',
            'INSERT' => 'post',
            'UPDATE' => 'put', 'REPLACE' => 'put', 'UPSERT' => 'put',
            'DELETE' => 'delete', 'DROP' => 'delete', 'TRUNCATE' => 'delete',
            'select' => 'get',
            'Insert' => 'post',
            'BOGUS' => 'other',
            '' => 'other',
        ];

        foreach ($mappings as $type => $expected) {
            self::assertSame(
                $expected,
                Vocabulary::sqlVerb($type),
                "Statement '{$type}' must map to '{$expected}'.",
            );
        }
    }

    public function testStatusClassMapsRangeBoundariesToStatusSuffixes(): void
    {
        $mappings = [
            0 => 'none',
            100 => 'none',
            199 => 'none',
            200 => '2xx',
            299 => '2xx',
            300 => '3xx',
            399 => '3xx',
            400 => '4xx',
            499 => '4xx',
            500 => '5xx',
            599 => '5xx',
            999 => '5xx',
        ];

        foreach ($mappings as $code => $expected) {
            self::assertSame(
                $expected,
                Vocabulary::statusClass($code),
                "Code '{$code}' must map to '{$expected}'.",
            );
        }
    }

    public function testVerbMapsHttpMethodsToVocabularySuffixes(): void
    {
        $mappings = [
            'GET' => 'get', 'HEAD' => 'get',
            'POST' => 'post',
            'PUT' => 'put', 'PATCH' => 'put',
            'DELETE' => 'delete',
            'get' => 'get',
            'pAtCh' => 'put',
            'OPTIONS' => 'other',
            'TRACE' => 'other',
            'CONNECT' => 'other',
            'COMMAND' => 'other',
            '' => 'other',
            'BOGUS' => 'other',
        ];

        foreach ($mappings as $method => $expected) {
            self::assertSame(
                $expected,
                Vocabulary::verb($method),
                "Method '{$method}' must map to '{$expected}'.",
            );
        }
    }
}

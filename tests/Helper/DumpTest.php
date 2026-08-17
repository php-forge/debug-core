<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Dump;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Dump} covering the display and parsable renderings of the JSON-safe value domain.
 *
 * @since 0.1
 */
#[Group('helpers')]
final class DumpTest extends TestCase
{
    public function testAsStringCollapsesArraysBeyondTheDepthLimit(): void
    {
        self::assertSame(
            "[\n    0 => [\n        0 => [...]\n    ]\n]",
            Dump::asString([[['deep']]], 2),
            'Nesting beyond the depth limit must collapse to `[...]`.',
        );
    }

    public function testAsStringRendersArraysWithKeysAndWithoutTrailingCommas(): void
    {
        self::assertSame(
            "[\n    'a' => 1\n    'b' => [\n        'x' => null\n        0 => true\n    ]\n    'c' => 'it\\'s'\n]",
            Dump::asString(['a' => 1, 'b' => ['x' => null, 0 => true], 'c' => "it's"]),
            'Array rendering must match the VarDumper display conventions.',
        );
    }

    public function testAsStringRendersScalarsBare(): void
    {
        self::assertSame(
            '5',
            Dump::asString(5),
            'Int must render bare.',
        );
        self::assertSame(
            '1.5',
            Dump::asString(1.5),
            'Float must render bare.',
        );
        self::assertSame(
            'true',
            Dump::asString(true),
            "'true' must render bare.",
        );
        self::assertSame(
            'false',
            Dump::asString(false),
            "'false' must render bare.",
        );
        self::assertSame(
            'null',
            Dump::asString(null),
            "'null' must render bare.",
        );
        self::assertSame(
            "'abc'",
            Dump::asString('abc'),
            'String must render quoted.',
        );
        self::assertSame(
            '[]',
            Dump::asString([]),
            'Empty array must render compact.',
        );
    }

    public function testAsStringRendersUnsupportedTypesAsPlaceholders(): void
    {
        self::assertSame(
            '{stdClass}',
            Dump::asString(new \stdClass()),
            'Objects must degrade to their type placeholder.',
        );
    }

    public function testExportOmitsSequentialIntegerKeysAndKeepsExplicitOnes(): void
    {
        self::assertSame(
            "[\n    'a',\n    'b',\n]",
            Dump::export(['a', 'b']),
            'Sequential lists must omit their keys.',
        );
        self::assertSame(
            "[\n    0 => 'a',\n    1 => 'b',\n    5 => 1.5,\n]",
            Dump::export(['a', 'b', 5 => 1.5]),
            'Non-sequential arrays must keep their keys.',
        );
    }

    public function testExportRendersNestedArraysWithTrailingCommas(): void
    {
        self::assertSame(
            "[\n    'k' => [\n        'nested',\n    ],\n    'z' => null,\n]",
            Dump::export(['k' => ['nested'], 'z' => null]),
            'Nested export must match the VarDumper parsable conventions.',
        );
    }

    public function testExportRendersScalarsViaVarExport(): void
    {
        self::assertSame(
            "'abc'",
            Dump::export('abc'),
            'String must export quoted.',
        );
        self::assertSame(
            '5',
            Dump::export(5),
            'Int must export bare.',
        );
        self::assertSame(
            'true',
            Dump::export(true),
            "'true' must export bare.",
        );
        self::assertSame(
            'null',
            Dump::export(null),
            "'null' must export lowercase.",
        );
        self::assertSame(
            '[]',
            Dump::export([]),
            'Empty array must export compact.',
        );
        self::assertSame(
            '{stdClass}',
            Dump::export(new \stdClass()),
            'Objects must degrade to their type placeholder.',
        );
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\{DumpEntry, DumpKind, DumpNode};
use PHPForge\Debug\Tests\Panel\Log\PhpExportParserTest;
use PHPForge\Debug\Tests\Support\PhpExport\ExportedPoint;
use PHPForge\Debug\Tests\Support\PrintR\{PrintRChild, PrintRPriority, PrintRSuit, PrintRToggle};

use function var_export;

use const INF;
use const NAN;
use const PHP_INT_MIN;

/**
 * Data provider for {@see PhpExportParserTest} test cases.
 */
final class PhpExportParserProvider
{
    /**
     * @return iterable<string, array{string, DumpNode}>
     */
    public static function exports(): iterable
    {
        $suit = DumpNode::enumCase(PrintRSuit::class, 'HEARTS');
        $roles = DumpNode::block(
            DumpKind::ARRAY,
            '',
            [
                new DumpEntry('0', DumpNode::string('admin')),
                new DumpEntry('1', DumpNode::string('editor')),
            ],
        );

        yield 'nested arrays' => [
            var_export(['user' => ['id' => 42, 'roles' => ['admin', 'editor']], 'empty' => []], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry(
                        'user',
                        DumpNode::block(
                            DumpKind::ARRAY,
                            '',
                            [
                                new DumpEntry('id', DumpNode::integer('42')),
                                new DumpEntry('roles', $roles),
                            ],
                        ),
                    ),
                    new DumpEntry('empty', DumpNode::block(DumpKind::ARRAY, '', [])),
                ],
            ),
        ];
        yield 'empty root array' => [
            var_export([], true),
            DumpNode::block(DumpKind::ARRAY, '', []),
        ];
        yield 'object with nested array and enum case' => [
            var_export(new ExportedPoint(), true),
            DumpNode::block(
                DumpKind::OBJECT,
                ExportedPoint::class,
                [
                    new DumpEntry('empty', DumpNode::block(DumpKind::ARRAY, '', [])),
                    new DumpEntry('suit', $suit),
                    new DumpEntry('tags', DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('roles', $roles)])),
                    new DumpEntry('x', DumpNode::integer('1')),
                ],
            ),
        ];
        yield 'object with protected, private, and inherited properties' => [
            var_export(new PrintRChild(), true),
            DumpNode::block(
                DumpKind::OBJECT,
                PrintRChild::class,
                [
                    new DumpEntry('role', DumpNode::string('admin')),
                    new DumpEntry('secret', DumpNode::string('parent')),
                    new DumpEntry('empty', DumpNode::string('')),
                    new DumpEntry('flag', DumpNode::boolean('false')),
                    new DumpEntry('name', DumpNode::string('Ada')),
                    new DumpEntry('self', DumpNode::null('NULL')),
                    new DumpEntry('suit', $suit),
                    new DumpEntry(
                        'tags',
                        DumpNode::block(
                            DumpKind::ARRAY,
                            '',
                            [
                                new DumpEntry('0', DumpNode::string('a')),
                                new DumpEntry('1', DumpNode::string('b')),
                            ],
                        ),
                    ),
                    new DumpEntry('parent', DumpNode::null('NULL')),
                    new DumpEntry('secret', DumpNode::string('child')),
                ],
            ),
        ];
        yield 'object nested in an array' => [
            var_export(['point' => new ExportedPoint()], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry(
                        'point',
                        DumpNode::block(
                            DumpKind::OBJECT,
                            ExportedPoint::class,
                            [
                                new DumpEntry('empty', DumpNode::block(DumpKind::ARRAY, '', [])),
                                new DumpEntry('suit', $suit),
                                new DumpEntry(
                                    'tags',
                                    DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('roles', $roles)]),
                                ),
                                new DumpEntry('x', DumpNode::integer('1')),
                            ],
                        ),
                    ),
                ],
            ),
        ];
        yield 'stdClass' => [
            var_export((object) ['k' => 'v', 'inner' => (object) [], 'list' => [1]], true),
            DumpNode::block(
                DumpKind::OBJECT,
                'stdClass',
                [
                    new DumpEntry('k', DumpNode::string('v')),
                    new DumpEntry('inner', DumpNode::block(DumpKind::OBJECT, 'stdClass', [])),
                    new DumpEntry('list', DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('0', DumpNode::integer('1'))])),
                ],
            ),
        ];
        yield 'pure and backed enum cases' => [
            var_export([PrintRToggle::ON, PrintRSuit::HEARTS, PrintRPriority::HIGH], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('0', DumpNode::enumCase(PrintRToggle::class, 'ON')),
                    new DumpEntry('1', $suit),
                    new DumpEntry('2', DumpNode::enumCase(PrintRPriority::class, 'HIGH')),
                ],
            ),
        ];
        yield 'strings' => [
            var_export(
                [
                    'quote' => "it's",
                    'backslash' => 'a\\b',
                    'trailing backslash' => '\\',
                    'escape-like' => '\\n',
                    'NUL' => "a\0b\0",
                    'line breaks' => "a\r\nb\n\nc",
                    'empty' => '',
                    'markup' => '<b>&</b>',
                    "key\0with NUL" => 1,
                ],
                true,
            ),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('quote', DumpNode::string("it's")),
                    new DumpEntry('backslash', DumpNode::string('a\\b')),
                    new DumpEntry('trailing backslash', DumpNode::string('\\')),
                    new DumpEntry('escape-like', DumpNode::string('\\n')),
                    new DumpEntry('NUL', DumpNode::string("a\0b\0")),
                    new DumpEntry('line breaks', DumpNode::string("a\r\nb\n\nc")),
                    new DumpEntry('empty', DumpNode::string('')),
                    new DumpEntry('markup', DumpNode::string('<b>&</b>')),
                    new DumpEntry("key\0with NUL", DumpNode::integer('1')),
                ],
            ),
        ];
        yield 'numbers' => [
            var_export(
                [-5 => PHP_INT_MIN, 'int' => -1, 'zero' => 0, 'f' => 1.0, 'e' => 1.0E+25, 'neg' => -1.5, 'nz' => -0.0],
                true,
            ),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('-5', DumpNode::integer('-9223372036854775807-1')),
                    new DumpEntry('int', DumpNode::integer('-1')),
                    new DumpEntry('zero', DumpNode::integer('0')),
                    new DumpEntry('f', DumpNode::float('1.0')),
                    new DumpEntry('e', DumpNode::float('1.0E+25')),
                    new DumpEntry('neg', DumpNode::float('-1.5')),
                    new DumpEntry('nz', DumpNode::float('-0.0')),
                ],
            ),
        ];
        yield 'special floats' => [
            var_export([NAN, INF, -INF], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('0', DumpNode::float('NAN')),
                    new DumpEntry('1', DumpNode::float('INF')),
                    new DumpEntry('2', DumpNode::float('-INF')),
                ],
            ),
        ];
        yield 'booleans and null' => [
            var_export(['t' => true, 'f' => false, 'n' => null], true),
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('t', DumpNode::boolean('true')),
                    new DumpEntry('f', DumpNode::boolean('false')),
                    new DumpEntry('n', DumpNode::null('NULL')),
                ],
            ),
        ];
        yield 'constants in any letter case' => [
            "[\n    TRUE,\n    False,\n    Null,\n]",
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('0', DumpNode::boolean('TRUE')),
                    new DumpEntry('1', DumpNode::boolean('False')),
                    new DumpEntry('2', DumpNode::null('Null')),
                ],
            ),
        ];
        yield 'VarDumper::export() keyless lists and lowercase null' => [
            <<<'PHP'
            [
                'list' => [
                    1,
                    'two',
                    null,
                    false,
                    [],
                ],
                'map' => [
                    'k' => 1.5,
                    'n' => 'a' . "\0" . '',
                ],
            ]
            PHP,
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry(
                        'list',
                        DumpNode::block(
                            DumpKind::ARRAY,
                            '',
                            [
                                new DumpEntry('0', DumpNode::integer('1')),
                                new DumpEntry('1', DumpNode::string('two')),
                                new DumpEntry('2', DumpNode::null('null')),
                                new DumpEntry('3', DumpNode::boolean('false')),
                                new DumpEntry('4', DumpNode::block(DumpKind::ARRAY, '', [])),
                            ],
                        ),
                    ),
                    new DumpEntry(
                        'map',
                        DumpNode::block(
                            DumpKind::ARRAY,
                            '',
                            [
                                new DumpEntry('k', DumpNode::float('1.5')),
                                new DumpEntry('n', DumpNode::string("a\0")),
                            ],
                        ),
                    ),
                ],
            ),
        ];
        yield 'last entry without a trailing comma' => [
            "[\n    1,\n    2\n]",
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [
                    new DumpEntry('0', DumpNode::integer('1')),
                    new DumpEntry('1', DumpNode::integer('2')),
                ],
            ),
        ];
        yield 'array without space before the parenthesis' => [
            "[\n  0 => array(\n    'a' => 1,\n  ),\n]",
            DumpNode::block(
                DumpKind::ARRAY,
                '',
                [new DumpEntry('0', DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('a', DumpNode::integer('1'))]))],
            ),
        ];
        yield 'surrounding whitespace and Windows line breaks' => [
            "\r\n  array (\r\n  0 => \r\n  array (\r\n  ),\r\n)\r\n",
            DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('0', DumpNode::block(DumpKind::ARRAY, '', []))]),
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected(): iterable
    {
        yield 'plain text' => ["line one\nline two"];
        yield 'root array without space before the parenthesis' => ["array(\n  0 => 1,\n)"];
        yield 'free text before the export' => ["Loaded: array (\n  0 => 1,\n)"];
        yield 'invalid PHP' => ["array (\n  'a' => ,\n)"];
        yield 'text after the export' => ["[\n    1,\n] . 'x'"];
        yield 'second export after the export' => ["[\n    1,\n] + [\n    2,\n]"];
        yield 'closure' => ["[\n    function () {\n        return 1;\n    },\n]"];
        yield 'anonymous class' => ["[\n    new class {},\n]"];
        yield 'unserialize call' => ["[\n    'o' => unserialize('O:8:\"stdClass\":0:{}'),\n]"];
        yield 'function call' => ["[\n    strlen('a'),\n]"];
        yield 'constant' => ["[\n    PHP_EOL,\n]"];
        yield 'class constant without call' => ["[\n    \\App\\Config,\n]"];
        yield 'static call other than __set_state' => ["[\n    \\App\\Config::load(array(\n    )),\n]"];
        yield '__set_state without an array' => ['\\App\\Point::__set_state(1)'];
        yield '__set_state with a second argument' => ["\\App\\Point::__set_state(array(\n), 1)"];
        yield 'cast to object of a scalar' => ["[\n    (object) 1,\n]"];
        yield 'double-quoted string' => ["[\n    \"a\",\n]"];
        yield 'double-quoted escape other than NUL' => ["[\n    'a' . \"\\n\",\n]"];
        yield 'binary string prefix' => ["[\n    b'a',\n]"];
        yield 'concatenation with a number' => ["[\n    'a' . 1,\n]"];
        yield 'negative NAN' => ["[\n    -NAN,\n]"];
        yield 'negative boolean' => ["[\n    -true,\n]"];
        yield 'negative string' => ["[\n    -'a',\n]"];
        yield 'negative array' => ["[\n    -[],\n]"];
        yield 'subtraction' => ["[\n    5-1,\n]"];
        yield 'subtraction after a negative integer' => ["[\n    -5-2,\n]"];
        yield 'float key' => ["[\n    1.5 => 1,\n]"];
        yield 'boolean key' => ["[\n    true => 1,\n]"];
        yield 'enum case key' => ["[\n    \\App\\Suit::A => 1,\n]"];
        yield 'keyed entry after keyless entries' => ["[\n    1,\n    'k' => 2,\n]"];
        yield 'keyless entry after keyed entries' => ["[\n    'k' => 1,\n    2,\n]"];
        yield 'nested mixed entries' => ["[\n    'a' => [\n        0 => 1,\n        2,\n    ],\n]"];
        yield 'parenthesized value' => ["[\n    (1),\n]"];
    }
}

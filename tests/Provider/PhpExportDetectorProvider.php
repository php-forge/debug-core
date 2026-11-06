<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Log\PhpExportDetectorTest;
use PHPForge\Debug\Tests\Support\PhpExport\ExportedPoint;

use function var_export;

use const INF;
use const NAN;
use const PHP_INT_MIN;

/**
 * Data provider for {@see PhpExportDetectorTest} test cases.
 */
final class PhpExportDetectorProvider
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function exports(): iterable
    {
        yield 'nested array' => [
            var_export(['user' => ['id' => 42, 'roles' => ['admin', 'editor']], 'flags' => ['limit' => null]], true),
            <<<'PHP'
            [
                'user' => [
                    'id' => 42,
                    'roles' => [
                        0 => 'admin',
                        1 => 'editor',
                    ],
                ],
                'flags' => [
                    'limit' => NULL,
                ],
            ]
            PHP,
        ];
        yield 'empty arrays' => [
            var_export(['e' => [], 'n' => [[]]], true),
            <<<'PHP'
            [
                'e' => [],
                'n' => [
                    0 => [],
                ],
            ]
            PHP,
        ];
        yield 'object with nested array and enum case' => [
            var_export(new ExportedPoint(), true),
            <<<'PHP'
            \PHPForge\Debug\Tests\Support\PhpExport\ExportedPoint::__set_state([
                'empty' => [],
                'suit' => \PHPForge\Debug\Tests\Support\PrintR\PrintRSuit::HEARTS,
                'tags' => [
                    'roles' => [
                        0 => 'admin',
                        1 => 'editor',
                    ],
                ],
                'x' => 1,
            ])
            PHP,
        ];
        yield 'object nested in an array' => [
            var_export(['point' => new ExportedPoint()], true),
            <<<'PHP'
            [
                'point' => \PHPForge\Debug\Tests\Support\PhpExport\ExportedPoint::__set_state([
                    'empty' => [],
                    'suit' => \PHPForge\Debug\Tests\Support\PrintR\PrintRSuit::HEARTS,
                    'tags' => [
                        'roles' => [
                            0 => 'admin',
                            1 => 'editor',
                        ],
                    ],
                    'x' => 1,
                ]),
            ]
            PHP,
        ];
        yield 'stdClass' => [
            var_export((object) ['k' => 'v', 'inner' => (object) [], 'list' => [1]], true),
            <<<'PHP'
            (object) [
                'k' => 'v',
                'inner' => (object) [],
                'list' => [
                    0 => 1,
                ],
            ]
            PHP,
        ];
        yield 'strings holding array syntax' => [
            var_export(['a (b)' => "array (\n)", 'q' => "it's \\ )"], true),
            <<<'PHP'
            [
                'a (b)' => 'array (
            )',
                'q' => 'it\'s \\ )',
            ]
            PHP,
        ];
        yield 'special scalars' => [
            var_export([-5 => -INF, 'f' => 1.0E+25, 'n' => NAN, 'z' => -0.0, 'b' => false, "a\0b" => "x\0"], true),
            <<<'PHP'
            [
                -5 => -INF,
                'f' => 1.0E+25,
                'n' => NAN,
                'z' => -0.0,
                'b' => false,
                'a' . "\0" . 'b' => 'x' . "\0" . '',
            ]
            PHP,
        ];
        yield 'short array export' => [
            <<<'PHP'
            [
                'a' => [
                    'b' => 1,
                ],
                'c' => unserialize('O:8:"stdClass":0:{}'),
            ]
            PHP,
            <<<'PHP'
            [
                'a' => [
                    'b' => 1,
                ],
                'c' => unserialize('O:8:"stdClass":0:{}'),
            ]
            PHP,
        ];
        yield 'brackets inside strings' => [
            var_export(['a]' => '[', ')' => ['(' => ']]'], '"x]"' => "]\n["], true),
            <<<'PHP'
            [
                'a]' => '[',
                ')' => [
                    '(' => ']]',
                ],
                '"x]"' => ']
            [',
            ]
            PHP,
        ];
        yield 'integer minimum' => [
            var_export([PHP_INT_MIN], true),
            "[\n    0 => -9223372036854775807-1,\n]",
        ];
        yield 'array without space before the parenthesis' => [
            "[\n  0 => array(\n    0 => 1,\n  ),\n]",
            "[\n    0 => [\n        0 => 1,\n    ],\n]",
        ];
        yield 'surrounding whitespace' => [
            "\n  array (\n  0 => 1,\n)\n",
            "[\n    0 => 1,\n]",
        ];
        yield 'Windows line breaks' => [
            "array (\r\n  0 => \r\n  array (\r\n    0 => 1,\r\n  ),\r\n)",
            "[\n    0 => [\n        0 => 1,\n    ],\n]",
        ];
        yield 'carriage return line breaks' => [
            "array (\r  0 => \r  array (\r    0 => 1,\r  ),\r)",
            "[\n    0 => [\n        0 => 1,\n    ],\n]",
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejected(): iterable
    {
        yield 'plain text' => ["line one\nline two"];
        yield 'quoted string' => ["'line one\nline two'"];
        yield 'invalid PHP' => ["array (\n  'a' => ,\n)"];
        yield 'free text before the export' => ["Loaded: array (\n  0 => 1,\n)"];
        yield 'valid PHP before the export' => ["1 + [\n    2,\n]"];
        yield 'class constant' => ["\\App\\Config::VALUES + [\n    2,\n]"];
        yield 'closing tag with inline output' => ["[\n    1,\n] ?> <b>x</b>"];
        yield 'closing tag reopening PHP' => ["[\n    1,\n] ?><?php echo 1"];
        yield 'extra statement' => ["[\n    1,\n]; echo 1"];
        yield 'statement terminator only' => ["[\n    1,\n];"];
        yield 'comment after the terminator' => ["[\n    1,\n]; // c"];
        yield 'comment swallowing the terminator' => ["[\n    1,\n] // c"];
        yield 'hash comment' => ["[\n    1, # c\n]"];
        yield 'block comment' => ["[\n    /* c */ 1,\n]"];
        yield 'doc comment' => ["[\n    /** c */ 1,\n]"];
        yield 'interpolated variable before a bracket' => ["[\n    \"\$x]\",\n]"];
        yield 'interpolated expression in braces' => ["[\n    \"{\$x}\",\n]"];
        yield 'interpolated dollar brace' => ["[\n    \"\${x}\",\n]"];
        yield 'heredoc' => ["[\n    <<<EOT\n    ]\n    EOT,\n]"];
        yield 'nowdoc' => ["[\n    <<<'EOT'\n    ]\n    EOT,\n]"];
        yield 'variable' => ["[\n    \$x,\n]"];
        yield 'arrow function' => ["[\n    fn(array \$a): array => \$a,\n]"];
        yield 'closure' => ["[\n    function () {\n        return 1;\n    },\n]"];
        yield 'cast to bool' => ["[\n    (bool) 1,\n]"];
    }
}

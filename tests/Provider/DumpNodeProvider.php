<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\{DumpEntry, DumpKind, DumpNode};
use PHPForge\Debug\Tests\Panel\Log\DumpNodeTest;
use stdClass;

use function range;

use const PHP_INT_MIN;

/**
 * Data provider for {@see DumpNodeTest} test cases.
 */
final class DumpNodeProvider
{
    /**
     * @return iterable<string, array{mixed, DumpNode}>
     */
    public static function arrays(): iterable
    {
        yield 'empty array' => [
            [],
            DumpNode::block(DumpKind::ARRAY, '', []),
        ];
        yield 'nested arrays' => [
            ['user' => ['id' => 7, 'roles' => ['admin']], 3 => null],
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
                                new DumpEntry('id', DumpNode::integer('7')),
                                new DumpEntry(
                                    'roles',
                                    DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('0', DumpNode::string('admin'))]),
                                ),
                            ],
                        ),
                    ),
                    new DumpEntry('3', DumpNode::null('NULL')),
                ],
            ),
        ];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function overBounds(): iterable
    {
        $nested = 1;

        for ($level = 0; $level < 33; $level++) {
            $nested = [$nested];
        }

        yield 'thirty-three nested arrays' => [$nested];
        yield 'root block plus 5000 values' => [range(1, 5000)];
        yield 'root and nested blocks plus 4999 values' => [['n' => range(1, 4999)]];
    }

    /**
     * @return iterable<string, array{mixed, DumpKind, string}>
     */
    public static function scalars(): iterable
    {
        yield 'string' => [
            "a'<b>\n",
            DumpKind::STRING,
            "a'<b>\n",
        ];
        yield 'empty string' => [
            '',
            DumpKind::STRING,
            '',
        ];
        yield 'integer' => [
            42,
            DumpKind::INTEGER,
            '42',
        ];
        yield 'integer minimum' => [
            PHP_INT_MIN,
            DumpKind::INTEGER,
            '-9223372036854775807-1',
        ];
        yield 'float with zero fraction' => [
            1.0,
            DumpKind::FLOAT,
            '1.0',
        ];
        yield 'float' => [
            -0.5,
            DumpKind::FLOAT,
            '-0.5',
        ];
        yield 'true' => [
            true,
            DumpKind::BOOLEAN,
            'true',
        ];
        yield 'false' => [
            false,
            DumpKind::BOOLEAN,
            'false',
        ];
        yield 'null' => [
            null,
            DumpKind::NULL,
            'NULL',
        ];
        yield 'value outside the normalized types' => [
            new stdClass(),
            DumpKind::SCALAR,
            '{stdClass}',
        ];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function withinBounds(): iterable
    {
        $nested = 1;

        for ($level = 0; $level < 32; $level++) {
            $nested = [$nested];
        }

        yield 'thirty-two nested arrays' => [$nested];
        yield 'root block plus 4999 values' => [range(1, 4999)];
        yield 'root and nested blocks plus 4998 values' => [['n' => range(1, 4998)]];
    }
}

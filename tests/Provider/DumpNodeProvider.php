<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Panel\Log\{DumpEntry, DumpKind, DumpNode};
use PHPForge\Debug\Tests\Panel\Log\DumpNodeTest;
use stdClass;

use const PHP_INT_MIN;

/**
 * Data provider for {@see DumpNodeTest} test cases.
 */
final class DumpNodeProvider
{
    /**
     * @return iterable<string, array{mixed, DumpNode}>
     */
    public static function values(): iterable
    {
        yield 'string' => [
            "a'<b>\n",
            DumpNode::string("a'<b>\n"),
        ];
        yield 'empty string' => [
            '',
            DumpNode::string(''),
        ];
        yield 'integer' => [
            42,
            DumpNode::integer('42'),
        ];
        yield 'integer minimum' => [
            PHP_INT_MIN,
            DumpNode::integer('-9223372036854775807-1'),
        ];
        yield 'float with zero fraction' => [
            1.0,
            DumpNode::float('1.0'),
        ];
        yield 'float' => [
            -0.5,
            DumpNode::float('-0.5'),
        ];
        yield 'true' => [
            true,
            DumpNode::boolean('true'),
        ];
        yield 'false' => [
            false,
            DumpNode::boolean('false'),
        ];
        yield 'null' => [
            null,
            DumpNode::null('NULL'),
        ];
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
        yield 'value outside the normalized types' => [
            new stdClass(),
            DumpNode::scalar('{stdClass}'),
        ];
    }
}

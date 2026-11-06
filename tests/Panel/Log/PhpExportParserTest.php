<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Panel\Log\{DumpEntry, DumpKind, DumpNode, PhpExportParser};
use PHPForge\Debug\Tests\Provider\PhpExportParserProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function range;
use function str_repeat;
use function strlen;
use function var_export;

/**
 * Unit tests for {@see PhpExportParser} covering real `var_export()` output, `VarDumper::export()` shaped exports,
 * rejected messages, and the byte, depth, and value count bounds.
 *
 * {@see PhpExportParserProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('log')]
final class PhpExportParserTest extends TestCase
{
    public function testParseAcceptsMessageAtByteLimit(): void
    {
        $value = str_repeat('a', 262133);
        $message = "[\n    '{$value}',\n]";

        self::assertSame(
            262144,
            strlen($message),
            'Fixture must be exactly at the byte limit.',
        );
        self::assertEquals(
            DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('0', DumpNode::string($value))]),
            PhpExportParser::parse($message),
            'Message at the byte limit must parse.',
        );
    }

    public function testParseAcceptsNestingAtDepthLimit(): void
    {
        $expected = DumpNode::block(DumpKind::ARRAY, '', []);

        for ($level = 1; $level < 32; $level++) {
            $expected = DumpNode::block(DumpKind::ARRAY, '', [new DumpEntry('0', $expected)]);
        }

        self::assertEquals(
            $expected,
            PhpExportParser::parse(str_repeat('[', 32) . str_repeat(']', 32)),
            'Thirty-two nested arrays must parse.',
        );
    }

    public function testParseAcceptsObjectNestingAtDepthLimit(): void
    {
        $expected = DumpNode::block(DumpKind::OBJECT, 'stdClass', []);

        for ($level = 1; $level < 32; $level++) {
            $expected = DumpNode::block(DumpKind::OBJECT, 'App\Node', [new DumpEntry('n', $expected)]);
        }

        self::assertEquals(
            $expected,
            PhpExportParser::parse(
                str_repeat("\\App\\Node::__set_state(array('n' => ", 31) . '(object) array()' . str_repeat('))', 31),
            ),
            'Thirty-two nested objects must parse.',
        );
    }

    public function testParseAcceptsValueCountAtNodeLimit(): void
    {
        $entries = PhpExportParser::parse(var_export(range(1, 4999), true))->entries ?? [];

        self::assertCount(
            4999,
            $entries,
            'Root block plus 4999 entries must parse.',
        );
        self::assertEquals(
            new DumpEntry('4998', DumpNode::integer('4999')),
            $entries[4998] ?? null,
            'Last entry must keep its key and value.',
        );
    }

    #[DataProviderExternal(PhpExportParserProvider::class, 'exports')]
    public function testParseBuildsTypedTreeFromExport(string $message, DumpNode $expected): void
    {
        self::assertEquals(
            $expected,
            PhpExportParser::parse($message),
            'Tree must mirror every block, key, type, and value of the export.',
        );
    }

    public function testParseReturnsNullForMessageOverByteLimit(): void
    {
        $message = "[\n    '" . str_repeat('a', 262134) . "',\n]";

        self::assertSame(
            262145,
            strlen($message),
            'Fixture must be one byte over the limit.',
        );
        self::assertNull(
            PhpExportParser::parse($message),
            'Message over the byte limit must not parse.',
        );
    }

    #[DataProviderExternal(PhpExportParserProvider::class, 'rejected')]
    public function testParseReturnsNullForMessageThatIsNotOneSupportedExport(string $message): void
    {
        self::assertNull(
            PhpExportParser::parse($message),
            'Unsupported export must fall back to `null`.',
        );
    }

    public function testParseReturnsNullForNestingOverDepthLimit(): void
    {
        self::assertNull(
            PhpExportParser::parse(str_repeat('[', 33) . str_repeat(']', 33)),
            'Thirty-three nested arrays must not parse.',
        );
    }

    public function testParseReturnsNullForObjectNestingOverDepthLimit(): void
    {
        self::assertNull(
            PhpExportParser::parse(
                str_repeat("\\App\\Node::__set_state(array('n' => ", 32) . '(object) array()' . str_repeat('))', 32),
            ),
            'Thirty-three nested objects must not parse.',
        );
    }

    public function testParseReturnsNullForValueCountOverNodeLimit(): void
    {
        self::assertNull(
            PhpExportParser::parse(var_export(range(1, 5000), true)),
            'Root block plus 5000 entries must not parse.',
        );
    }
}

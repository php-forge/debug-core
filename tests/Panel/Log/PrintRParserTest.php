<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use PHPForge\Debug\Panel\Log\{PrintREntry, PrintRKind, PrintRNode, PrintRParser};
use PHPForge\Debug\Tests\Provider\PrintRParserProvider;
use PHPForge\Debug\Tests\Support\PrintR\PrintRFixture;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function strlen;

/**
 * Unit tests for {@see PrintRParser} covering real `print_r()` dumps, malformed input, and the size, depth, and value
 * count bounds.
 *
 * {@see PrintRParserProvider} for test case data providers.
 */
#[Group('panel')]
#[Group('log')]
final class PrintRParserTest extends TestCase
{
    public function testParseAcceptsMessageAtByteLimit(): void
    {
        $message = PrintRFixture::sized(262144);

        self::assertSame(
            262144,
            strlen($message),
            'Fixture must be exactly at the byte limit.',
        );
        self::assertEquals(
            PrintRNode::block(
                PrintRKind::ARRAY,
                '',
                [new PrintREntry('k', PrintRNode::scalar(str_repeat('a', 262144 - 22)))],
            ),
            PrintRParser::parse($message),
            'Message at the byte limit must parse.',
        );
    }

    public function testParseAcceptsNestingAtDepthLimit(): void
    {
        $expected = PrintRNode::block(PrintRKind::ARRAY, '', []);

        for ($level = 1; $level < 32; $level++) {
            $expected = PrintRNode::block(PrintRKind::ARRAY, '', [new PrintREntry('n', $expected)]);
        }

        self::assertEquals(
            $expected,
            PrintRParser::parse(PrintRFixture::nested(32)),
            'Thirty-two nested blocks must parse.',
        );
    }

    public function testParseAcceptsValueCountAtNodeLimit(): void
    {
        $entries = PrintRParser::parse(PrintRFixture::list(4999))->entries ?? [];

        self::assertCount(
            4999,
            $entries,
            'Root block plus 4999 entries must parse.',
        );
        self::assertEquals(
            new PrintREntry('4998', PrintRNode::scalar('4999')),
            $entries[4998] ?? null,
            'Last entry must keep its key and value.',
        );
    }

    #[DataProviderExternal(PrintRParserProvider::class, 'dumps')]
    public function testParseBuildsTreeFromDump(string $message, PrintRNode $expected): void
    {
        self::assertEquals(
            $expected,
            PrintRParser::parse($message),
            'Tree must mirror every block, entry, visibility, and value of the dump.',
        );
    }

    #[DataProviderExternal(PrintRParserProvider::class, 'invalid')]
    public function testParseReturnsNullForMalformedMessage(string $message): void
    {
        self::assertNull(
            PrintRParser::parse($message),
            'Malformed dump must fall back to `null`.',
        );
    }

    public function testParseReturnsNullForMessageOverByteLimit(): void
    {
        self::assertNull(
            PrintRParser::parse(PrintRFixture::sized(262145)),
            'Message one byte over the limit must not parse.',
        );
    }

    public function testParseReturnsNullForNestingOverDepthLimit(): void
    {
        self::assertNull(
            PrintRParser::parse(PrintRFixture::nested(33)),
            'Thirty-three nested blocks must not parse.',
        );
    }

    public function testParseReturnsNullForValueCountOverNodeLimit(): void
    {
        self::assertNull(
            PrintRParser::parse(PrintRFixture::list(5000)),
            'Root block plus 5000 entries must not parse.',
        );
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Registration;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;
use PHPForge\Debug\Registration\EntryParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see EntryParser} covering entry shapes, the `enabled` flag, and registration key checks.
 */
#[Group('registration')]
final class EntryParserTest extends TestCase
{
    public function testAssertKeyMatchesIdAcceptsAListKey(): void
    {
        EntryParser::assertKeyMatchesId(0, 'cache', 'collector');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertKeyMatchesIdAcceptsTheDeclaredId(): void
    {
        EntryParser::assertKeyMatchesId('cache', 'cache', 'panel');

        $this->expectNotToPerformAssertions();
    }

    public function testEnabledDefaultsToTrueWhenTheOptionIsAbsent(): void
    {
        self::assertTrue(
            EntryParser::enabled(['class' => 'Acme\\CacheCollector'], 'cache'),
            'An absent flag must enable the entry.',
        );
    }

    public function testEnabledReturnsTheDeclaredFlag(): void
    {
        self::assertFalse(
            EntryParser::enabled(['enabled' => false], 'cache'),
            "Declared 'false' must be returned.",
        );
        self::assertTrue(
            EntryParser::enabled(['enabled' => true], 'cache'),
            "Declared 'true' must be returned.",
        );
    }

    public function testEnabledTreatsNullAsAbsent(): void
    {
        self::assertTrue(
            EntryParser::enabled(['enabled' => null], 'cache'),
            "'null' must count as an absent flag.",
        );
    }

    public function testParseKeepsEveryOptionBesideTheClass(): void
    {
        $entry = EntryParser::parse(
            ['class' => 'Acme\\CachePanel', 'enabled' => false, 'title' => 'Cache'],
            'cache',
        );

        self::assertSame(
            'Acme\\CachePanel',
            $entry->class,
            "Class must be read from the 'class' key.",
        );
        self::assertSame(
            ['enabled' => false, 'title' => 'Cache'],
            $entry->options,
            "Options must exclude 'class' and keep 'enabled'.",
        );
    }

    public function testParseReadsAClassStringWithoutOptions(): void
    {
        $entry = EntryParser::parse('Acme\\CacheCollector', 'cache');

        self::assertSame(
            'Acme\\CacheCollector',
            $entry->class,
            'Class string must be carried verbatim.',
        );
        self::assertSame(
            [],
            $entry->options,
            'A class string declares no option.',
        );
    }

    public function testThrowInvalidArgumentExceptionForArrayEntryWithoutAClassString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            Message::REGISTRATION_ENTRY_INVALID->getMessage('cache'),
        );

        EntryParser::parse(['class' => 42, 'enabled' => true], 'cache');
    }

    public function testThrowInvalidArgumentExceptionForEntryThatIsNeitherStringNorArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            Message::REGISTRATION_ENTRY_INVALID->getMessage('cache'),
        );

        EntryParser::parse(42, 'cache');
    }

    public function testThrowInvalidArgumentExceptionForKeyNotMatchingTheDeclaredId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            Message::REGISTRATION_ID_MISMATCH->getMessage('panel', 'wrong', 'vite'),
        );

        EntryParser::assertKeyMatchesId('wrong', 'vite', 'panel');
    }

    public function testThrowInvalidArgumentExceptionForNonBooleanEnabledFlag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            Message::REGISTRATION_ENABLED_INVALID->getMessage('cache'),
        );

        EntryParser::enabled(['enabled' => 'yes'], 'cache');
    }
}

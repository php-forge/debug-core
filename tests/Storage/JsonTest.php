<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use PHPForge\Debug\Storage\Json;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Json} covering UTF-8 preservation and binary representation.
 */
#[Group('storage')]
final class JsonTest extends TestCase
{
    public function testSafeStringPreservesUtf8(): void
    {
        self::assertSame(
            'Debug failure.',
            Json::safeString('Debug failure.'),
            'Valid UTF-8 must remain unchanged.',
        );
    }

    public function testSafeStringRepresentsBinaryAsBase64(): void
    {
        self::assertSame(
            '(binary: base64 sTE=)',
            Json::safeString("\xB1\x31"),
            'Binary text must be represented as base64.',
        );
    }
}

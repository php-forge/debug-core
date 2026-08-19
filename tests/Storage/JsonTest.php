<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use PHPForge\Debug\Storage\Json;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for {@see Json} covering UTF-8 preservation and binary representation.
 */
#[Group('storage')]
final class JsonTest extends TestCase
{
    public function testPrivateConstructorContainsNoInitializationBehavior(): void
    {
        $reflection = new ReflectionClass(Json::class);

        $instance = $reflection->newInstanceWithoutConstructor();

        $reflection->getConstructor()?->invoke($instance);

        self::assertSame(
            Json::class,
            $instance::class,
            'Invoking the private constructor must preserve helper type.',
        );
    }

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

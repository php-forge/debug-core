<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see SensitiveDataRedactor} covering exact, case-insensitive, and nested key replacement.
 *
 * @since 0.1
 */
#[Group('helpers')]
final class SensitiveDataRedactorTest extends TestCase
{
    public function testDefaultPolicyBoundsDepthAndNodeTraversal(): void
    {
        $deep = ['password' => 'secret'];

        for ($depth = 0; $depth < 11; $depth++) {
            $deep = ['nested' => $deep];
        }

        $wide = SensitiveDataRedactor::redact(range(1, 10_050));

        self::assertStringContainsString(
            SensitiveDataRedactor::TRUNCATED,
            json_encode(SensitiveDataRedactor::redact($deep), JSON_THROW_ON_ERROR),
            'Redaction must stop traversing beyond the depth budget.',
        );
        self::assertCount(10_001, $wide, 'Redaction must retain one explicit marker at the node boundary.');
        self::assertSame(
            SensitiveDataRedactor::TRUNCATED,
            $wide[10_000] ?? null,
            'Redaction must stop traversing values beyond the node budget.',
        );
    }

    public function testRedactNormalizesNestedPublicObjectsBeforeTraversal(): void
    {
        self::assertSame(
            ['payload' => ['token' => SensitiveDataRedactor::PLACEHOLDER]],
            SensitiveDataRedactor::redact(['payload' => (object) ['token' => 'secret']]),
            'Nested public object properties must not bypass key redaction.',
        );
    }
    public function testRedactPreservesNumericKeysAndNonSensitiveValues(): void
    {
        self::assertSame(
            [0 => 'first', 'user' => ['name' => 'Ada']],
            SensitiveDataRedactor::redact([0 => 'first', 'user' => ['name' => 'Ada']], ['password']),
            'Unmatched values and numeric keys must remain unchanged.',
        );
    }

    public function testRedactReplacesConfiguredKeysCaseInsensitivelyAtEveryDepth(): void
    {
        self::assertSame(
            [
                'Password' => SensitiveDataRedactor::PLACEHOLDER,
                'nested' => [
                    'accessToken' => SensitiveDataRedactor::PLACEHOLDER,
                    'tokenSuffix' => 'visible',
                ],
            ],
            SensitiveDataRedactor::redact(
                [
                    'Password' => 'secret',
                    'nested' => [
                        'accessToken' => 'token',
                        'tokenSuffix' => 'visible',
                    ],
                ],
                ['password', 'ACCESSTOKEN'],
            ),
            'Configured keys must match exactly and without case sensitivity throughout nested arrays.',
        );
    }

    public function testSensitiveKeyMatchingUsesDefaultsOrAnExplicitList(): void
    {
        self::assertTrue(
            SensitiveDataRedactor::isSensitiveKey('AUTHORIZATION'),
            'Default key matching must ignore case.',
        );
        self::assertTrue(
            SensitiveDataRedactor::isSensitiveKey('private', ['PRIVATE']),
            'Explicit key matching must ignore case.',
        );
        self::assertFalse(
            SensitiveDataRedactor::isSensitiveKey('privateSuffix', ['private']),
            'Key matching must remain exact.',
        );
    }
}

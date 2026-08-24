<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use InvalidArgumentException;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see SensitiveDataRedactor} covering exact, case-insensitive, and nested key replacement.
 */
#[Group('helpers')]
final class SensitiveDataRedactorTest extends TestCase
{
    public function testConfigurationRejectsEmptyPrefixesAndInvalidPatterns(): void
    {
        try {
            SensitiveDataRedactor::redact(['value' => 'secret'], [], ['']);
            self::fail('An empty sensitive-key prefix would match every key and must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString(
                'must not be empty',
                $exception->getMessage(),
                'The empty-prefix error must explain the unsafe configuration.',
            );
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid PCRE pattern');

        SensitiveDataRedactor::isSensitiveKey('value', [], [], ['~[~']);
    }

    public function testDefaultKeysCoverCommonEnvironmentCredentialNames(): void
    {
        self::assertSame(
            [
                'DB_PASSWORD' => SensitiveDataRedactor::PLACEHOLDER,
                'AWS_SECRET_ACCESS_KEY' => SensitiveDataRedactor::PLACEHOLDER,
                'DATABASE_URL' => SensitiveDataRedactor::PLACEHOLDER,
                'MY_APP_PASSWORD' => SensitiveDataRedactor::PLACEHOLDER,
                'REDIS_PASSWORD' => SensitiveDataRedactor::PLACEHOLDER,
                'SMTP_API_TOKEN' => SensitiveDataRedactor::PLACEHOLDER,
                'DATABASE_HOST' => 'database.test',
                'tokenizer' => 'safe-service',
                'passwordless' => true,
            ],
            SensitiveDataRedactor::redact(
                [
                    'DB_PASSWORD' => 'database-secret',
                    'AWS_SECRET_ACCESS_KEY' => 'cloud-secret',
                    'DATABASE_URL' => 'mysql://user:secret@database.test/app',
                    'MY_APP_PASSWORD' => 'application-secret',
                    'REDIS_PASSWORD' => 'redis-secret',
                    'SMTP_API_TOKEN' => 'smtp-secret',
                    'DATABASE_HOST' => 'database.test',
                    'tokenizer' => 'safe-service',
                    'passwordless' => true,
                ],
            ),
            'High-risk environment credential names must be redacted without hiding safe connection metadata.',
        );
    }
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
        self::assertCount(
            10_001,
            $wide,
            'Redaction must retain one explicit marker at the node boundary.',
        );
        self::assertSame(
            SensitiveDataRedactor::TRUNCATED,
            $wide[10_000] ?? null,
            'Redaction must stop traversing values beyond the node budget.',
        );
    }

    public function testDefaultPolicyPreservesTheExactDepthBoundaryAndLaterSiblings(): void
    {
        $value = [
            'tooDeep' => ['password' => 'secret'],
            'after' => 'preserved',
        ];
        $expected = [
            'tooDeep' => SensitiveDataRedactor::TRUNCATED,
            'after' => 'preserved',
        ];

        for ($depth = 0; $depth < 10; $depth++) {
            $value = ['nested' => $value];
            $expected = ['nested' => $expected];
        }

        self::assertSame(
            $expected,
            SensitiveDataRedactor::redact($value),
            'Only arrays beyond depth ten must truncate, without dropping later siblings.',
        );
    }

    public function testLiteralPrefixesAndPatternsAreAdditiveAndConfigurable(): void
    {
        $value = [
            'PRIVATE_ONE' => 'one',
            'privateTwo' => 'two',
            'credential_123' => 'three',
            'public' => 'visible',
        ];

        self::assertSame(
            [
                'PRIVATE_ONE' => SensitiveDataRedactor::PLACEHOLDER,
                'privateTwo' => SensitiveDataRedactor::PLACEHOLDER,
                'credential_123' => SensitiveDataRedactor::PLACEHOLDER,
                'public' => 'visible',
            ],
            SensitiveDataRedactor::redact(
                $value,
                [],
                ['private'],
                ['~^credential_\d+$~i'],
            ),
            'Literal prefixes and PCRE patterns must augment exact-key matching throughout a value tree.',
        );
        self::assertTrue(
            SensitiveDataRedactor::isSensitiveKey('PrivateValue', [], ['PRIVATE']),
            'Literal prefixes must ignore case.',
        );
        self::assertFalse(
            SensitiveDataRedactor::isSensitiveKey('not_credential_123', [], [], ['~^credential_\d+$~i']),
            'Configured patterns must retain their caller-defined anchoring semantics.',
        );
        self::assertFalse(
            SensitiveDataRedactor::isSensitiveKey('MY_APP_PASSWORD', SensitiveDataRedactor::DEFAULT_KEYS, [], []),
            'An explicit empty pattern list must opt out while retaining exact default keys.',
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

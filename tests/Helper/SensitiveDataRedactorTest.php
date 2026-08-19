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
}

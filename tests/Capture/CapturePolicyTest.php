<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Capture;

use InvalidArgumentException;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see CapturePolicy} covering default redaction and persistent-body limits.
 */
#[Group('capture')]
final class CapturePolicyTest extends TestCase
{
    public function testConstructorRejectsANonPositiveBodyLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('greater than zero');

        $policy = new CapturePolicy(maxBodyBytes: 0);

        self::fail('Expected invalid policy construction to fail, got ' . $policy::class . '.');
    }

    public function testPolicySupportsAnEmptySensitiveKeyList(): void
    {
        self::assertSame(
            'password=visible',
            (new CapturePolicy([]))->redactText('password=visible'),
            'An explicitly empty key list must disable assignment redaction.',
        );
    }

    public function testPolicySupportsAnExplicitSensitiveKeyList(): void
    {
        $policy = new CapturePolicy(['private']);

        self::assertSame(
            ['private' => SensitiveDataRedactor::PLACEHOLDER, 'password' => 'visible'],
            $policy->redact(['private' => 'secret', 'password' => 'visible']),
            'An explicit policy must replace the default key list.',
        );
        self::assertTrue($policy->isSensitiveKey('PRIVATE'), 'Policy key checks must ignore case.');
        self::assertFalse($policy->isSensitiveKey('password'), 'Explicit policy keys must replace defaults.');
    }

    public function testRedactBodySanitizesObjectsAndOpaqueAssignments(): void
    {
        $policy = new CapturePolicy();

        self::assertSame(
            [
                'decoded' => ['password' => SensitiveDataRedactor::PLACEHOLDER],
                'raw' => SensitiveDataRedactor::PLACEHOLDER,
            ],
            $policy->redactBody('{"password":"object-secret"}', (object) ['password' => 'object-secret']),
            'Decoded objects must be normalized and redacted before persistence.',
        );
        self::assertStringNotContainsString(
            'opaque-secret',
            $policy->redactBody('password=opaque-secret', null)['raw'],
            'Opaque assignment bodies must receive best-effort text redaction.',
        );
    }

    public function testRedactBodySuppressesRawSecretsAndPreservesSafeDecodedData(): void
    {
        $policy = new CapturePolicy();

        self::assertSame(
            [
                'decoded' => ['password' => SensitiveDataRedactor::PLACEHOLDER],
                'raw' => SensitiveDataRedactor::PLACEHOLDER,
            ],
            $policy->redactBody('password=secret', ['password' => 'secret']),
            'A redacted decoded body must not retain its raw secret.',
        );
        self::assertSame(
            ['decoded' => ['page' => 1], 'raw' => 'page=1'],
            $policy->redactBody('page=1', ['page' => 1]),
            'A safe decoded body may retain its bounded raw form.',
        );
    }

    public function testRedactBodyTruncatesOpaqueBodiesAtTheByteLimit(): void
    {
        self::assertSame(
            ['decoded' => null, 'raw' => 'abc' . SensitiveDataRedactor::TRUNCATED],
            (new CapturePolicy(maxBodyBytes: 3))->redactBody('abcdef', null),
            'Opaque bodies must be truncated at the configured byte limit.',
        );
    }

    public function testRedactTextSanitizesAssignmentsWithoutMatchingSuffixes(): void
    {
        $policy = new CapturePolicy();

        self::assertSame(
            'Failure: password=[redacted]; Authorization: [redacted]',
            $policy->redactText('Failure: password=secret; Authorization: Bearer credential'),
            'Diagnostic assignments must retain their key while removing the value.',
        );
        self::assertSame(
            'tokenSuffix=visible',
            $policy->redactText('tokenSuffix=visible'),
            'Sensitive keys must not match longer suffix keys.',
        );
    }

    public function testRedactUrlSanitizesQueryValuesAndPreservesFragments(): void
    {
        $policy = new CapturePolicy();

        self::assertSame(
            'https://example.test/path',
            $policy->redactUrl('https://example.test/path'),
            'A URL without a query must stay unchanged.',
        );
        self::assertSame(
            'https://example.test/path?page=1&token=%5Bredacted%5D#result',
            $policy->redactUrl('https://example.test/path?page=1&token=secret#result'),
            'Sensitive query values must be redacted while the fragment is preserved.',
        );
    }
}

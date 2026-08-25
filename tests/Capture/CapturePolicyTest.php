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
    public function testConstructorAcceptsTheSmallestPositiveBodyLimit(): void
    {
        self::assertSame(
            ['decoded' => null, 'raw' => 'a'],
            (new CapturePolicy(maxBodyBytes: 1))->redactBody('a', null),
            'A one-byte body limit must remain valid.',
        );
    }
    public function testConstructorRejectsANonPositiveBodyLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'greater than zero',
        );

        $policy = new CapturePolicy(maxBodyBytes: 0);

        self::fail(
            'Expected invalid policy construction to fail, got ' . $policy::class . '.',
        );
    }

    public function testConstructorRejectsInvalidPatternConfigurationImmediately(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid PCRE pattern');

        new CapturePolicy(sensitiveKeyPatterns: ['invalid']);
    }

    public function testPolicyDefaultPatternsAreSegmentAwareAndCanBeDisabled(): void
    {
        $defaultPolicy = new CapturePolicy();
        $exactOnlyPolicy = new CapturePolicy(sensitiveKeyPatterns: []);

        self::assertTrue(
            $defaultPolicy->isSensitiveKey('MY_APP_PASSWORD'),
            'Default policy patterns must cover segmented environment credential keys.',
        );
        self::assertFalse(
            $defaultPolicy->isSensitiveKey('tokenizer'),
            'Default patterns must not redact safe words that merely contain a credential substring.',
        );
        self::assertFalse(
            $exactOnlyPolicy->isSensitiveKey('MY_APP_PASSWORD'),
            'An explicit empty pattern list must preserve an exact-key-only policy.',
        );
        self::assertTrue(
            $exactOnlyPolicy->isSensitiveKey('password'),
            'Disabling patterns must retain the configured exact keys.',
        );
    }

    public function testPolicySupportsAdditivePrefixesAndPatternsAcrossRepresentations(): void
    {
        $policy = new CapturePolicy(
            sensitiveKeys: [],
            sensitiveKeyPrefixes: ['private_'],
            sensitiveKeyPatterns: ['~^credential-\d+$~i'],
        );

        self::assertSame(
            [
                'private_key' => SensitiveDataRedactor::PLACEHOLDER,
                'credential-42' => SensitiveDataRedactor::PLACEHOLDER,
                'public_key' => 'visible',
            ],
            $policy->redact(
                [
                    'private_key' => 'one',
                    'credential-42' => 'two',
                    'public_key' => 'visible',
                ],
            ),
            'Policy prefix and pattern rules must apply to decoded value trees.',
        );
        self::assertSame(
            'private_key=[redacted]; credential-42: [redacted]; public_key=visible',
            $policy->redactText('private_key=one; credential-42: two; public_key=visible'),
            'Policy prefix and pattern rules must apply to opaque assignment text.',
        );
        self::assertSame(
            '/callback?private_code=%5Bredacted%5D&public=visible',
            $policy->redactUrl('/callback?private_code=secret&public=visible'),
            'Policy prefix rules must apply to parsed URL query keys.',
        );
    }

    public function testPolicySupportsAnEmptySensitiveKeyList(): void
    {
        self::assertSame(
            '=visible',
            (new CapturePolicy([]))->redactText('=visible'),
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
        self::assertTrue(
            $policy->isSensitiveKey('PRIVATE'),
            'Policy key checks must ignore case.',
        );
        self::assertFalse(
            $policy->isSensitiveKey('password'),
            'Explicit policy keys must replace defaults.',
        );
        self::assertSame(
            'privateXkey=visible; private.key=[redacted]',
            (new CapturePolicy(['private.key']))->redactText('privateXkey=visible; private.key=secret'),
            'Regular-expression characters in configured keys must be matched literally.',
        );
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
        self::assertSame(
            'password=[redacted]',
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
        self::assertSame(
            ['decoded' => null, 'raw' => 'abc'],
            (new CapturePolicy(maxBodyBytes: 3))->redactBody('abc', null),
            'A body exactly at the byte limit must not be truncated.',
        );
        self::assertSame(
            ['decoded' => null, 'raw' => str_repeat('a', 65_536) . SensitiveDataRedactor::TRUNCATED],
            (new CapturePolicy())->redactBody(str_repeat('a', 65_537), null),
            'The default body limit must remain exactly 65,536 bytes.',
        );
    }

    public function testRedactTextAppliesPatternRulesWhenPatternsAreTheOnlyConfiguredSource(): void
    {
        $policy = new CapturePolicy(sensitiveKeys: [], sensitiveKeyPatterns: ['~^secret-word$~i']);

        self::assertSame(
            'secret-word=[redacted]; plain-word=visible',
            $policy->redactText('secret-word=hidden; plain-word=visible'),
            'A pattern-only rule set must keep assignment redaction active.',
        );
    }

    public function testRedactTextAppliesPrefixRulesWhenPrefixesAreTheOnlyConfiguredSource(): void
    {
        $policy = new CapturePolicy(sensitiveKeys: [], sensitiveKeyPrefixes: ['private_'], sensitiveKeyPatterns: []);

        self::assertSame(
            'private_key=[redacted]; public_key=visible',
            $policy->redactText('private_key=secret; public_key=visible'),
            'A prefix-only rule set must keep assignment redaction active.',
        );
    }

    public function testRedactTextAppliesRedactionWhenEveryRuleSourceIsConfigured(): void
    {
        $policy = new CapturePolicy(
            sensitiveKeys: ['password'],
            sensitiveKeyPrefixes: ['private_'],
            sensitiveKeyPatterns: ['~^credential-\d+$~'],
        );

        self::assertSame(
            'password=[redacted]; private_token=[redacted]; credential-7: [redacted]; public=visible',
            $policy->redactText('password=one; private_token=two; credential-7: three; public=visible'),
            'Exact, prefix, and pattern rules must redact concurrently.',
        );
    }

    public function testRedactTextDoesNotExposeAnEarlySecretAheadOfManyLaterAssignments(): void
    {
        $assignments = ['password=early-secret'];

        for ($index = 0; $index < 5_001; $index++) {
            $assignments[] = "safe_{$index}=visible";
        }

        $redacted = (new CapturePolicy())->redactText(implode(';', $assignments));

        self::assertStringStartsWith(
            'password=[redacted];safe_0=visible',
            $redacted,
            'The first assignment must carry the `[redacted]` marker.',
        );
        self::assertStringNotContainsString(
            'early-secret',
            $redacted,
            'The plaintext secret must not survive sanitization.',
        );
    }

    public function testRedactTextDoesNotExposeLateSecretsAfterManyAssignments(): void
    {
        $assignments = [];

        for ($index = 0; $index < 10_001; $index++) {
            $assignments[] = "safe_{$index}=visible";
        }

        $assignments[] = 'MY_APP_PASSWORD=late-secret';

        self::assertStringEndsWith(
            'MY_APP_PASSWORD=[redacted]',
            (new CapturePolicy())->redactText(implode(';', $assignments)),
            'Text redaction must not inherit the nested-value node budget and expose a late credential assignment.',
        );
    }

    public function testRedactTextPreservesSensitiveLookingAssignmentsWhenNoRuleSourceIsConfigured(): void
    {
        $policy = new CapturePolicy(sensitiveKeys: [], sensitiveKeyPrefixes: [], sensitiveKeyPatterns: []);

        self::assertSame(
            'password=secret; Authorization: Bearer credential',
            $policy->redactText('password=secret; Authorization: Bearer credential'),
            'An all-empty rule set must disable assignment redaction.',
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
        self::assertSame(
            'https://example.test/path?page=1#result',
            $policy->redactUrl('https://example.test/path?page=1#result'),
            'A fragment must not become part of a safe query value.',
        );
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Log;

use DateTimeImmutable;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Panel\Log\{LogContext, LogMessage};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

use function array_fill_keys;
use function fclose;
use function fopen;
use function json_encode;
use function range;
use function str_repeat;
use function strlen;

use const INF;
use const JSON_THROW_ON_ERROR;
use const NAN;

/**
 * Unit tests for {@see LogContext} normalizing PSR-3 log contexts into bounded, redacted, JSON-safe value trees.
 */
#[Group('panel')]
#[Group('log')]
final class LogContextTest extends TestCase
{
    public function testNormalizeCollapsesArraysNestedDeeperThanEightLevels(): void
    {
        $context = ['l1' => ['l2' => ['l3' => ['l4' => ['l5' => ['l6' => ['l7' => ['l8' => [1], 'v' => 1]]]]]]]];

        $normalized = LogContext::normalize($context, new CapturePolicy());

        self::assertSame(
            ['l1' => ['l2' => ['l3' => ['l4' => ['l5' => ['l6' => ['l7' => ['l8' => '[...]', 'v' => 1]]]]]]]],
            $normalized,
            'The eighth level must keep its scalars and collapse its arrays.',
        );
    }

    public function testNormalizeCutsKeysAtOneHundredTwentyEightBytes(): void
    {
        $normalized = LogContext::normalize(
            [str_repeat('k', 128) => 1, 'k' . str_repeat('λ', 65) => 2],
            new CapturePolicy(),
        );

        self::assertSame(
            [str_repeat('k', 128) => 1, 'k' . str_repeat('λ', 63) => 2],
            $normalized,
            'Keys must keep 128 bytes on a UTF-8 boundary.',
        );
    }

    public function testNormalizeDropsExcludedKeysBeforeCountingTheTopLevelCap(): void
    {
        $context = ['category' => 'application', ...array_fill_keys(range('a', 'z'), 1), 'A' => 1, 'B' => 1];

        $context += ['C' => 1, 'D' => 1, 'E' => 1, 'F' => 1];

        $normalized = LogContext::normalize($context, new CapturePolicy(), ['category', 'missing']);

        self::assertArrayNotHasKey(
            'category',
            $normalized,
            'Excluded key must be dropped.',
        );
        self::assertCount(
            32,
            $normalized,
            'Excluded keys must not count toward the cap.',
        );
        self::assertSame(
            1,
            $normalized['F'] ?? null,
            'The thirty-second key must be kept.',
        );
    }

    public function testNormalizeKeepsJsonScalarsAndStringifiesNonFiniteFloats(): void
    {
        $normalized = LogContext::normalize(
            [
                'null' => null,
                'true' => true,
                'int' => 42,
                'float' => 1.5,
                'inf' => INF,
                'negativeInf' => -INF,
                'nan' => NAN,
                7 => 'numeric key',
            ],
            new CapturePolicy(),
        );

        self::assertSame(
            [
                'null' => null,
                'true' => true,
                'int' => 42,
                'float' => 1.5,
                'inf' => 'INF',
                'negativeInf' => '-INF',
                'nan' => 'NAN',
                7 => 'numeric key',
            ],
            $normalized,
            'JSON scalars must be kept and non-finite floats stringified.',
        );
    }

    public function testNormalizeLimitsNestedArraysToOneHundredItems(): void
    {
        $map = [];
        $expectedMap = [];

        foreach (range(1, 103) as $index) {
            $map["key{$index}"] = $index;

            if ($index <= 100) {
                $expectedMap["key{$index}"] = $index;
            }
        }

        $expectedMap['...'] = '[+3 more]';

        $normalized = LogContext::normalize(
            ['list' => range(1, 101), 'map' => $map, 'full' => range(1, 100)],
            new CapturePolicy(),
        );

        self::assertSame(
            [...range(1, 100), '[+1 more]'],
            $normalized['list'] ?? null,
            'List marker must be appended.',
        );
        self::assertSame(
            $expectedMap, $normalized['map'] ?? null, 'Map marker must count the cut entries.');
        self::assertSame(
            range(1, 100),
            $normalized['full'] ?? null,
            'One hundred items must not get a marker.',
        );
    }

    public function testNormalizeLimitsTheTopLevelToThirtyTwoKeys(): void
    {
        $context = [];

        foreach (range(1, 35) as $index) {
            $context["key{$index}"] = $index;
        }

        $normalized = LogContext::normalize($context, new CapturePolicy());

        self::assertCount(
            33,
            $normalized,
            'Top level must keep thirty-two keys plus the marker.',
        );
        self::assertSame(
            32,
            $normalized['key32'] ?? null,
            'The thirty-second key must be kept.',
        );
        self::assertArrayNotHasKey(
            'key33',
            $normalized,
            'Keys past the cap must be cut.',
        );
        self::assertSame(
            '[+3 more]',
            $normalized['...'] ?? null,
            'Marker must count the cut keys.',
        );
    }

    public function testNormalizeProducesJsonEncodableTreeForBinaryText(): void
    {
        $normalized = LogContext::normalize(["\xFF" => "\xC3\x28"], new CapturePolicy());

        self::assertSame(
            ['(binary: base64 /w==)' => '(binary: base64 wyg=)'],
            $normalized,
            'Binary keys and values must be represented as base64.',
        );
        self::assertSame(
            '{"(binary: base64 \/w==)":"(binary: base64 wyg=)"}',
            json_encode($normalized, JSON_THROW_ON_ERROR),
            'Tree must survive strict JSON encoding.',
        );
    }

    public function testNormalizeRedactsSensitiveKeysAtEveryDepthAndSecretsInText(): void
    {
        $normalized = LogContext::normalize(
            [
                'password' => ['nested' => 'secret'],
                'request' => ['token' => 'abc', 'query' => 'page=2&api_key=xyz'],
                'user' => 'admin',
            ],
            new CapturePolicy(),
        );

        self::assertSame(
            [
                'password' => '[redacted]',
                'request' => ['token' => '[redacted]', 'query' => 'page=2&api_key=[redacted]'],
                'user' => 'admin',
            ],
            $normalized,
            'Denied keys and secret assignments must be redacted.',
        );
    }

    public function testNormalizeReducesObjectsAndResourcesToReadableStrings(): void
    {
        $exception = new RuntimeException('boom');
        $closedResource = fopen('php://memory', 'rb');

        if ($closedResource !== false) {
            fclose($closedResource);
        }

        $normalized = LogContext::normalize(
            [
                'exception' => $exception,
                'date' => new DateTimeImmutable('2026-10-14 08:30:00+02:00'),
                'enum' => LogMessage::CATEGORY,
                'stringable' => new class implements Stringable {
                    public function __toString(): string
                    {
                        return 'secret=s3cr3t';
                    }
                },
                'failing' => new class implements Stringable {
                    public function __toString(): string
                    {
                        throw new RuntimeException('conversion failed');
                    }
                },
                'object' => new stdClass(),
                'closure' => static fn(): int => 1,
                'resource' => STDIN,
                'closed' => $closedResource,
            ],
            new CapturePolicy(),
        );

        self::assertSame(
            [
                'exception' => "RuntimeException: boom in {$exception->getFile()}:{$exception->getLine()}",
                'date' => '2026-10-14T08:30:00+02:00',
                'enum' => LogMessage::class . '::CATEGORY',
                'stringable' => 'secret=[redacted]',
                'failing' => '{Stringable@anonymous}',
                'object' => '{stdClass}',
                'closure' => '{Closure}',
                'resource' => '{resource (stream)}',
                'closed' => '{resource (closed)}',
            ],
            $normalized,
            'Objects and resources must be reduced to redacted readable strings.',
        );
    }

    public function testNormalizeTruncatesStringsLongerThanTwoThousandFortyEightBytes(): void
    {
        $normalized = LogContext::normalize(
            ['limit' => str_repeat('a', 2048), 'long' => 'a' . str_repeat('λ', 1025)],
            new CapturePolicy(),
        );

        $long = $normalized['long'] ?? '';

        self::assertSame(
            str_repeat('a', 2048),
            $normalized['limit'] ?? null,
            'Strings at the limit must be kept.',
        );
        self::assertIsString(
            $long,
            'Truncated value must stay a string.',
        );
        self::assertSame(
            'a' . str_repeat('λ', 1017) . ' [truncated]',
            $long,
            'Cut must keep a UTF-8 boundary.',
        );
        self::assertSame(
            2047,
            strlen($long),
            'Truncated value must fit the byte budget.',
        );
    }
}

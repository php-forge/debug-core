<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use Closure;
use PHPForge\Debug\Storage\{DebugValue, HydrationException};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Stringable;

/**
 * Unit tests for {@see DebugValue} covering the tagged capture of arbitrary PHP values, the guard rails that keep the
 * payload JSON-safe, and the strict hydration of every tagged type.
 */
#[Group('storage')]
final class DebugValueTest extends TestCase
{
    public function testCaptureFallsBackToTheClassNameWhenStringConversionThrows(): void
    {
        $value = DebugValue::capture(
            new class implements Stringable {
                /**
                 * Simulates a failing string conversion.
                 *
                 * @return string Object label.
                 */
                public function __toString(): string
                {
                    throw new RuntimeException('cannot stringify');
                }
            },
        );

        self::assertSame(
            'object',
            $value->type,
            'The value stays a tagged object.',
        );
        self::assertStringContainsString(
            'Stringable@anonymous',
            (string) $value->value,
            'The class name is the fallback.',
        );
    }

    public function testCaptureLabelsAClosedResourceAsUnsupported(): void
    {
        $handle = fopen('php://memory', 'r');

        self::assertIsResource(
            $handle,
            'The fixture must open a stream.',
        );

        fclose($handle);

        $value = DebugValue::capture($handle);

        self::assertSame(
            'unsupported',
            $value->type,
            'A closed resource is no longer a resource.',
        );
        self::assertSame(
            'unknown-type',
            $value->reason,
            'The reason must record why it was rejected.',
        );
    }

    public function testCaptureLabelsAnOpenResourceWithItsType(): void
    {
        $handle = fopen('php://memory', 'r');

        self::assertIsResource(
            $handle,
            'The fixture must open a stream.',
        );

        $value = DebugValue::capture($handle);

        fclose($handle);

        self::assertSame(
            'resource',
            $value->type,
            'An open resource keeps its tagged type.',
        );
        self::assertSame(
            'stream',
            $value->resourceType,
            'The resource type must be recorded.',
        );
        self::assertSame(
            '(resource: stream)',
            $value->toDisplayValue(),
            'Display value must name the resource.',
        );
        self::assertSame(
            ['type' => 'resource', 'resourceType' => 'stream'],
            $value->jsonSerialize(),
            'Serialized form must carry the resource type.',
        );
        self::assertEquals(
            $value,
            DebugValue::fromArray($value->jsonSerialize()),
            'A resource must round-trip through hydration.',
        );
    }

    public function testCaptureLabelsAThrowableWithItsMessage(): void
    {
        $value = DebugValue::capture(new RuntimeException('boom'));

        self::assertStringContainsString(
            'boom',
            (string) $value->value,
            'The label must carry the message.',
        );
    }

    public function testCaptureNormalizesBinaryArrayAndObjectKeys(): void
    {
        $binaryKey = "\xFF";
        $safeKey = '(binary: base64 /w==)';

        $capturedArray = DebugValue::capture([$binaryKey => 'array value']);

        $arrayEntries = $capturedArray->jsonSerialize()['entries'] ?? null;

        self::assertIsArray(
            $arrayEntries,
            'Serialized array must contain tagged entries.',
        );

        $arrayEntry = $arrayEntries[0] ?? null;

        self::assertIsArray(
            $arrayEntry,
            'First serialized array entry must retain its structure.',
        );
        self::assertSame(
            $safeKey,
            $arrayEntry['key'] ?? null,
            'Binary array key must use a JSON-safe label.',
        );
        self::assertJson(
            json_encode($capturedArray, JSON_THROW_ON_ERROR),
            'Binary array key must not break JSON serialization.',
        );

        $object = new stdClass();

        $object->{$binaryKey} = 'binary property';

        $capturedObject = DebugValue::capture($object);

        $objectEntries = $capturedObject->jsonSerialize()['entries'] ?? null;

        self::assertIsArray(
            $objectEntries,
            'Serialized object must contain tagged entries.',
        );

        $objectEntry = $objectEntries[0] ?? null;

        self::assertIsArray(
            $objectEntry,
            'First serialized object entry must retain its structure.',
        );
        self::assertSame(
            $safeKey,
            $objectEntry['key'] ?? null,
            'Binary object key must use a JSON-safe label.',
        );
        self::assertJson(
            json_encode($capturedObject, JSON_THROW_ON_ERROR),
            'Binary object key must not break JSON serialization.',
        );
    }

    public function testCaptureNormalizesInvalidUtf8StringableLabel(): void
    {
        $value = DebugValue::capture(
            new class implements Stringable {
                /**
                 * Returns a binary fixture label.
                 *
                 * @return string Binary fixture label.
                 */
                public function __toString(): string
                {
                    return "\xB1\x31";
                }
            },
        );

        self::assertSame(
            '(binary: base64 sTE=)',
            $value->value,
            'A binary Stringable label must be represented as base64.',
        );
        self::assertJson(
            json_encode($value, JSON_THROW_ON_ERROR),
            'A binary Stringable label must remain JSON-safe.',
        );
    }

    public function testCaptureNormalizesInvalidUtf8ThrowableMessage(): void
    {
        $value = DebugValue::capture(new RuntimeException("\xB1\x31"));

        self::assertSame(
            RuntimeException::class . ': (binary: base64 sTE=)',
            $value->value,
            'A binary throwable message must be represented as base64.',
        );
        self::assertJson(
            json_encode($value, JSON_THROW_ON_ERROR),
            'A binary throwable message must remain JSON-safe.',
        );
    }

    public function testCapturePreservesTheArrayDepthBoundary(): void
    {
        $atLimit = 'leaf';
        $beyondLimit = 'leaf';

        for ($depth = 0; $depth < 10; $depth++) {
            $atLimit = [$atLimit];
            $beyondLimit = [$beyondLimit];
        }

        $beyondLimit = [$beyondLimit];

        self::assertStringNotContainsString(
            'DEEP NESTED VALUE',
            $this->flatten(DebugValue::capture($atLimit)),
            'Depth ten must remain capturable.',
        );
        self::assertStringContainsString(
            'DEEP NESTED VALUE',
            $this->flatten(DebugValue::capture($beyondLimit)),
            'Depth eleven must be truncated.',
        );
    }

    public function testCapturePreservesTheObjectDepthBoundary(): void
    {
        $atLimit = 'leaf';
        $beyondLimit = 'leaf';

        for ($depth = 0; $depth < 10; $depth++) {
            $atLimit = (object) ['value' => $atLimit];
            $beyondLimit = (object) ['value' => $beyondLimit];
        }

        $beyondLimit = (object) ['value' => $beyondLimit];

        self::assertStringNotContainsString(
            'DEEP NESTED VALUE',
            $this->flatten(DebugValue::capture($atLimit)),
            'Object depth ten must remain capturable.',
        );
        self::assertStringContainsString(
            'DEEP NESTED VALUE',
            $this->flatten(DebugValue::capture($beyondLimit)),
            'Object depth eleven must be truncated.',
        );
    }

    public function testCaptureStopsTraversingObjectBeyondTheNodeLimit(): void
    {
        $object = new stdClass();

        for ($index = 0; $index < 10_050; $index++) {
            $object->{"property-{$index}"} = $index;
        }

        $value = DebugValue::capture($object);
        $entries = $value->jsonSerialize()['entries'] ?? null;

        self::assertIsArray(
            $entries,
            'A captured object must contain serialized entries.',
        );
        self::assertCount(
            10_000,
            $entries,
            'Object traversal must stop after recording the node-limit marker.',
        );
        self::assertStringContainsString(
            'SKIPPED over 10000 nodes',
            $this->flatten($value),
            'Object values past the node budget must be truncated.',
        );
    }

    public function testCaptureStringifiesAStringableObject(): void
    {
        $value = DebugValue::capture(
            new class implements Stringable {
                /**
                 * Returns the fixture label.
                 *
                 * @return string Fixture label.
                 */
                public function __toString(): string
                {
                    return 'rendered';
                }
            },
        );

        self::assertSame(
            'rendered',
            $value->value,
            'A Stringable must be labelled with its string form.',
        );
        self::assertSame(
            'rendered',
            $value->jsonSerialize()['value'] ?? null,
            'Serialized object must retain its label.',
        );
    }

    public function testCaptureTreatsRepeatedObjectReferencesAsIndependentBranches(): void
    {
        $shared = (object) ['name' => 'shared'];

        $display = DebugValue::capture([$shared, $shared])
            ->toDisplayValue();

        self::assertIsArray(
            $display,
            'Top-level value must remain an array.',
        );
        self::assertSame(
            ['__class' => stdClass::class, 'name' => 'shared'],
            $display[0] ?? null,
            'First branch must retain the shared object.',
        );
        self::assertSame(
            ['__class' => stdClass::class, 'name' => 'shared'],
            $display[1] ?? null,
            'Second branch must not be mistaken for recursion.',
        );
    }

    public function testCaptureTruncatesBeyondTheDepthLimit(): void
    {
        $deep = 'leaf';

        for ($i = 0; $i < 12; $i++) {
            $deep = [$deep];
        }

        self::assertStringContainsString(
            'DEEP NESTED VALUE',
            $this->flatten(DebugValue::capture($deep)),
            'Values nested past the depth limit must be truncated.',
        );
    }

    public function testCaptureTruncatesBeyondTheNodeLimit(): void
    {
        $value = DebugValue::capture(range(1, 10_050));

        $entries = $value->jsonSerialize()['entries'] ?? null;

        self::assertIsArray(
            $entries,
            'A captured array must contain serialized entries.',
        );
        self::assertCount(
            10_000,
            $entries,
            'Traversal must stop after recording the node-limit marker.',
        );
        self::assertStringContainsString(
            'SKIPPED over 10000 nodes',
            $this->flatten($value),
            'Values past the node budget must be truncated.',
        );

        $lastInBudget = $entries[9_998] ?? null;
        $firstOutOfBudget = $entries[9_999] ?? null;

        self::assertIsArray(
            $lastInBudget,
            'Last in-budget entry must retain its tagged structure.',
        );
        self::assertIsArray(
            $firstOutOfBudget,
            'First out-of-budget entry must retain its tagged structure.',
        );
        self::assertSame(
            ['type' => 'int', 'value' => 9_999],
            $lastInBudget['value'] ?? null,
            'Last in-budget node must retain its value.',
        );

        $truncatedValue = $firstOutOfBudget['value'] ?? null;

        self::assertIsArray(
            $truncatedValue,
            'Truncation marker must retain its tagged structure.',
        );
        self::assertSame(
            'truncated',
            $truncatedValue['type'] ?? null,
            'First out-of-budget node must carry the truncation marker.',
        );
    }

    public function testCaptureUsesTheCanonicalClosureLabel(): void
    {
        $value = DebugValue::capture(static fn(): bool => true);

        self::assertSame(
            '\\Closure',
            $value->value,
            'Closure label must retain its canonical prefix.',
        );
    }

    public function testRoundTripPreservesJsonSafeValuesAndLabelsUnsafeValues(): void
    {
        $object = new stdClass();

        $object->name = 'debug';
        $object->self = $object;

        $value = DebugValue::capture(
            [
                'binary' => "\xB1\x31",
                'nan' => NAN,
                'positiveInfinity' => INF,
                'negativeInfinity' => -INF,
                'closure' => static fn(): bool => true,
                'object' => $object,
            ],
        );

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        $display = DebugValue::fromArray($decoded)->toDisplayValue();

        self::assertIsArray(
            $display,
            'Top-level display value must remain an array.',
        );
        self::assertIsString(
            $display['binary'] ?? null,
            'Binary data must project to a label.',
        );
        self::assertStringStartsWith(
            '(binary: base64 ',
            $display['binary'],
            'Binary label must identify its base64 representation.',
        );
        self::assertSame(
            'NAN',
            $display['nan'] ?? null,
            'NAN must retain its non-finite label.',
        );
        self::assertSame(
            'INF',
            $display['positiveInfinity'] ?? null,
            'Positive infinity must retain its non-finite label.',
        );
        self::assertSame(
            '-INF',
            $display['negativeInfinity'] ?? null,
            'Negative infinity must retain its non-finite label.',
        );
        self::assertSame(
            ['__class' => Closure::class],
            $display['closure'] ?? null,
            'Closure must project only its class marker.',
        );

        $capturedObject = $display['object'] ?? null;

        self::assertIsArray(
            $capturedObject,
            'Captured object must project to an array.',
        );
        self::assertSame(
            stdClass::class,
            $capturedObject['__class'] ?? null,
            'Object projection must retain its class marker.',
        );
        self::assertSame(
            'debug',
            $capturedObject['name'] ?? null,
            'Public object properties must retain their values.',
        );
        self::assertSame(
            stdClass::class,
            $capturedObject['self'] ?? null,
            'Recursive reference must project to the object class.',
        );
    }

    public function testThrowHydrationExceptionForAnEntryKeyThatDoesNotMatchItsKeyType(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '.key',
        );

        DebugValue::fromArray(
            [
                'type' => 'array',
                'entries' => [
                    [
                        'keyType' => 'int',
                        'key' => 'not-an-int',
                        'value' => ['type' => 'null'],
                    ],
                ],
            ],
        );
    }

    public function testThrowHydrationExceptionForAnUnknownSpecialFloat(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.value',
        );

        DebugValue::fromArray(['type' => 'special-float', 'value' => 'NOPE']);
    }

    public function testThrowHydrationExceptionForAnUnsupportedBinaryEncoding(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.encoding',
        );

        DebugValue::fromArray(['type' => 'binary', 'encoding' => 'hex', 'data' => 'ff']);
    }

    public function testThrowHydrationExceptionForFieldsThatDoNotBelongToTheTaggedType(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.value',
        );

        DebugValue::fromArray(['type' => 'null', 'value' => null]);
    }

    public function testThrowHydrationExceptionForInvalidBinaryData(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.data',
        );

        DebugValue::fromArray(['type' => 'binary', 'encoding' => 'base64', 'data' => '*invalid*']);
    }

    public function testThrowHydrationExceptionForUnknownFields(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.unexpected',
        );

        DebugValue::fromArray(['type' => 'null', 'unexpected' => true]);
    }

    public function testToDisplayValuePreservesScalarAndDiagnosticTypes(): void
    {
        self::assertNull(
            DebugValue::fromArray(['type' => 'null'])->toDisplayValue(),
            'Null tag must project to `null`.',
        );
        self::assertSame(
            1.5,
            DebugValue::fromArray(['type' => 'float', 'value' => 1.5])->toDisplayValue(),
            'Finite float must retain its value.',
        );
        self::assertSame(
            '*LIMIT*',
            DebugValue::fromArray(['type' => 'truncated', 'value' => '*LIMIT*', 'reason' => 'size'])->toDisplayValue(),
            'Truncation label must remain visible.',
        );
        self::assertSame(
            '*UNKNOWN*',
            DebugValue::fromArray(['type' => 'unsupported', 'value' => '*UNKNOWN*', 'reason' => 'fixture'])
                ->toDisplayValue(),
            'Unsupported-value label must remain visible.',
        );
    }

    /**
     * Renders the tagged value as a `string` so truncation labels can be asserted regardless of nesting depth.
     *
     * @param DebugValue $value Tagged value to render.
     *
     * @return string JSON representation used by truncation assertions.
     */
    private function flatten(DebugValue $value): string
    {
        return (string) json_encode($value->jsonSerialize());
    }
}

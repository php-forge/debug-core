<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use PHPForge\Debug\Tests\Support\ArrayPayloadSnapshotFixture;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ArrayPayloadSnapshotFixture} covering capture, hydration, and serialization.
 */
#[Group('storage')]
final class ArrayPayloadSnapshotTest extends TestCase
{
    public function testCaptureHydrateAndSerializeDynamicPayload(): void
    {
        $captured = ArrayPayloadSnapshotFixture::capture(['name' => 'debug', 3 => true]);
        $hydrated = ArrayPayloadSnapshotFixture::fromArray($captured->jsonSerialize(), '$.fixture');

        self::assertSame(
            ['name' => 'debug', 3 => true],
            $hydrated->data(),
            'Plain values must retain their keys and types.',
        );
        self::assertSame(
            $captured->jsonSerialize(),
            $hydrated->jsonSerialize(),
            'Tagged payload must remain byte-for-byte equivalent.',
        );
    }
}

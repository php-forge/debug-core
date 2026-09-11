<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Collector;

use JsonException;
use JsonSerializable;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\CollectorInterface;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Storage\{Json, RequestSummary, SnapshotStore};
use PHPForge\Debug\Tests\Provider\PortablePayloadProvider;
use PHPForge\Debug\Tests\Support\ClosureCollectorFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function is_resource;

/**
 * Unit tests for isolating one collector's unencodable capture from every other panel.
 *
 * Built-in and provider-owned collectors share {@see CollectorInterface}, so the JSON boundary in
 * {@see CollectorCoordinator} applies the same rule to both.
 *
 * {@see PortablePayloadProvider} for test case data providers.
 */
final class PortablePayloadTest extends TestCase
{
    #[DataProviderExternal(PortablePayloadProvider::class, 'invalidPayloads')]
    public function testBadProviderCannotDestroyOtherCaptures(string $case): void
    {
        $resource = fopen('php://memory', 'r');

        $cyclic = [];
        $cyclic['self'] = &$cyclic;
        $deep = [];

        for ($i = 0; $i < 501; ++$i) {
            $deep = ['next' => $deep];
        }

        $value = match ($case) {
            'utf8' => "\xff", 'nan' => NAN, 'inf' => INF, 'resource' => $resource,
            'recursive' => $cyclic, 'depth' => $deep,
            default => new class implements JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    throw new RuntimeException(
                        "Provider serialization failed \xff",
                    );
                }
            },
        };

        $bad = new ClosureCollectorFixture(
            'bad',
            static function () use ($case, $value): array {
                if ($case === 'capture') {
                    throw new RuntimeException('Provider capture failed');
                }
                return ['value' => $value];
            },
        );
        $good = new ClosureCollectorFixture(
            'good',
            static fn(): array => ['value' => 1.0],
        );

        $coordinator = new CollectorCoordinator([$bad, $good]);

        try {
            $snapshot = $coordinator->run(static fn() => $coordinator->capture(RequestSummary::create('isolated')));

            self::assertSame(
                ['good' => ['value' => 1.0]],
                $snapshot->panels,
                'Sibling capture must survive.',
            );
            self::assertSame(
                'capture',
                $snapshot->failures['bad']->stage ?? null,
                "Stage must be 'capture'.",
            );

            $store = new SnapshotStore(sys_get_temp_dir() . '/portable-payload-' . uniqid(), 0o700, 0o600);

            $store->writeSnapshot($snapshot, 5);

            self::assertSame(
                $snapshot->panels,
                $store->readSnapshot('isolated')?->panels,
                'Stored panels must match.',
            );
            self::assertNotNull(
                $store->readSnapshot('isolated')?->failures['bad'] ?? null,
                'Failure must survive the round trip.',
            );

            $store->clear();
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testBuiltInSnapshotPayloadIsIsolatedLikeAProviderPayload(): void
    {
        $broken = new ClosureCollectorFixture(
            'log',
            static fn(): array => LogSnapshot::capture([["\xff", 1, 'application', 1.0, [], 0]])->jsonSerialize(),
        );
        $healthy = new ClosureCollectorFixture(
            'other',
            static fn(): array => LogSnapshot::capture([['ok', 1, 'application', 1.0, [], 0]])->jsonSerialize(),
        );

        $coordinator = new CollectorCoordinator([$broken, $healthy]);

        $snapshot = $coordinator->run(
            static fn() => $coordinator->capture(RequestSummary::create('built-in')),
        );

        $failure = $snapshot->failures['log'] ?? null;

        self::assertArrayNotHasKey(
            'log',
            $snapshot->panels,
            'Unencodable payload must not be persisted.',
        );
        self::assertArrayHasKey(
            'other',
            $snapshot->panels,
            'Sibling built-in capture must survive.',
        );
        self::assertNotNull(
            $failure,
            'An unencodable built-in payload must be recorded as a failure.',
        );
        self::assertSame(
            'capture',
            $failure->stage,
            'Stage must be `capture`.',
        );
        self::assertSame(
            JsonException::class,
            $failure->exception->getClass(),
            'Encoding rejection must be reported verbatim.',
        );
        self::assertArrayNotHasKey(
            'other',
            $snapshot->failures,
            'A healthy panel must not inherit the failure.',
        );
    }

    public function testPayloadDepthBoundaryReservesEnvelopeSpace(): void
    {
        $payload = ['value' => 1];

        for ($i = 0; $i < 498; ++$i) {
            $payload = ['next' => $payload];
        }

        self::assertSame(
            $payload,
            Json::payload($payload),
            "Depth '499' must round-trip unchanged.",
        );

        $this->expectException(JsonException::class);

        Json::payload(['next' => $payload]);
    }

    public function testSerializationCallbacksRunOnlyOnceAndEmptyIsNotMissing(): void
    {
        $value = new class implements JsonSerializable {
            public int $calls = 0;
            public function jsonSerialize(): mixed
            {
                ++$this->calls;
                return ['captured' => $this->calls];
            }
        };

        $coordinator = new CollectorCoordinator(
            [
                new ClosureCollectorFixture('once', static fn(): array => ['value' => $value]),
                new ClosureCollectorFixture('empty', static fn(): array => []),
                new ClosureCollectorFixture('missing', static fn(): null => null),
            ],
        );

        $snapshot = $coordinator->run(static fn() => $coordinator->capture(RequestSummary::create('once')));

        self::assertSame(
            ['once' => ['value' => ['captured' => 1]], 'empty' => []],
            $snapshot->panels,
            'An observed empty capture is stored; a missing one is absent.',
        );

        json_encode($snapshot, JSON_THROW_ON_ERROR);
        json_encode($snapshot, JSON_THROW_ON_ERROR);

        self::assertSame(
            1,
            $value->calls,
            'Serialization must be frozen at capture time.',
        );
    }
}

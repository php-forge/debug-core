<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Vite;

use PHPForge\Debug\Panel\Asset\ViteChunk;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSnapshot};
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the strict Vite component and snapshot persistence boundary.
 */
#[Group('panel')]
#[Group('vite')]
final class ViteSnapshotTest extends TestCase
{
    public function testHydrationPreservesUnknownMode(): void
    {
        $payload = self::componentPayload();
        $payload['mode'] = ViteComponent::MODE_UNKNOWN;

        $component = ViteComponent::fromArray($payload, '$');

        self::assertSame(
            ViteComponent::MODE_UNKNOWN,
            $component->mode,
            'Unavailable adapters must be able to persist an explicitly unknown runtime mode.',
        );
    }
    public function testHydrationRejectsAnInvalidImplementation(): void
    {
        $payload = self::componentPayload();
        $payload['implementation'] = 'unsupported';

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage("Invalid debug snapshot value at '$.implementation'");

        ViteComponent::fromArray($payload, '$');
    }

    public function testHydrationRejectsAnInvalidMode(): void
    {
        $payload = self::componentPayload();
        $payload['mode'] = 'preview';

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage("Invalid debug snapshot value at '$.mode'");

        ViteComponent::fromArray($payload, '$');
    }

    public function testHydrationRejectsANonBooleanNullableFlag(): void
    {
        $payload = self::componentPayload();
        $payload['includeViteClient'] = 1;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage("Invalid debug snapshot value at '$.includeViteClient'");

        ViteComponent::fromArray($payload, '$');
    }

    public function testHydrationRejectsANonStringEntrypoint(): void
    {
        $payload = self::componentPayload();
        $payload['entrypoints'] = ['resources/js/app.js', 42];

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage("Invalid debug snapshot value at '$.entrypoints[1]'");

        ViteComponent::fromArray($payload, '$');
    }

    public function testHydrationRejectsUndeclaredSnapshotFields(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage("Invalid debug snapshot value at '$.unexpected'");

        ViteSnapshot::fromArray(['components' => [], 'unexpected' => true]);
    }

    public function testSnapshotRoundTripsTypedComponentsAndChunks(): void
    {
        $snapshot = new ViteSnapshot(
            [
                new ViteComponent(
                    id: 'frontend',
                    class: 'PHPForge\Vite\Vite',
                    implementation: ViteComponent::IMPLEMENTATION_MODERN,
                    inspectionAvailable: true,
                    mode: ViteComponent::MODE_PRODUCTION,
                    entrypoints: ['resources/js/app.js'],
                    baseUrl: '/build',
                    devServerUrl: null,
                    manifestPath: '/app/public/build/.vite/manifest.json',
                    includeViteClient: null,
                    modulePreload: true,
                    chunks: [new ViteChunk('resources/js/app.js', 'assets/app.js', 1, 2, true)],
                ),
                new ViteComponent(
                    id: 'legacy',
                    class: 'yii\inertia\Vite',
                    implementation: ViteComponent::IMPLEMENTATION_LEGACY,
                    inspectionAvailable: true,
                    mode: ViteComponent::MODE_DEVELOPMENT,
                    entrypoints: ['resources/js/legacy.js'],
                    baseUrl: '@web/build',
                    devServerUrl: 'http://localhost:5173',
                    manifestPath: '',
                    includeViteClient: false,
                    modulePreload: null,
                    chunks: [],
                ),
            ],
        );

        $serialized = $snapshot->jsonSerialize();
        $hydrated = ViteSnapshot::fromArray($serialized, '$.panels.vite');
        $components = $hydrated->components();
        $component = $components[0] ?? self::fail('The modern Vite component must be hydrated.');
        $chunk = $component->chunks()[0] ?? self::fail('The modern Vite manifest chunk must be hydrated.');

        self::assertSame(
            $serialized,
            $hydrated->jsonSerialize(),
            'A valid Vite snapshot must round-trip without losing typed fields.',
        );
        self::assertCount(2, $components, 'Every serialized Vite component must be hydrated.');
        self::assertSame('assets/app.js', $chunk->file, 'Manifest chunks must hydrate as typed rows.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function componentPayload(): array
    {
        return [
            'id' => 'frontend',
            'class' => 'PHPForge\Vite\Vite',
            'implementation' => ViteComponent::IMPLEMENTATION_MODERN,
            'inspectionAvailable' => true,
            'mode' => ViteComponent::MODE_DEVELOPMENT,
            'entrypoints' => ['resources/js/app.js'],
            'baseUrl' => '',
            'devServerUrl' => 'http://localhost:5173',
            'manifestPath' => '',
            'includeViteClient' => true,
            'modulePreload' => null,
            'chunks' => [],
        ];
    }
}

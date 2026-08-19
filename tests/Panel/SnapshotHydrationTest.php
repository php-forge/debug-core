<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use PHPForge\Debug\Panel\Asset\AssetSnapshot;
use PHPForge\Debug\Panel\Config\ConfigSnapshot;
use PHPForge\Debug\Panel\Db\DbSnapshot;
use PHPForge\Debug\Panel\Event\EventSnapshot;
use PHPForge\Debug\Panel\Timeline\TimelineSnapshot;
use PHPForge\Debug\Panel\User\UserSnapshot;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for panel snapshots that hydrate typed rows from persisted payloads.
 */
#[Group('panel')]
final class SnapshotHydrationTest extends TestCase
{
    public function testAssetSnapshotHydratesBundlesAndViteManifest(): void
    {
        $payload = [
            'bundles' => [
                [
                    'name' => 'app\\Asset',
                    'sourcePath' => '@app/assets',
                    'basePath' => '/public/assets',
                    'baseUrl' => '/assets',
                    'css' => ['app.css'],
                    'js' => ['app.js'],
                    'depends' => ['yii\\web\\YiiAsset'],
                ],
            ],
            'vite' => [
                'baseUrl' => '/build',
                'devMode' => false,
                'devServerUrl' => null,
                'manifestPath' => '/public/build/manifest.json',
                'chunks' => [
                    [
                        'name' => 'src/main.js',
                        'file' => 'assets/main.js',
                        'cssCount' => 1,
                        'imports' => 2,
                        'isEntry' => true,
                    ],
                ],
            ],
        ];

        $snapshot = AssetSnapshot::fromArray($payload, '$.panels.asset');
        $bundle = $snapshot->bundles()[0] ?? self::fail('Expected one hydrated asset bundle.');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Asset payload must round-trip exactly.',
        );
        self::assertSame(
            $payload['bundles'][0],
            $bundle->jsonSerialize(),
            'Typed asset bundles must remain accessible.',
        );
        self::assertSame(
            $payload['vite'],
            $snapshot->vite()?->jsonSerialize(),
            'Typed Vite data must remain accessible.',
        );
    }

    public function testConfigurationSnapshotRoundTripsDynamicData(): void
    {
        $captured = ConfigSnapshot::capture(['debug' => true, 'aliases' => ['@app' => '/app']]);

        $payload = $captured->jsonSerialize();

        $snapshot = ConfigSnapshot::fromArray($payload, '$.panels.config');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Configuration payload must round-trip exactly.',
        );
        self::assertSame(
            ['debug' => true, 'aliases' => ['@app' => '/app']],
            $snapshot->data(),
            'Configuration values must be restored for display.',
        );
    }

    public function testDatabaseSnapshotHydratesQueryRows(): void
    {
        $payload = [
            'entries' => [
                [
                    'type' => 'SELECT',
                    'query' => 'SELECT 1',
                    'duration' => 1.5,
                    'trace' => [['file' => '/app/index.php', 'line' => 12]],
                    'traceHash' => 'trace-hash',
                    'timestamp' => 1_700_000_000_000.0,
                    'seq' => 0,
                    'duplicate' => 1,
                    'rows' => null,
                ],
            ],
        ];

        $snapshot = DbSnapshot::fromArray($payload, '$.panels.db');
        $query = $snapshot->entries()[0] ?? self::fail('Expected one hydrated query row.');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Database payload must round-trip exactly.',
        );
        self::assertSame(
            $payload['entries'][0],
            $query->jsonSerialize(),
            'Typed query rows must remain accessible.',
        );
    }

    public function testEventSnapshotHydratesEventRows(): void
    {
        $payload = [
            'entries' => [
                [
                    'time' => 1_700_000_000.5,
                    'name' => 'EVENT_AFTER_REQUEST',
                    'class' => 'app\\Event',
                    'isStatic' => '0',
                    'senderClass' => 'app\\Application',
                ],
            ],
        ];

        $snapshot = EventSnapshot::fromArray($payload, '$.panels.event');
        $event = $snapshot->entries()[0] ?? self::fail('Expected one hydrated event row.');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Event payload must round-trip exactly.',
        );
        self::assertSame(
            $payload['entries'][0],
            $event->jsonSerialize(),
            'Typed event rows must remain accessible.',
        );
    }

    public function testTimelineSnapshotRoundTripsMetrics(): void
    {
        $payload = ['start' => 100.1, 'end' => 100.3, 'memory' => 4_096];

        $snapshot = TimelineSnapshot::fromArray($payload, '$.panels.timeline');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Timeline metrics must round-trip exactly.',
        );
    }

    public function testUserSnapshotRoundTripsDynamicData(): void
    {
        $captured = UserSnapshot::capture(['id' => 42, 'roles' => ['admin']]);

        $payload = $captured->jsonSerialize();

        $snapshot = UserSnapshot::fromArray($payload, '$.panels.user');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'User payload must round-trip exactly.',
        );
        self::assertSame(
            ['id' => 42, 'roles' => ['admin']],
            $snapshot->data(),
            'User values must be restored for display.',
        );
    }
}

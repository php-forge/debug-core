<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore, StorageException};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Xepozz\InternalMocker\MockerState;

/**
 * Unit tests for {@see SnapshotStore} covering the JSON filesystem boundary: atomic writes, manifest locking, history
 * garbage collection, and the failure paths that keep a broken filesystem from corrupting a capture.
 */
#[Group('storage')]
final class SnapshotStoreTest extends TestCase
{
    private string $path = '';

    public function testClearRemovesSnapshotsAndManifest(): void
    {
        $store = $this->store();

        $summary = $this->summary('current', 1_700_000_000.0);

        $store->writeSnapshot(
            'current',
            new DebugSnapshot($summary, [], []),
        );
        $store->updateManifest($summary, 10);
        $store->clear();

        self::assertNull(
            $store->readSnapshot('current'),
            'Cleared snapshot must read as `null`.',
        );
        self::assertSame(
            [],
            $store->loadManifest(),
            'Cleared manifest must contain no entries.',
        );
    }

    public function testInvalidJsonIsRejectedWithoutExecutingPayloads(): void
    {
        mkdir($this->path, recursive: true);
        file_put_contents("{$this->path}/invalid.json", '{invalid');

        self::assertNull(
            $this->store()->readSnapshot('invalid'),
            'Malformed JSON must read as `null`.',
        );
    }

    public function testLoadManifestReturnsNothingWhenTheLockFileCannotBeOpened(): void
    {
        $store = $this->store();

        $store->updateManifest($this->summary('tag-1', 1_700_000_000.0), 10);

        MockerState::addCondition('PHPForge\\Debug\\Storage', 'fopen', [], false, true);

        self::assertSame(
            [],
            $store->loadManifest(),
            'An unopenable lock file must yield an empty manifest instead of throwing.',
        );
    }

    public function testReadRejectsTagThatEscapesTheStorageDirectory(): void
    {
        self::assertNull(
            $this->store()->readSnapshot('../outside'),
            'Unsafe read tag must yield `null`.',
        );
    }

    public function testReadSnapshotReturnsNullForATagThatWasNeverWritten(): void
    {
        mkdir($this->path, recursive: true);

        self::assertNull(
            $this->store()->readSnapshot('never-written'),
            'A missing snapshot file must read back as `null`.',
        );
    }

    public function testRemovesOrphanSnapshotsMissingFromTheManifest(): void
    {
        $store = $this->store();

        $store->writeSnapshot(
            'kept',
            new DebugSnapshot($this->summary('kept', 1_700_000_000.0), [], []),
        );

        file_put_contents("{$this->path}/orphan.json", '{}');

        for ($index = 0; $index < 13; $index++) {
            $store->updateManifest($this->summary("tag-{$index}", 1_700_000_000.0 + $index), 2);
        }

        self::assertFileDoesNotExist(
            "{$this->path}/orphan.json",
            'A snapshot with no manifest entry must be swept.',
        );
    }

    public function testSnapshotAndManifestRoundTripThroughJson(): void
    {
        $store = $this->store();
        $older = $this->summary('older', 1_700_000_000.0);
        $newer = $this->summary('newer', 1_700_000_001.0);

        $store->writeSnapshot(
            'newer',
            new DebugSnapshot($newer, ['panel' => ['value' => 1]], []),
        );
        $store->updateManifest($older, 10);
        $store->updateManifest($newer, 10);

        $snapshot = $store->readSnapshot('newer');

        self::assertNotNull(
            $snapshot,
            'Persisted snapshot must remain readable.',
        );
        self::assertSame(
            'newer',
            $snapshot->summary->tag,
            'Request tag must survive persistence.',
        );
        self::assertSame(
            ['value' => 1],
            $snapshot->panels['panel'] ?? null,
            'Panel payload must survive persistence.',
        );
        self::assertSame(
            ['newer', 'older'],
            array_keys($store->loadManifest()),
            'Manifest entries must be ordered newest first.',
        );
    }

    public function testThrowStorageExceptionForTagThatEscapesTheStorageDirectory(): void
    {
        $summary = $this->summary('../outside', 1_700_000_000.0);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Invalid debug snapshot tag: ../outside',
        );

        $this->store()->writeSnapshot(
            '../outside',
            new DebugSnapshot($summary, [], []),
        );
    }

    public function testThrowStorageExceptionWhenTheSnapshotCannotBeMovedIntoPlace(): void
    {
        MockerState::addCondition('PHPForge\\Debug\\Storage', 'rename', [], false, true);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to replace debug data file',
        );

        $this->store()->writeSnapshot(
            'blocked',
            new DebugSnapshot($this->summary('blocked', 1_700_000_000.0), [], []),
        );
    }

    public function testThrowStorageExceptionWhenTheTemporaryFileCannotBeCreated(): void
    {
        MockerState::addCondition('PHPForge\\Debug\\Storage', 'tempnam', [], false, true);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to write temporary debug data file',
        );

        $this->store()->writeSnapshot(
            'blocked',
            new DebugSnapshot($this->summary('blocked', 1_700_000_000.0), [], []),
        );
    }

    public function testThrowStorageExceptionWhenTheTemporaryFileCannotBeWritten(): void
    {
        MockerState::addCondition('PHPForge\\Debug\\Storage', 'file_put_contents', [], false, true);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to write temporary debug data file',
        );

        $this->store()->writeSnapshot(
            'blocked',
            new DebugSnapshot($this->summary('blocked', 1_700_000_000.0), [], []),
        );
    }

    public function testWritingJsonSnapshotRemovesLegacySerializedFiles(): void
    {
        mkdir($this->path, recursive: true);
        file_put_contents("{$this->path}/legacy.data", 'serialized payload');

        $summary = $this->summary('current', 1_700_000_000.0);

        $this->store()->writeSnapshot(
            'current',
            new DebugSnapshot($summary, [], []),
        );

        self::assertFileDoesNotExist(
            "{$this->path}/legacy.data",
            'Legacy serialized file must be removed.',
        );
        self::assertFileExists(
            "{$this->path}/current.json",
            'JSON snapshot file must be created.',
        );
    }

    /**
     * Creates an isolated temporary storage path.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/yii-debug-storage-' . uniqid('', true);
    }

    /**
     * Removes the temporary storage directory after each test.
     */
    protected function tearDown(): void
    {
        $this->removeDirectory($this->path);

        parent::tearDown();
    }

    /**
     * Removes a directory tree created by a test.
     *
     * @param string $path Directory path to remove.
     */
    private function removeDirectory(string $path): void
    {
        $files = glob($path . '/*');

        foreach ($files === false ? [] : $files as $file) {
            is_dir($file) ? $this->removeDirectory($file) : unlink($file);
        }

        if (is_dir($path)) {
            rmdir($path);
        }
    }

    /**
     * Creates a store for the isolated temporary path.
     *
     * @return SnapshotStore Store configured for the current test.
     */
    private function store(): SnapshotStore
    {
        return new SnapshotStore($this->path, 0o777, null);
    }

    /**
     * Creates representative request metadata for storage tests.
     *
     * @param string $tag Request tag.
     * @param float $time Request start timestamp.
     * @return RequestSummary Representative request metadata.
     */
    private function summary(string $tag, float $time): RequestSummary
    {
        return new RequestSummary(
            tag: $tag,
            url: 'https://example.test/',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: $time,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: null,
            peakMemory: null,
        );
    }
}

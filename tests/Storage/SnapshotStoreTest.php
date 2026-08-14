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
            new DebugSnapshot($summary, [], []),
            10,
        );

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
        self::assertFileExists(
            "{$this->path}/index.lock",
            'Clear must preserve the shared lock file.',
        );
    }

    public function testHistorySizeIsStrictMaximum(): void
    {
        $store = $this->store();

        for ($index = 0; $index < 3; $index++) {
            $summary = $this->summary("tag-{$index}", 1_700_000_000.0 + $index);

            $store->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                2,
            );
        }

        self::assertSame(
            ['tag-2', 'tag-1'],
            array_keys($store->loadManifest()),
            'Manifest must contain only the configured number of entries.',
        );
        self::assertFileDoesNotExist(
            "{$this->path}/tag-0.json",
            'Oldest snapshot must be removed at the retention boundary.',
        );
    }

    public function testHistorySizeZeroPersistsNoSnapshots(): void
    {
        $store = $this->store();

        $summary = $this->summary('current', 1_700_000_000.0);

        $removed = $store->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            0,
        );

        self::assertSame(
            [$summary],
            $removed,
            'Discarded request summary must be reported for dependent cleanup.',
        );
        self::assertSame(
            [],
            $store->loadManifest(),
            'Manifest must remain empty.',
        );
        self::assertFileDoesNotExist(
            "{$this->path}/current.json",
            'Discarded snapshot must not leave an orphan file.',
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

        $summary = $this->summary('tag-1', 1_700_000_000.0);

        $store->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'fopen',
            [],
            false,
            true,
        );

        self::assertSame(
            [],
            $store->loadManifest(),
            'An unopenable lock file must yield an empty manifest instead of throwing.',
        );
    }

    public function testLoadManifestReturnsNothingWhenTheSharedLockCannotBeAcquired(): void
    {
        $store = $this->store();

        $summary = $this->summary('tag-1', 1_700_000_000.0);

        $store->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'flock',
            [],
            false,
            true,
        );

        self::assertSame(
            [],
            $store->loadManifest(),
            'An unavailable shared lock must yield an empty manifest.',
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

        $kept = $this->summary('kept', 1_700_000_000.0);

        $store->writeSnapshot(
            new DebugSnapshot($kept, [], []),
            2,
        );

        file_put_contents("{$this->path}/orphan.json", '{}');

        for ($index = 0; $index < 13; $index++) {
            $summary = $this->summary("tag-{$index}", 1_700_000_000.0 + $index);

            $store->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                2,
            );
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
            new DebugSnapshot($older, [], []),
            10,
        );
        $store->writeSnapshot(
            new DebugSnapshot($newer, ['panel' => ['value' => 1]], []),
            10,
        );

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

    public function testThrowStorageExceptionForNegativeHistorySize(): void
    {
        $store = $this->store();

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Invalid debug history size: -1',
        );

        try {
            $summary = $this->summary('current', 1_700_000_000.0);

            $store->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                -1,
            );
        } finally {
            self::assertDirectoryDoesNotExist(
                $this->path,
                'Invalid history size must be rejected before storage initialization.',
            );
        }
    }

    public function testThrowStorageExceptionForReservedManifestTag(): void
    {
        $summary = $this->summary('index', 1_700_000_000.0);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Invalid debug snapshot tag: index',
        );

        $this->store()->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
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
            new DebugSnapshot($summary, [], []),
            10,
        );
    }

    public function testThrowStorageExceptionWhenClearCannotAcquireTheExclusiveLock(): void
    {
        $store = $this->store();

        $summary = $this->summary('current', 1_700_000_000.0);

        $store->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'flock',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to acquire debug data lock',
        );

        try {
            $store->clear();
        } finally {
            self::assertFileExists(
                "{$this->path}/current.json",
                'Failed lock acquisition must leave the snapshot intact.',
            );
        }
    }

    public function testThrowStorageExceptionWhenTheManifestCannotBeWrittenAtHistoryLimit(): void
    {
        $store = $this->store();

        $older = $this->summary('older', 1_700_000_000.0);
        $newer = $this->summary('newer', 1_700_000_001.0);

        $store->writeSnapshot(
            new DebugSnapshot($older, [], []),
            2,
        );
        $store->writeSnapshot(
            new DebugSnapshot($newer, [], []),
            2,
        );

        $temporaryFileCalls = 0;

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'tempnam',
            [$this->path, '.debug-'],
            static function (string $directory, string $prefix) use (&$temporaryFileCalls): string|false {
                return ++$temporaryFileCalls === 2 ? false : tempnam($directory, $prefix);
            },
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to write temporary debug data file',
        );

        try {
            $summary = $this->summary('blocked', 1_700_000_002.0);

            $store->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                2,
            );
        } finally {
            self::assertSame(
                ['newer', 'older'],
                array_keys($store->loadManifest()),
                'Failed manifest commit must preserve the previous manifest.',
            );
            self::assertNotNull(
                $store->readSnapshot('older'),
                'Oldest retained snapshot must remain readable.',
            );
        }
    }

    public function testThrowStorageExceptionWhenTheSnapshotCannotBeMovedIntoPlace(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'rename',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to replace debug data file',
        );

        $summary = $this->summary('blocked', 1_700_000_000.0);

        $this->store()->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );
    }

    public function testThrowStorageExceptionWhenTheTemporaryFileCannotBeCreated(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'tempnam',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to write temporary debug data file',
        );

        $summary = $this->summary('blocked', 1_700_000_000.0);

        $this->store()->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );
    }

    public function testThrowStorageExceptionWhenTheTemporaryFileCannotBeWritten(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'file_put_contents',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to write temporary debug data file',
        );

        $summary = $this->summary('blocked', 1_700_000_000.0);

        $this->store()->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );
    }

    public function testThrowStorageExceptionWhenWriteCannotAcquireTheExclusiveLock(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'flock',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to acquire debug data lock',
        );

        try {
            $summary = $this->summary('current', 1_700_000_000.0);

            $this->store()->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                10,
            );
        } finally {
            self::assertFileDoesNotExist(
                "{$this->path}/current.json",
                'Failed lock acquisition must not write the snapshot.',
            );
            self::assertFileDoesNotExist(
                "{$this->path}/index.json",
                'Failed lock acquisition must not write the manifest.',
            );
        }
    }

    public function testThrowStorageExceptionWhenWriteCannotOpenTheLockFile(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'fopen',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            'Unable to open debug data lock file',
        );

        try {
            $summary = $this->summary('current', 1_700_000_000.0);

            $this->store()->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                10,
            );
        } finally {
            self::assertFileDoesNotExist(
                "{$this->path}/current.json",
                'An unopenable lock file must prevent snapshot persistence.',
            );
        }
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

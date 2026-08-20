<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use PHPForge\Debug\Storage\{DebugSnapshot, RequestSummary, SnapshotStore, StorageException};
use PHPUnit\Framework\Attributes\{Group, TestWith};
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

    public function testClearInitializesEmptyStorage(): void
    {
        $this->store()->clear();

        self::assertDirectoryExists(
            $this->path,
            'Storage directory must be created before locking.',
        );
        self::assertFileExists(
            "{$this->path}/index.lock",
            'Empty storage must retain its lock file.',
        );
    }

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

    public function testClearThrowsWhenAStoredFileCannotBeRemoved(): void
    {
        $store = $this->store();

        $store->writeSnapshot(
            new DebugSnapshot($this->summary('current', 1_700_000_000.0), [], []),
            10,
        );

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'unlink',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            "Unable to remove debug data file: {$this->path}/current.json",
        );

        $store->clear();
    }

    public function testCommittedTransactionJournalKeepsCommittedData(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 1.0), [], []), 10);
        file_put_contents(
            "{$this->path}/.debug-transaction.json",
            json_encode(
                [
                    'version' => 1,
                    'state' => 'committed',
                    'tag' => 'current',
                    'snapshotBefore' => null,
                    'manifestBefore' => null,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        self::assertNotNull($store->readSnapshot('current'), 'Committed transaction data must remain visible.');
        self::assertFileDoesNotExist(
            "{$this->path}/.debug-transaction.json",
            'Committed journal must be cleaned during recovery.',
        );
    }

    public function testEmptyManifestRebuildsValidSnapshotHistory(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('older', 1.0), [], []), 10);
        file_put_contents("{$this->path}/index.json", '');

        $store->writeSnapshot(new DebugSnapshot($this->summary('newer', 2.0), [], []), 10);

        self::assertSame(
            ['newer', 'older'],
            array_keys($store->loadManifest()),
            'An empty index file must rebuild from valid snapshot envelopes instead of erasing history.',
        );
        self::assertNotNull($store->readSnapshot('older'), 'A valid snapshot must survive empty-index recovery.');
    }


    public function testGarbageCollectionReportsEveryRemovedSummary(): void
    {
        $store = $this->store();

        for ($index = 0; $index < 3; $index++) {
            $summary = $this->summary("tag-{$index}", 1_700_000_000.0 + $index);

            $store->writeSnapshot(
                new DebugSnapshot($summary, [], []),
                10,
            );
        }

        $current = $this->summary('current', 1_700_000_003.0);

        $removed = $store->writeSnapshot(
            new DebugSnapshot($current, [], []),
            1,
        );

        self::assertSame(
            ['tag-0', 'tag-1', 'tag-2'],
            array_map(static fn(RequestSummary $summary): string => $summary->tag, $removed),
            'Eviction report must include every discarded summary.',
        );
        self::assertSame(
            ['current'],
            array_keys($store->loadManifest()),
            'Manifest must retain only the newest request.',
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

    public function testInvalidManifestRebuildsValidSnapshotHistory(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('oldest', 1.0), [], []), 10);
        $store->writeSnapshot(new DebugSnapshot($this->summary('older', 2.0), [], []), 10);
        file_put_contents("{$this->path}/index.json", '{invalid');

        $store->writeSnapshot(new DebugSnapshot($this->summary('newer', 3.0), [], []), 10);

        self::assertSame(
            ['newer', 'older', 'oldest'],
            array_keys($store->loadManifest()),
            'A corrupt index must be rebuilt from valid snapshot envelopes before appending new history.',
        );
        self::assertNotNull($store->readSnapshot('older'), 'A valid snapshot must survive index recovery.');
    }

    public function testInvalidManifestResetsStaleSnapshots(): void
    {
        mkdir($this->path, recursive: true);

        file_put_contents("{$this->path}/index.json", '{invalid');
        file_put_contents("{$this->path}/stale.json", '{}');

        $summary = $this->summary('current', 1_700_000_000.0);

        $this->store()->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );

        self::assertFileDoesNotExist(
            "{$this->path}/stale.json",
            'Reset manifest must discard snapshots it cannot reference.',
        );
        self::assertFileExists(
            "{$this->path}/current.json",
            'Replacement snapshot must remain stored.',
        );
        self::assertSame(
            ['current'],
            array_keys($this->store()->loadManifest()),
            'Replacement manifest must contain the new request.',
        );
    }

    public function testInvalidManifestSkipsInvalidAndEmptySnapshotFiles(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('valid', 1.0), [], []), 10);
        file_put_contents("{$this->path}/index.json", '{invalid');
        file_put_contents("{$this->path}/7.json", '{}');
        file_put_contents("{$this->path}/empty.json", '');

        $store->writeSnapshot(new DebugSnapshot($this->summary('newer', 2.0), [], []), 10);

        self::assertSame(['newer', 'valid'], array_keys($store->loadManifest()), 'Only valid envelopes may rebuild.');
        self::assertFileDoesNotExist("{$this->path}/7.json", 'Invalid tag file must be removed during reconciliation.');
        self::assertFileDoesNotExist("{$this->path}/empty.json", 'Empty snapshot must be removed during reconciliation.');
    }

    public function testInvalidTransactionJournalMakesReadsFailClosed(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 1.0), [], []), 10);
        file_put_contents("{$this->path}/.debug-transaction.json", '{invalid');

        self::assertNull($store->readSnapshot('current'), 'Malformed transaction state must fail closed.');
        self::assertSame([], $store->loadManifest(), 'Malformed transaction state must not expose a partial manifest.');
    }

    public function testInvalidTransactionJournalShapeAndStateFailClosed(): void
    {
        mkdir($this->path, recursive: true);
        touch("{$this->path}/index.lock");

        file_put_contents(
            "{$this->path}/.debug-transaction.json",
            json_encode(['version' => 99], JSON_THROW_ON_ERROR),
        );
        self::assertSame([], $this->store()->loadManifest(), 'Invalid journal shape must fail closed.');

        file_put_contents(
            "{$this->path}/.debug-transaction.json",
            json_encode(
                [
                    'version' => 1,
                    'state' => 'unknown',
                    'tag' => 'current',
                    'snapshotBefore' => null,
                    'manifestBefore' => null,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
        self::assertSame([], $this->store()->loadManifest(), 'Unknown journal state must fail closed.');
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

    public function testManifestReadResultDistinguishesEmptyStoreFromCorruptManifest(): void
    {
        $store = $this->store();

        $empty = $store->loadManifestResult();

        self::assertSame([], $empty->entries, 'A store that does not exist yet must have no entries.');
        self::assertNull($empty->error, 'A store that does not exist yet must not be reported as a read failure.');

        mkdir($this->path, recursive: true);
        file_put_contents("{$this->path}/index.json", '{invalid');

        $corrupt = $store->loadManifestResult();

        self::assertSame([], $corrupt->entries, 'A corrupt manifest must remain fail-closed.');
        self::assertNotNull($corrupt->error, 'A corrupt manifest must expose a diagnostic through the additive API.');
        self::assertNotNull($corrupt->error->getPrevious(), 'The decoding failure must remain available for logging.');
    }

    public function testManifestReadResultReportsEmptyAndUnreadableManifestFiles(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX read permissions are not portable to Windows.');
        }

        mkdir($this->path, recursive: true);
        file_put_contents("{$this->path}/index.json", '');

        $empty = $this->store()->loadManifestResult();

        self::assertSame(
            "Debug manifest is empty: {$this->path}/index.json",
            $empty->error?->getMessage(),
            'An empty index file must be distinguishable from an empty manifest.',
        );

        chmod("{$this->path}/index.json", 0o000);

        try {
            self::assertSame(
                "Unable to read debug manifest: {$this->path}/index.json",
                $this->store()->loadManifestResult()->error?->getMessage(),
                'An unreadable index file must expose a filesystem diagnostic.',
            );
        } finally {
            chmod("{$this->path}/index.json", 0o600);
        }
    }

    public function testManifestReadResultReportsInvalidPathAndLockFailure(): void
    {
        file_put_contents($this->path, 'not a directory');

        self::assertSame(
            "Debug data path is not a directory: {$this->path}",
            $this->store()->loadManifestResult()->error?->getMessage(),
            'A non-directory storage path must be observable.',
        );

        unlink($this->path);
        mkdir($this->path, recursive: true);

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'fopen',
            [],
            false,
            true,
        );

        self::assertStringContainsString(
            'Unable to open debug data lock file',
            $this->store()->loadManifestResult()->error->getMessage(),
            'A lock failure must be observable instead of looking like an empty store.',
        );
    }

    public function testPreparedFirstWriteTransactionRemovesPartialFiles(): void
    {
        mkdir($this->path, recursive: true);
        touch("{$this->path}/index.lock");
        file_put_contents(
            "{$this->path}/current.json",
            json_encode(new DebugSnapshot($this->summary('current', 1.0), [], []), JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            "{$this->path}/.debug-transaction.json",
            json_encode(
                [
                    'version' => 1,
                    'state' => 'prepared',
                    'tag' => 'current',
                    'snapshotBefore' => null,
                    'manifestBefore' => null,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        self::assertNull($this->store()->readSnapshot('current'), 'Interrupted first write must roll back to no data.');
        self::assertFileDoesNotExist("{$this->path}/current.json", 'Partial first snapshot must be removed.');
        self::assertFileDoesNotExist("{$this->path}/index.json", 'Partial first manifest must be removed.');
    }

    public function testPreparedTransactionIsRolledBackBeforeRead(): void
    {
        $store = $this->store();
        $oldSnapshot = new DebugSnapshot($this->summary('current', 1.0), ['panel' => ['value' => 'old']], []);
        $store->writeSnapshot($oldSnapshot, 10);
        $snapshotBefore = file_get_contents("{$this->path}/current.json");
        $manifestBefore = file_get_contents("{$this->path}/index.json");

        self::assertIsString($snapshotBefore, 'Old snapshot fixture must be readable.');
        self::assertIsString($manifestBefore, 'Old manifest fixture must be readable.');

        file_put_contents(
            "{$this->path}/current.json",
            json_encode(
                new DebugSnapshot($this->summary('current', 2.0), ['panel' => ['value' => 'partial']], []),
                JSON_THROW_ON_ERROR,
            ),
        );
        file_put_contents(
            "{$this->path}/.debug-transaction.json",
            json_encode(
                [
                    'version' => 1,
                    'state' => 'prepared',
                    'tag' => 'current',
                    'snapshotBefore' => $snapshotBefore,
                    'manifestBefore' => $manifestBefore,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );

        $recovered = $store->readSnapshot('current');

        self::assertNotNull($recovered, 'Prepared transaction must recover the previous snapshot.');
        self::assertSame(['value' => 'old'], $recovered->panels['panel'] ?? null, 'Recovery must restore old detail.');
        self::assertFileDoesNotExist(
            "{$this->path}/.debug-transaction.json",
            'Successful recovery must remove the prepared journal.',
        );
    }

    public function testPreparedTransactionKeepsJournalWhenRollbackDeletionFails(): void
    {
        mkdir($this->path, recursive: true);
        touch("{$this->path}/index.lock");
        file_put_contents(
            "{$this->path}/current.json",
            json_encode(new DebugSnapshot($this->summary('current', 1.0), [], []), JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            "{$this->path}/.debug-transaction.json",
            json_encode(
                [
                    'version' => 1,
                    'state' => 'prepared',
                    'tag' => 'current',
                    'snapshotBefore' => null,
                    'manifestBefore' => null,
                ],
                JSON_THROW_ON_ERROR,
            ),
        );
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'unlink',
            ["{$this->path}/current.json"],
            false,
            true,
        );

        $result = $this->store()->readSnapshotResult('current');

        self::assertSame(
            "Unable to roll back debug data file: {$this->path}/current.json",
            $result->error?->getMessage(),
            'A rollback deletion failure must be observable.',
        );
        self::assertFileExists(
            "{$this->path}/.debug-transaction.json",
            'The prepared journal must remain available for a later recovery retry.',
        );
        self::assertFileExists("{$this->path}/current.json", 'Failed rollback must not pretend partial data vanished.');
    }

    public function testReadRejectsSnapshotWhoseEnvelopeTagDoesNotMatchFilename(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('source', 1.0), [], []), 10);
        copy("{$this->path}/source.json", "{$this->path}/renamed.json");

        self::assertNull(
            $store->readSnapshot('renamed'),
            'A renamed snapshot must be rejected when its envelope tag does not match the requested file.',
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

    public function testReadSnapshotReturnsNullWhenStorageLockCannotBeOpened(): void
    {
        self::assertNull(
            $this->store()->readSnapshot('never-written'),
            'A missing storage directory must fail closed without creating a lock.',
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
        self::assertFileExists(
            "{$this->path}/tag-12.json",
            'Newest retained snapshot must survive stale-file cleanup.',
        );
        self::assertFileExists(
            "{$this->path}/tag-11.json",
            'Second retained snapshot must survive stale-file cleanup.',
        );
    }

    public function testRetriesOrphanSnapshotCleanupAfterEverySuccessfulCommit(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('kept', 1.0), [], []), 10);
        file_put_contents("{$this->path}/orphan.json", '{}');

        $store->writeSnapshot(new DebugSnapshot($this->summary('newer', 2.0), [], []), 10);

        self::assertFileDoesNotExist(
            "{$this->path}/orphan.json",
            'A stale-file deletion retry must not depend on another retention eviction.',
        );
    }

    public function testRewrittenSnapshotBecomesNewestManifestEntry(): void
    {
        $store = $this->store();

        $store->writeSnapshot(
            new DebugSnapshot($this->summary('first', 1_700_000_000.0), [], []),
            2,
        );
        $store->writeSnapshot(
            new DebugSnapshot($this->summary('second', 1_700_000_001.0), [], []),
            2,
        );
        $store->writeSnapshot(
            new DebugSnapshot($this->summary('first', 1_700_000_002.0), [], []),
            2,
        );

        $removed = $store->writeSnapshot(
            new DebugSnapshot($this->summary('third', 1_700_000_003.0), [], []),
            2,
        );

        self::assertSame(
            ['second'],
            array_map(static fn(RequestSummary $summary): string => $summary->tag, $removed),
            'Eviction must target the least recently written snapshot.',
        );
        self::assertSame(
            ['third', 'first'],
            array_keys($store->loadManifest()),
            'Manifest order must reflect the latest write for each tag.',
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

    public function testSnapshotReadResultDistinguishesMissingInvalidAndCorruptSnapshots(): void
    {
        $store = $this->store();

        $missing = $store->readSnapshotResult('missing');

        self::assertNull($missing->snapshot, 'A missing snapshot must have no value.');
        self::assertNull($missing->error, 'A missing snapshot in an empty store must not be a read error.');

        $invalid = $store->readSnapshotResult('../outside');

        self::assertNull($invalid->snapshot, 'An invalid tag must have no value.');
        self::assertSame(
            'Invalid debug snapshot tag: ../outside',
            $invalid->error?->getMessage(),
            'An invalid caller tag must be observable through the additive API.',
        );

        mkdir($this->path, recursive: true);
        file_put_contents("{$this->path}/corrupt.json", '{invalid');

        $corrupt = $store->readSnapshotResult('corrupt');

        self::assertNull($corrupt->snapshot, 'A corrupt snapshot must remain fail-closed.');
        self::assertNotNull($corrupt->error, 'A corrupt snapshot must expose a diagnostic.');
        self::assertNotNull($corrupt->error->getPrevious(), 'The hydration failure must remain available for logging.');
    }

    public function testSnapshotReadResultReportsEmptyMismatchAndUnreadableFiles(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX read permissions are not portable to Windows.');
        }

        mkdir($this->path, recursive: true);
        file_put_contents("{$this->path}/empty.json", '');

        self::assertSame(
            "Debug snapshot is empty: {$this->path}/empty.json",
            $this->store()->readSnapshotResult('empty')->error?->getMessage(),
            'An empty snapshot file must expose a diagnostic.',
        );

        $snapshot = new DebugSnapshot($this->summary('source', 1.0), [], []);
        file_put_contents("{$this->path}/renamed.json", json_encode($snapshot, JSON_THROW_ON_ERROR));

        self::assertSame(
            "Debug snapshot tag does not match its filename: {$this->path}/renamed.json",
            $this->store()->readSnapshotResult('renamed')->error?->getMessage(),
            'A renamed envelope must expose the integrity failure.',
        );

        file_put_contents("{$this->path}/unreadable.json", '{}');
        chmod("{$this->path}/unreadable.json", 0o000);

        try {
            self::assertSame(
                "Unable to read debug snapshot: {$this->path}/unreadable.json",
                $this->store()->readSnapshotResult('unreadable')->error?->getMessage(),
                'An unreadable snapshot must expose a filesystem diagnostic.',
            );
        } finally {
            chmod("{$this->path}/unreadable.json", 0o600);
        }
    }

    public function testSnapshotReadResultReportsInvalidPathAndLockFailure(): void
    {
        file_put_contents($this->path, 'not a directory');

        self::assertSame(
            "Debug data path is not a directory: {$this->path}",
            $this->store()->readSnapshotResult('current')->error?->getMessage(),
            'A non-directory storage path must be observable.',
        );

        unlink($this->path);
        mkdir($this->path, recursive: true);

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'fopen',
            [],
            false,
            true,
        );

        self::assertStringContainsString(
            'Unable to open debug data lock file',
            $this->store()->readSnapshotResult('current')->error->getMessage(),
            'A snapshot lock failure must be observable.',
        );
    }

    public function testSnapshotReadResultReturnsPersistedSnapshotWithoutAnError(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 1.0), [], []), 10);

        $result = $store->readSnapshotResult('current');

        self::assertSame('current', $result->snapshot?->summary->tag, 'A valid snapshot must remain available.');
        self::assertNull($result->error, 'A valid snapshot must not produce a read diagnostic.');
    }

    /**
     * @param string $tag Leading-dot tag.
     */
    #[TestWith(['.'])]
    #[TestWith(['.hidden'])]
    public function testThrowStorageExceptionForLeadingDotTag(string $tag): void
    {
        $summary = $this->summary($tag, 1_700_000_000.0);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot tag: {$tag}",
        );

        $this->store()->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
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

    /**
     * @param string $tag Numeric tag.
     */
    #[TestWith(['0'])]
    #[TestWith(['7'])]
    public function testThrowStorageExceptionForNumericTag(string $tag): void
    {
        $summary = $this->summary($tag, 1_700_000_000.0);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot tag: {$tag}",
        );

        $this->store()->writeSnapshot(
            new DebugSnapshot($summary, [], []),
            10,
        );
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

    public function testThrowStorageExceptionWhenDirectoryModeCannotBeApplied(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'chmod',
            [$this->path, 0o700],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unable to apply debug data directory mode');

        (new SnapshotStore($this->path, 0o700, 0o600))->writeSnapshot(
            new DebugSnapshot($this->summary('current', 1.0), [], []),
            10,
        );
    }

    public function testThrowStorageExceptionWhenFileModeCannotBeApplied(): void
    {
        mkdir($this->path, recursive: true);

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'chmod',
            [],
            false,
            true,
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unable to apply debug data file mode');

        (new SnapshotStore($this->path, 0o700, 0o600))->writeSnapshot(
            new DebugSnapshot($this->summary('current', 1.0), [], []),
            10,
        );
    }

    public function testThrowStorageExceptionWhenStoragePathCannotBeCreated(): void
    {
        file_put_contents($this->path, 'not a directory');

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage(
            "Unable to create debug data directory: {$this->path}",
        );

        try {
            $this->store()->clear();
        } finally {
            unlink($this->path);
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
                return ++$temporaryFileCalls === 3 ? false : tempnam($directory, $prefix);
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
            self::assertFileDoesNotExist(
                "{$this->path}/blocked.json",
                'Failed manifest commit must roll back the newly written snapshot.',
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

    public function testWriteAppliesConfiguredFileMode(): void
    {
        $store = new SnapshotStore($this->path, 0o777, 0o600);

        $store->writeSnapshot(
            new DebugSnapshot($this->summary('current', 1_700_000_000.0), [], []),
            10,
        );

        $modes = [];

        foreach (MockerState::getTraces('PHPForge\Debug\Storage', 'chmod') as $trace) {
            self::assertIsArray(
                $trace,
                'Each chmod trace must expose its arguments.',
            );

            $arguments = $trace['arguments'] ?? null;

            self::assertIsArray(
                $arguments,
                'Each chmod trace must expose an argument list.',
            );

            $mode = $arguments[1] ?? null;

            self::assertIsInt(
                $mode,
                'Each chmod call must receive an integer mode.',
            );

            $modes[] = $mode;
        }

        self::assertSame(
            [0o777, 0o600, 0o600, 0o600, 0o600],
            $modes,
            'Configured modes must cover the directory, transaction, snapshot, manifest, and commit-marker files.',
        );
    }

    public function testWritePreservesPrimaryFailureWhenImmediateRollbackAlsoFails(): void
    {
        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 1.0), [], []), 10);
        $temporaryFileCalls = 0;

        MockerState::addCondition(
            'PHPForge\\Debug\\Storage',
            'tempnam',
            [$this->path, '.debug-'],
            static function (string $directory, string $prefix) use (&$temporaryFileCalls): string|false {
                $call = ++$temporaryFileCalls;

                return $call === 3 || $call === 4 ? false : tempnam($directory, $prefix);
            },
        );

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unable to write temporary debug data file');

        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 2.0), [], []), 10);
    }

    public function testWriteRejectsUnreadableExistingTransactionTarget(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX read permissions are not portable to Windows.');
        }

        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 1.0), [], []), 10);
        chmod("{$this->path}/current.json", 0o000);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unable to read debug data file');

        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 2.0), [], []), 10);
    }

    public function testWriteRejectsUnreadableManifest(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('POSIX read permissions are not portable to Windows.');
        }

        $store = $this->store();
        $store->writeSnapshot(new DebugSnapshot($this->summary('current', 1.0), [], []), 10);
        chmod("{$this->path}/index.json", 0o000);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Unable to read debug manifest');

        try {
            $store->writeSnapshot(new DebugSnapshot($this->summary('newer', 2.0), [], []), 10);
        } finally {
            chmod("{$this->path}/index.json", 0o600);
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

        if (is_file($path . '/.debug-transaction.json')) {
            unlink($path . '/.debug-transaction.json');
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

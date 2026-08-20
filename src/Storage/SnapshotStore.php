<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonException;
use Throwable;

use function array_key_exists;
use function array_keys;
use function array_reverse;
use function count;
use function fclose;
use function file_get_contents;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function pathinfo;
use function preg_match;
use function usort;

/**
 * Provides JSON filesystem storage, manifest locking, atomic writes, and snapshot garbage collection.
 */
final class SnapshotStore
{
    private const string INDEX_FILE = 'index.json';
    private const int JSON_FLAGS = JSON_THROW_ON_ERROR
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRESERVE_ZERO_FRACTION;
    private const string LOCK_FILE = 'index.lock';
    private const string TRANSACTION_FILE = '.debug-transaction.json';
    private const int TRANSACTION_VERSION = 1;

    /**
     * Creates a filesystem store with directory and file permission modes.
     *
     * @param string $path Storage directory path.
     * @param int $dirMode Directory permissions used when creating the storage path.
     * @param int|null $fileMode File permissions or `null` to preserve the system default.
     */
    public function __construct(
        private readonly string $path,
        private readonly int $dirMode,
        private readonly int|null $fileMode,
    ) {}

    /**
     * Removes stored manifests, snapshots, and temporary files.
     *
     * Usage example:
     *
     * ```php
     * $store = new \PHPForge\Debug\Storage\SnapshotStore(sys_get_temp_dir() . '/debug', 0o775, null);
     * $store->clear();
     * ```
     */
    public function clear(): void
    {
        $this->initialize();

        $lock = $this->acquireLock(LOCK_EX);

        try {
            $patterns = [
                "{$this->path}/*.json",
                "{$this->path}/.debug-*",
            ];

            foreach ($patterns as $pattern) {
                $files = glob($pattern);

                foreach ($files === false ? [] : $files as $file) {
                    if (is_file($file) && !@unlink($file)) {
                        throw new StorageException(
                            "Unable to remove debug data file: {$file}",
                        );
                    }
                }
            }
        } finally {
            fclose($lock);
        }
    }

    /**
     * Returns manifest entries ordered from newest to oldest.
     *
     * Usage example:
     *
     * ```php
     * $store = new \PHPForge\Debug\Storage\SnapshotStore(sys_get_temp_dir() . '/debug', 0o775, null);
     * $entries = $store->loadManifest();
     * ```
     *
     * @return array<string, RequestSummary> Newest entries first.
     */
    public function loadManifest(): array
    {
        return $this->loadManifestResult()->entries;
    }

    /**
     * Returns manifest entries and preserves any read diagnostic for observable error handling.
     *
     * @return ManifestReadResult Newest entries first plus an optional storage failure.
     */
    public function loadManifestResult(): ManifestReadResult
    {
        if (!is_dir($this->path)) {
            $error = is_file($this->path)
                ? new StorageException("Debug data path is not a directory: {$this->path}")
                : null;

            return new ManifestReadResult([], $error);
        }

        try {
            $lock = $this->acquireLock(LOCK_EX);
        } catch (StorageException $error) {
            return new ManifestReadResult([], $error);
        }

        try {
            $this->recoverTransaction();

            $file = $this->indexFile();

            if (!is_file($file)) {
                return new ManifestReadResult([], null);
            }

            $raw = @file_get_contents($file);

            if ($raw === false) {
                throw new StorageException(
                    "Unable to read debug manifest: {$file}",
                );
            }

            if ($raw === '') {
                throw new StorageException(
                    "Debug manifest is empty: {$file}",
                );
            }

            $manifest = Manifest::fromArray(self::decode($raw));

            return new ManifestReadResult(array_reverse($manifest->entries, true), null);
        } catch (Throwable $failure) {
            $error = $failure instanceof StorageException
                ? $failure
                : new StorageException(
                    'Unable to read debug manifest.',
                    0,
                    $failure,
                );

            return new ManifestReadResult([], $error);
        } finally {
            fclose($lock);
        }
    }

    /**
     * Returns a stored snapshot or `null` when the tag or persisted payload is invalid.
     *
     * Usage example:
     *
     * ```php
     * $store = new \PHPForge\Debug\Storage\SnapshotStore(sys_get_temp_dir() . '/debug', 0o775, null);
     * $snapshot = $store->readSnapshot('request-1');
     * ```
     *
     * @param string $tag Snapshot tag.
     *
     * @return DebugSnapshot|null Hydrated snapshot or `null` when the stored value is unavailable or invalid.
     */
    public function readSnapshot(string $tag): DebugSnapshot|null
    {
        return $this->readSnapshotResult($tag)->snapshot;
    }

    /**
     * Returns a stored snapshot and preserves any read diagnostic for observable error handling.
     *
     * @param string $tag Snapshot tag.
     *
     * @return SnapshotReadResult Snapshot, missing state, or storage failure.
     */
    public function readSnapshotResult(string $tag): SnapshotReadResult
    {
        if (!self::isValidTag($tag)) {
            return new SnapshotReadResult(null, new StorageException("Invalid debug snapshot tag: {$tag}"));
        }

        if (!is_dir($this->path)) {
            $error = is_file($this->path)
                ? new StorageException(
                    "Debug data path is not a directory: {$this->path}",
                )
                : null;

            return new SnapshotReadResult(null, $error);
        }

        try {
            $lock = $this->acquireLock(LOCK_EX);
        } catch (StorageException $error) {
            return new SnapshotReadResult(null, $error);
        }

        try {
            $this->recoverTransaction();

            $file = $this->snapshotFile($tag);

            if (!is_file($file)) {
                return new SnapshotReadResult(null, null);
            }

            $raw = @file_get_contents($file);

            if ($raw === false) {
                throw new StorageException(
                    "Unable to read debug snapshot: {$file}",
                );
            }

            if ($raw === '') {
                throw new StorageException(
                    "Debug snapshot is empty: {$file}",
                );
            }

            $snapshot = DebugSnapshot::fromArray(self::decode($raw));

            if ($snapshot->summary->tag !== $tag) {
                throw new StorageException(
                    "Debug snapshot tag does not match its filename: {$file}",
                );
            }

            return new SnapshotReadResult($snapshot, null);
        } catch (Throwable $failure) {
            $error = $failure instanceof StorageException
                ? $failure
                : new StorageException(
                    "Unable to read debug snapshot: {$tag}",
                    0,
                    $failure,
                );

            return new SnapshotReadResult(null, $error);
        } finally {
            fclose($lock);
        }
    }

    /**
     * Writes a snapshot, updates the manifest, and runs garbage collection under one exclusive lock.
     *
     * Usage example:
     *
     * ```php
     * $removed = $store->writeSnapshot($snapshot, 50);
     * ```
     *
     * @param DebugSnapshot $snapshot Snapshot to persist.
     * @param int $historySize Maximum number of retained entries.
     *
     * @return list<RequestSummary> Entries evicted from the manifest.
     */
    public function writeSnapshot(DebugSnapshot $snapshot, int $historySize): array
    {
        self::assertValidHistorySize($historySize);

        $tag = $snapshot->summary->tag;

        $snapshotFile = $this->snapshotFile($tag);
        $snapshotJson = self::encode($snapshot);

        $this->initialize();

        $lock = $this->acquireLock(LOCK_EX);

        try {
            $this->recoverTransaction();

            $manifest = $this->readManifestFile();

            $entries = ($manifest ?? $this->rebuildManifest())->entries;

            unset($entries[$tag]);

            $entries[$tag] = $snapshot->summary;

            $removed = $this->collectGarbage($entries, $historySize);

            $transaction = [
                'version' => self::TRANSACTION_VERSION,
                'state' => 'prepared',
                'tag' => $tag,
                'snapshotBefore' => $this->readExistingFile($snapshotFile),
                'manifestBefore' => $this->readExistingFile($this->indexFile()),
            ];

            $this->atomicWrite($this->transactionFile(), self::encode($transaction));

            try {
                if ($entries !== []) {
                    $this->atomicWrite($snapshotFile, $snapshotJson);
                }

                $this->atomicWrite($this->indexFile(), self::encode(new Manifest($entries)));

                $transaction['state'] = 'committed';

                $this->atomicWrite($this->transactionFile(), self::encode($transaction));

                @unlink($this->transactionFile());
            } catch (Throwable $failure) {
                try {
                    $this->recoverTransaction();
                } catch (Throwable) {
                    // Preserve the write failure; the journal remains available for a later recovery attempt.
                }

                throw $failure;
            }

            $this->removeStaleSnapshots($entries);

            return $removed;
        } finally {
            fclose($lock);
        }
    }

    /**
     * Opens and acquires a checked filesystem lock.
     *
     * @param int<0, 7> $operation Lock operation passed to {@see flock()}.
     *
     * @return resource Acquired lock handle.
     */
    private function acquireLock(int $operation): mixed
    {
        $lockFile = $this->lockFile();

        $lock = @fopen($lockFile, 'c+');

        if ($lock === false) {
            throw new StorageException(
                "Unable to open debug data lock file: {$lockFile}",
            );
        }

        if (!@flock($lock, $operation)) {
            fclose($lock);

            throw new StorageException(
                "Unable to acquire debug data lock: {$lockFile}",
            );
        }

        return $lock;
    }

    /**
     * Validates a manifest history size.
     *
     * @param int $historySize Maximum number of retained entries.
     */
    private static function assertValidHistorySize(int $historySize): void
    {
        if ($historySize < 0) {
            throw new StorageException(
                "Invalid debug history size: {$historySize}",
            );
        }
    }

    /**
     * Replaces a target file through a temporary file in the same directory.
     *
     * @param string $file Target file path.
     * @param string $contents JSON contents to write.
     */
    private function atomicWrite(string $file, string $contents): void
    {
        $temporary = @tempnam($this->path, '.debug-');

        if ($temporary === false) {
            throw new StorageException(
                "Unable to write temporary debug data file for: {$file}",
            );
        }

        if (file_put_contents($temporary, $contents) === false) {
            @unlink($temporary);

            throw new StorageException(
                "Unable to write temporary debug data file for: {$file}",
            );
        }

        if ($this->fileMode !== null) {
            if (!@chmod($temporary, $this->fileMode)) {
                @unlink($temporary);

                throw new StorageException(
                    "Unable to apply debug data file mode for: {$file}",
                );
            }
        }

        if (!@rename($temporary, $file)) {
            @unlink($temporary);

            throw new StorageException(
                "Unable to replace debug data file: {$file}",
            );
        }
    }

    /**
     * Removes expired manifest entries.
     *
     * @param array<string, RequestSummary> $entries Manifest entries, updated in place.
     * @param int $historySize Maximum number of retained entries.
     *
     * @return list<RequestSummary> Removed manifest entries.
     */
    private function collectGarbage(array &$entries, int $historySize): array
    {
        if (count($entries) <= $historySize) {
            return [];
        }

        $remaining = count($entries) - $historySize;

        $removed = [];

        foreach (array_keys($entries) as $tag) {
            $removed[] = $entries[$tag];

            unset($entries[$tag]);

            if (--$remaining <= 0) {
                break;
            }
        }

        return $removed;
    }

    /**
     * Decodes JSON and throws when the payload is invalid.
     *
     * @param string $json JSON document to decode.
     *
     * @throws JsonException if the JSON is invalid.
     *
     * @return mixed Decoded JSON value.
     */
    private static function decode(string $json): mixed
    {
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Encodes a value as deterministic JSON.
     *
     * @param mixed $value Value to encode.
     *
     * @throws JsonException if the value cannot be encoded.
     *
     * @return string Encoded JSON document.
     */
    private static function encode(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }

    /**
     * Returns the manifest file path.
     *
     * @return string Manifest file path.
     */
    private function indexFile(): string
    {
        return "{$this->path}/" . self::INDEX_FILE;
    }

    /**
     * Creates the storage directory when it does not exist.
     */
    private function initialize(): void
    {
        $created = false;

        if (!is_dir($this->path)) {
            $created = @mkdir($this->path, $this->dirMode, true);
        }

        if (!is_dir($this->path)) {
            throw new StorageException(
                "Unable to create debug data directory: {$this->path}",
            );
        }

        if ($created && !@chmod($this->path, $this->dirMode)) {
            throw new StorageException(
                "Unable to apply debug data directory mode: {$this->path}",
            );
        }
    }

    /**
     * Determines whether a tag can form a safe snapshot filename.
     *
     * @param string $tag Snapshot tag to validate.
     *
     * @return bool Whether the tag is safe for a filename.
     */
    private static function isValidTag(string $tag): bool
    {
        return $tag !== 'index'
            && preg_match('/\A(?!-?(?:0|[1-9][0-9]*)\z)[A-Za-z0-9_-][A-Za-z0-9._-]*\z/', $tag) === 1;
    }

    /**
     * Returns the manifest lock file path.
     *
     * @return string Manifest lock file path.
     */
    private function lockFile(): string
    {
        return "{$this->path}/" . self::LOCK_FILE;
    }

    /**
     * Reads an existing transaction target for rollback, or `null` when it does not exist.
     */
    private function readExistingFile(string $file): string|null
    {
        if (!is_file($file)) {
            return null;
        }

        $contents = @file_get_contents($file);

        if ($contents === false) {
            throw new StorageException(
                "Unable to read debug data file: {$file}",
            );
        }

        return $contents;
    }

    /**
     * Reads the manifest or returns `null` when persisted JSON is invalid.
     *
     * @return Manifest|null Hydrated manifest or `null` for invalid persisted JSON.
     */
    private function readManifestFile(): Manifest|null
    {
        $file = $this->indexFile();

        if (!is_file($file)) {
            return new Manifest([]);
        }

        $raw = @file_get_contents($file);

        if ($raw === false) {
            throw new StorageException(
                "Unable to read debug manifest: {$file}",
            );
        }

        if ($raw === '') {
            return null;
        }

        try {
            return Manifest::fromArray(self::decode($raw));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Rebuilds a corrupt manifest from valid snapshot envelopes in deterministic request-time order.
     */
    private function rebuildManifest(): Manifest
    {
        $summaries = [];
        $files = glob("{$this->path}/*.json");

        foreach ($files === false ? [] : $files as $file) {
            if ($file === $this->indexFile()) {
                continue;
            }

            $tag = pathinfo($file, PATHINFO_FILENAME);

            if (!self::isValidTag($tag)) {
                continue;
            }

            $raw = @file_get_contents($file);

            if ($raw === false || $raw === '') {
                continue;
            }

            try {
                $snapshot = DebugSnapshot::fromArray(self::decode($raw));
            } catch (Throwable) {
                continue;
            }

            if ($snapshot->summary->tag === $tag) {
                $summaries[] = $snapshot->summary;
            }
        }

        usort(
            $summaries,
            static fn(RequestSummary $left, RequestSummary $right): int => [$left->time, $left->tag]
                <=> [$right->time, $right->tag],
        );

        $entries = [];

        foreach ($summaries as $summary) {
            $entries[$summary->tag] = $summary;
        }

        return new Manifest($entries);
    }

    /**
     * Recovers a prepared multi-file write or clears a committed journal left behind by a crash.
     */
    private function recoverTransaction(): void
    {
        $file = $this->transactionFile();
        $raw = @file_get_contents($file);

        if ($raw === false || $raw === '') {
            return;
        }

        try {
            $transaction = self::decode($raw);
        } catch (JsonException $exception) {
            throw new StorageException(
                "Invalid debug storage transaction journal: {$file}",
                0,
                $exception,
            );
        }

        if (
            !is_array($transaction)
            || ($transaction['version'] ?? null) !== self::TRANSACTION_VERSION
            || !is_string($transaction['state'] ?? null)
            || !is_string($transaction['tag'] ?? null)
            || !self::isValidTag($transaction['tag'])
            || (!isset($transaction['snapshotBefore']) && !array_key_exists('snapshotBefore', $transaction))
            || (!isset($transaction['manifestBefore']) && !array_key_exists('manifestBefore', $transaction))
            || $transaction['snapshotBefore'] !== null && !is_string($transaction['snapshotBefore'])
            || $transaction['manifestBefore'] !== null && !is_string($transaction['manifestBefore'])
        ) {
            throw new StorageException("Invalid debug storage transaction journal: {$file}");
        }

        if ($transaction['state'] === 'committed') {
            @unlink($file);

            return;
        }

        if ($transaction['state'] !== 'prepared') {
            throw new StorageException("Invalid debug storage transaction journal: {$file}");
        }

        $snapshotFile = $this->snapshotFile($transaction['tag']);

        if ($transaction['snapshotBefore'] === null) {
            $this->removeTransactionTarget($snapshotFile);
        } else {
            $this->atomicWrite($snapshotFile, $transaction['snapshotBefore']);
        }

        if ($transaction['manifestBefore'] === null) {
            $this->removeTransactionTarget($this->indexFile());
        } else {
            $this->atomicWrite($this->indexFile(), $transaction['manifestBefore']);
        }

        @unlink($file);
    }

    /**
     * Removes snapshot files whose tags no longer appear in the manifest.
     *
     * @param array<string, RequestSummary> $entries Retained manifest entries.
     */
    private function removeStaleSnapshots(array $entries): void
    {
        $files = glob("{$this->path}/*.json");

        foreach ($files === false ? [] : $files as $file) {
            if ($file === $this->indexFile()) {
                continue;
            }

            $tag = pathinfo($file, PATHINFO_FILENAME);

            if (!array_key_exists($tag, $entries)) {
                @unlink($file);
            }
        }
    }

    /**
     * Removes a target created by an interrupted first write without discarding the journal on failure.
     */
    private function removeTransactionTarget(string $file): void
    {
        if (is_file($file) && !@unlink($file)) {
            throw new StorageException(
                "Unable to roll back debug data file: {$file}",
            );
        }
    }

    /**
     * Returns the snapshot file path for a validated tag.
     *
     * @param string $tag Snapshot tag.
     *
     * @return string Snapshot file path.
     */
    private function snapshotFile(string $tag): string
    {
        if (!self::isValidTag($tag)) {
            throw new StorageException(
                "Invalid debug snapshot tag: {$tag}",
            );
        }

        return $this->path . "/{$tag}.json";
    }

    /**
     * Returns the multi-file write journal path.
     */
    private function transactionFile(): string
    {
        return "{$this->path}/" . self::TRANSACTION_FILE;
    }
}

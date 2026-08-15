<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonException;
use Throwable;

use function array_diff;
use function array_keys;
use function array_reverse;
use function chmod;
use function count;
use function fclose;
use function file_get_contents;
use function glob;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function pathinfo;
use function preg_match;
use function unlink;

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

        fclose($lock);
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
        try {
            $lock = $this->acquireLock(LOCK_SH);
        } catch (StorageException) {
            return [];
        }

        try {
            $manifest = $this->readManifestFile();

            return $manifest === null ? [] : array_reverse($manifest->entries, true);
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
        if (!self::isValidTag($tag)) {
            return null;
        }

        $raw = @file_get_contents($this->snapshotFile($tag));

        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            return DebugSnapshot::fromArray(self::decode($raw));
        } catch (Throwable) {
            return null;
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
            $manifest = $this->readManifestFile();

            $removeStaleSnapshots = $manifest === null;
            $entries = $manifest instanceof Manifest ? $manifest->entries : [];

            unset($entries[$tag]);

            $entries[$tag] = $snapshot->summary;

            $removed = $this->collectGarbage($entries, $historySize);

            if ($entries !== []) {
                $this->atomicWrite($snapshotFile, $snapshotJson);
            }

            if ($removed !== []) {
                $removeStaleSnapshots = true;
            }

            $this->atomicWrite($this->indexFile(), self::encode(new Manifest($entries)));

            if ($removeStaleSnapshots) {
                $this->removeStaleSnapshots($entries);
            }

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
            @chmod($temporary, $this->fileMode);
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
        if (!is_dir($this->path)) {
            @mkdir($this->path, $this->dirMode, true);
        }

        if (!is_dir($this->path)) {
            throw new StorageException(
                "Unable to create debug data directory: {$this->path}",
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
     * Reads the manifest or returns `null` when persisted JSON is invalid.
     *
     * @return Manifest|null Hydrated manifest or `null` for invalid persisted JSON.
     */
    private function readManifestFile(): Manifest|null
    {
        $raw = @file_get_contents($this->indexFile());

        if ($raw === false || $raw === '') {
            return new Manifest([]);
        }

        try {
            return Manifest::fromArray(self::decode($raw));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Removes snapshot files whose tags no longer appear in the manifest.
     *
     * @param array<string, RequestSummary> $entries Retained manifest entries.
     */
    private function removeStaleSnapshots(array $entries): void
    {
        $storedTags = [];

        $files = glob("{$this->path}/*.json");

        foreach ($files === false ? [] : $files as $file) {
            if ($file === $this->indexFile()) {
                continue;
            }

            $storedTags[] = pathinfo($file, PATHINFO_FILENAME);
        }

        foreach (array_diff($storedTags, array_keys($entries)) as $tag) {
            @unlink($this->snapshotFile($tag));
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
}

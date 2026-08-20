<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

/**
 * Exposes a manifest read together with an optional storage diagnostic.
 *
 * An empty {@see $entries} list with no {@see $error} represents a valid empty store. Consumers that only need the
 * legacy fail-closed behavior can continue to use {@see SnapshotStore::loadManifest()}.
 */
final readonly class ManifestReadResult
{
    /**
     * @param array<string, RequestSummary> $entries Newest entries first, or an empty list after a failed read.
     * @param StorageException|null $error Filesystem, recovery, decoding, or hydration failure.
     */
    public function __construct(
        public array $entries,
        public StorageException|null $error,
    ) {}
}

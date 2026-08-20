<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

/**
 * Exposes a snapshot read together with an optional storage diagnostic.
 *
 * A `null` {@see $snapshot} with no {@see $error} means the requested snapshot does not exist. Consumers that only
 * need the legacy fail-closed behavior can continue to use {@see SnapshotStore::readSnapshot()}.
 */
final readonly class SnapshotReadResult
{
    public function __construct(
        public DebugSnapshot|null $snapshot,
        public StorageException|null $error,
    ) {}
}

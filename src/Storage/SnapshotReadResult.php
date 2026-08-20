<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

/**
 * Exposes a snapshot read together with an optional storage diagnostic.
 */
final readonly class SnapshotReadResult
{
    public function __construct(
        public DebugSnapshot|null $snapshot,
        public StorageException|null $error,
    ) {}
}

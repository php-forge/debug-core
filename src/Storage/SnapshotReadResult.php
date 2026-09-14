<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

/**
 * Exposes a snapshot read together with an optional storage diagnostic.
 */
final readonly class SnapshotReadResult
{
    /**
     * @param DebugSnapshot|null $snapshot The read snapshot, if available.
     * @param StorageException|null $error The storage error, if any.
     */
    public function __construct(public DebugSnapshot|null $snapshot, public StorageException|null $error) {}
}

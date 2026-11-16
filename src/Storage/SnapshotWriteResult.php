<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

/**
 * Exposes the committed manifest and the summaries evicted by a snapshot write.
 */
final readonly class SnapshotWriteResult
{
    /**
     * Creates a snapshot write result with the committed manifest and evictions.
     *
     * @param array<string, RequestSummary> $entries Committed manifest entries, newest first.
     * @param list<RequestSummary> $removed Entries evicted from the manifest.
     */
    public function __construct(public array $entries, public array $removed) {}
}

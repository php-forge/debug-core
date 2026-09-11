<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use Closure;
use PHPForge\Debug\CollectorInterface;

/**
 * Provides a collector whose capture is produced by a closure and gated on an active cycle.
 */
final class ClosureCollectorFixture implements CollectorInterface
{
    /**
     * @var bool Whether the collector is observing the active cycle.
     */
    private bool $started = false;

    /**
     * @param string $collectorId Stable collector ID.
     * @param Closure(): (array<string, mixed>|null) $operation Capture operation invoked while the collector runs.
     */
    public function __construct(private readonly string $collectorId, private readonly Closure $operation) {}

    /**
     * @return array<string, mixed>|null Capture operation result, or `null` outside an active cycle.
     */
    public function capture(): array|null
    {
        return $this->started ? ($this->operation)() : null;
    }

    public function id(): string
    {
        return $this->collectorId;
    }

    public function shutdown(): void
    {
        $this->started = false;
    }

    public function startup(): void
    {
        $this->started = true;
    }
}

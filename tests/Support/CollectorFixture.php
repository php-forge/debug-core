<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\Collector\CollectorInterface;
use PHPForge\Debug\Storage\PanelSnapshot;
use RuntimeException;

/**
 * Provides configurable collector behavior for coordinator tests.
 */
final class CollectorFixture implements CollectorInterface
{
    public int $shutdownCount = 0;
    public int $startupCount = 0;

    /**
     * @param string $collectorId Stable collector ID.
     * @param PanelSnapshot|null $snapshot Snapshot returned from capture.
     * @param bool $failCapture Whether capture should fail.
     * @param bool $failShutdown Whether shutdown should fail.
     * @param string $shutdownFailureMessage Shutdown failure message.
     * @param int $startupFailuresRemaining Number of startup calls that should fail.
     * @param string $startupFailureMessage Startup failure message.
     */
    public function __construct(
        private readonly string $collectorId,
        private readonly PanelSnapshot|null $snapshot = null,
        private readonly bool $failCapture = false,
        private readonly bool $failShutdown = false,
        private readonly string $shutdownFailureMessage = 'Collector shutdown failed.',
        private int $startupFailuresRemaining = 0,
        private readonly string $startupFailureMessage = 'Collector startup failed.',
    ) {}

    public function capture(): PanelSnapshot|null
    {
        if ($this->failCapture) {
            throw new RuntimeException('Collector capture failed.');
        }

        return $this->snapshot;
    }

    public function id(): string
    {
        return $this->collectorId;
    }

    public function shutdown(): void
    {
        ++$this->shutdownCount;

        if ($this->failShutdown) {
            throw new RuntimeException($this->shutdownFailureMessage);
        }
    }

    public function startup(): void
    {
        ++$this->startupCount;

        if ($this->startupFailuresRemaining > 0) {
            --$this->startupFailuresRemaining;

            throw new RuntimeException($this->startupFailureMessage);
        }
    }
}

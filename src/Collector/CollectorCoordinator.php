<?php

declare(strict_types=1);

namespace PHPForge\Debug\Collector;

use InvalidArgumentException;
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use Throwable;

use function trim;

/**
 * Coordinates validated collectors and isolates their snapshot capture failures.
 */
final class CollectorCoordinator
{
    /**
     * @var array<string, CollectorInterface> Collectors indexed by their stable ID.
     */
    private array $collectors = [];
    private bool $started = false;

    /**
     * Materializes collectors and rejects empty or duplicate IDs before request capture.
     *
     * @param iterable<CollectorInterface> $collectors Registered collectors.
     */
    public function __construct(iterable $collectors)
    {
        foreach ($collectors as $collector) {
            $id = $collector->id();

            if (trim($id) === '') {
                throw new InvalidArgumentException('Debug collector ID must not be empty.');
            }

            if (isset($this->collectors[$id])) {
                throw new InvalidArgumentException("Duplicate debug collector ID: {$id}.");
            }

            $this->collectors[$id] = $collector;
        }
    }

    /**
     * Captures every collector into one versioned request snapshot.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = $coordinator->capture($summary);
     * ```
     *
     * @param RequestSummary $summary Captured request metadata.
     *
     * @return DebugSnapshot Captured request envelope.
     */
    public function capture(RequestSummary $summary): DebugSnapshot
    {
        $panels = [];
        $failures = [];

        foreach ($this->collectors as $id => $collector) {
            try {
                $snapshot = $collector->capture();

                if ($snapshot !== null) {
                    $panels[$id] = $snapshot->jsonSerialize();
                }
            } catch (Throwable $throwable) {
                $failures[$id] = PanelFailure::fromThrowable(PanelFailure::CAPTURE, $throwable);
            }
        }

        return new DebugSnapshot($summary, $panels, $failures);
    }

    /**
     * Returns the collector registered under the given stable ID.
     *
     * Usage example:
     *
     * ```php
     * $collector = $coordinator->collector('app.orders');
     * ```
     *
     * @param string $id Collector ID.
     *
     * @return CollectorInterface|null Registered collector, or `null` when the ID is unknown.
     */
    public function collector(string $id): CollectorInterface|null
    {
        return $this->collectors[$id] ?? null;
    }

    /**
     * Returns whether a collector is registered under the given stable ID.
     *
     * Usage example:
     *
     * ```php
     * $registered = $coordinator->hasCollector('app.orders');
     * ```
     *
     * @param string $id Collector ID.
     *
     * @return bool Whether the collector is registered.
     */
    public function hasCollector(string $id): bool
    {
        return isset($this->collectors[$id]);
    }

    /**
     * Stops every collector once and propagates the first shutdown error after cleanup completes.
     *
     * Usage example:
     *
     * ```php
     * $coordinator->shutdown();
     * ```
     *
     * @throws Throwable When a collector cannot shut down.
     */
    public function shutdown(): void
    {
        if (!$this->started) {
            return;
        }

        $this->started = false;
        $failure = null;

        foreach ($this->collectors as $collector) {
            try {
                $collector->shutdown();
            } catch (Throwable $throwable) {
                $failure ??= $throwable;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * Starts every registered collector once and rolls back affected collectors when startup fails.
     *
     * Usage example:
     *
     * ```php
     * $coordinator->startup();
     * ```
     *
     * @throws Throwable When a collector cannot start.
     */
    public function startup(): void
    {
        if ($this->started) {
            return;
        }

        $affectedCollectors = [];

        try {
            foreach ($this->collectors as $collector) {
                $affectedCollectors[] = $collector;

                $collector->startup();
            }
        } catch (Throwable $startupFailure) {
            foreach ($affectedCollectors as $affectedCollector) {
                try {
                    $affectedCollector->shutdown();
                } catch (Throwable) {
                    // Preserve the startup failure while continuing rollback.
                }
            }

            throw $startupFailure;
        }

        $this->started = true;
    }
}

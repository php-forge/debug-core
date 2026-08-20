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
     * @var array<string, CollectorInterface> Collectors that still require cleanup in the active or failed cycle.
     */
    private array $startedCollectors = [];

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
     * Runs one request operation inside a complete collector lifecycle.
     *
     * A cleanup failure is propagated when the operation succeeded. When the operation already failed, its exact
     * throwable remains primary and an optional diagnostic callback receives the secondary cleanup failure. A failing
     * diagnostic callback is deliberately ignored so instrumentation never replaces the application failure.
     *
     * @template TResult
     *
     * @param callable(): TResult $operation Request operation to execute while collectors are active.
     * @param (callable(Throwable): void)|null $cleanupFailureHandler Optional secondary-failure observer.
     *
     * @return TResult Operation result.
     *
     * @throws Throwable When startup, the operation, or primary cleanup fails.
     */
    public function run(callable $operation, callable|null $cleanupFailureHandler = null): mixed
    {
        $this->startup();

        try {
            $result = $operation();
        } catch (Throwable $primaryFailure) {
            try {
                $this->shutdown();
            } catch (Throwable $cleanupFailure) {
                self::reportCleanupFailure($cleanupFailure, $cleanupFailureHandler);
            }

            throw $primaryFailure;
        }

        $this->shutdown();

        return $result;
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
        if (!$this->started && $this->startedCollectors === []) {
            return;
        }

        $this->started = false;

        $failure = null;

        foreach ($this->startedCollectors as $id => $collector) {
            try {
                $collector->shutdown();

                unset($this->startedCollectors[$id]);
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

        if ($this->startedCollectors !== []) {
            $this->shutdown();
        }

        try {
            foreach ($this->collectors as $id => $collector) {
                $this->startedCollectors[$id] = $collector;

                $collector->startup();
            }
        } catch (Throwable $startupFailure) {
            foreach ($this->startedCollectors as $affectedId => $affectedCollector) {
                try {
                    $affectedCollector->shutdown();

                    unset($this->startedCollectors[$affectedId]);
                } catch (Throwable) {
                    // Preserve the startup failure while continuing rollback.
                }
            }

            throw $startupFailure;
        }

        $this->started = true;
    }

    /**
     * Reports a secondary cleanup failure without allowing the observer to replace the primary failure.
     *
     * @param (callable(Throwable): void)|null $cleanupFailureHandler Secondary-failure observer.
     */
    private static function reportCleanupFailure(
        Throwable $cleanupFailure,
        callable|null $cleanupFailureHandler,
    ): void {
        if ($cleanupFailureHandler === null) {
            return;
        }

        try {
            $cleanupFailureHandler($cleanupFailure);
        } catch (Throwable) {
            // Diagnostic observers must not replace the primary application failure.
        }
    }
}

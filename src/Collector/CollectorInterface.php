<?php

declare(strict_types=1);

namespace PHPForge\Debug\Collector;

use PHPForge\Debug\Storage\PanelSnapshot;

/**
 * Defines a framework-neutral debug collector with a stable persisted ID.
 */
interface CollectorInterface
{
    /**
     * Captures the current request data as a typed snapshot.
     *
     * @return PanelSnapshot|null Captured payload or `null` when the collector has no data.
     */
    public function capture(): PanelSnapshot|null;

    /**
     * Returns the stable ID used as the persisted panel key.
     *
     * @return string Stable collector ID.
     */
    public function id(): string;

    /**
     * Stops collection and clears request-scoped state idempotently.
     */
    public function shutdown(): void;

    /**
     * Starts collection for the current request.
     */
    public function startup(): void;
}

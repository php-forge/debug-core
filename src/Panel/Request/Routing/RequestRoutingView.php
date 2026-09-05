<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * Routing diagnostics composed into the framework-neutral Request panel.
 */
final readonly class RequestRoutingView
{
    public function __construct(
        public CurrentRouteView $current,
        public RouteInventoryView|null $inventory = null,
    ) {}
}

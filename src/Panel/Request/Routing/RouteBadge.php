<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * Adapter-provided routing configuration badge.
 */
final readonly class RouteBadge
{
    public function __construct(
        public string $label,
        public string $variant = 'muted',
    ) {}
}

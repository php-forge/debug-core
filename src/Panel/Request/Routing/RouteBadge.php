<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * Adapter-provided routing configuration badge.
 */
final readonly class RouteBadge
{
    /**
     * @param string $label Badge label.
     * @param string $variant Badge variant, defaults to 'muted'.
     */
    public function __construct(public string $label, public string $variant = 'muted') {}
}

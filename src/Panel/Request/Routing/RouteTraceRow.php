<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * One routing rule inspected while resolving the current request.
 */
final readonly class RouteTraceRow
{
    public function __construct(public string $rule, public string $parent = '', public bool $matched = false) {}
}

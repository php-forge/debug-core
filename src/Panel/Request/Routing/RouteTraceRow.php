<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * One routing rule inspected while resolving the current request.
 */
final readonly class RouteTraceRow
{
    /**
     * @param string $rule Routing rule inspected.
     * @param string $parent Parent rule, if any.
     * @param bool $matched Whether this rule matched the current request.
     */
    public function __construct(public string $rule, public string $parent = '', public bool $matched = false) {}
}

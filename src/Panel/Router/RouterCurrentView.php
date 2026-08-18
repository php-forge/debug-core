<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Router;

use function count;

/**
 * Typed view-model for the Current Route section of the Router panel.
 *
 * Plain surface over the hydrated {@see RouterSnapshot}: the matched action, the resolved route, the rules tried,
 * and whether the router matched.
 */
final readonly class RouterCurrentView
{
    public function __construct(
        /**
         * Resolved action descriptor logged for the current request; empty when unresolved.
         */
        public string $action = '',
        /**
         * Number of rules inspected before a match (or until the trace ended).
         */
        public int $count = 0,
        /**
         * Whether any inspected rule reported a successful match.
         */
        public bool $hasMatch = false,
        /**
         * @var list<CurrentRouteLogRow> Rules inspected during routing, in inspection order.
         */
        public array $logs = [],
        /**
         * Trace-level info message captured for the routing pass, when present.
         */
        public string|null $message = null,
        /**
         * Resolved request route logged for the current request; empty when unresolved.
         */
        public string $route = '',
    ) {}

    /**
     * Builds the view model from a hydrated router snapshot, or an empty one when the panel captured nothing.
     */
    public static function fromSnapshot(RouterSnapshot|null $snapshot): self
    {
        if ($snapshot === null) {
            return new self();
        }

        $logs = $snapshot->entries();

        return new self(
            action: $snapshot->action ?? '',
            count: count($logs),
            hasMatch: $snapshot->hasMatch(),
            logs: $logs,
            message: $snapshot->message,
            route: $snapshot->route,
        );
    }
}

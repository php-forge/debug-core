<?php

declare(strict_types=1);

namespace PHPForge\Debug\Routing;

/**
 * Builds adapter-owned debugger URLs for framework-neutral panel renderers.
 */
interface DebugUrlGeneratorInterface
{
    /**
     * Builds a debugger action URL scoped to a captured request.
     *
     * @param string $action Adapter-defined action identifier.
     * @param string $tag Captured request tag.
     * @param array<array-key, mixed> $queryParams Additional query parameters.
     */
    public function action(string $action, string $tag, array $queryParams = []): string;

    /**
     * Builds the request-history URL.
     *
     * @param array<array-key, mixed> $queryParams History filter, sort, or cursor parameters.
     */
    public function history(array $queryParams = []): string;

    /**
     * Builds a captured-request panel URL.
     *
     * @param string $tag Captured request tag.
     * @param string $panel Stable panel identifier.
     * @param array<array-key, mixed> $queryParams Panel filter, sort, or pagination parameters.
     */
    public function panel(string $tag, string $panel, array $queryParams = []): string;
}

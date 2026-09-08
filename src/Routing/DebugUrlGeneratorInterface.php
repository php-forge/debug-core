<?php

declare(strict_types=1);

namespace PHPForge\Debug\Routing;

/**
 * Builds the adapter-owned captured-request panel URL consumed by framework-neutral panel renderers.
 */
interface DebugUrlGeneratorInterface
{
    /**
     * Builds a captured-request panel URL.
     *
     * @param string $tag Captured request tag.
     * @param string $panel Stable panel identifier.
     * @param array<array-key, mixed> $queryParams Panel filter, sort, or pagination parameters.
     */
    public function panel(string $tag, string $panel, array $queryParams = []): string;
}

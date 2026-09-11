<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\Routing\DebugUrlGeneratorInterface;

use function http_build_query;

/**
 * Provides a predictable panel URL shape for renderers that delegate link building to the adapter.
 */
final class DebugUrlGeneratorFixture implements DebugUrlGeneratorInterface
{
    /**
     * @param string $tag Captured request tag.
     * @param string $panel Stable panel identifier.
     * @param array<array-key, mixed> $queryParams Panel filter, sort, or pagination parameters.
     *
     * @return string Captured-request panel URL.
     */
    public function panel(string $tag, string $panel, array $queryParams = []): string
    {
        $path = "/panel/{$tag}/{$panel}";

        return $queryParams === [] ? $path : $path . '?' . http_build_query($queryParams);
    }
}

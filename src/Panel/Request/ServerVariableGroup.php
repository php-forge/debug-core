<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Groups captured server variables for the Request panel without changing their diagnostic values.
 */
final readonly class ServerVariableGroup
{
    /**
     * @param string $id Identifier for the server variable group.
     * @param string $label Human-readable label for the server variable group.
     * @param array<int|string, mixed> $entries Captured server variables within the group.
     * @param bool $collapsed Whether the group should be initially collapsed.
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $entries,
        public bool $collapsed = false,
    ) {}
}

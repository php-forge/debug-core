<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Groups captured server variables for the Request panel without changing their diagnostic values.
 */
final readonly class ServerVariableGroup
{
    /**
     * @param array<int|string, mixed> $entries
     */
    public function __construct(
        public string $id,
        public string $label,
        public array $entries,
        public bool $collapsed = false,
    ) {}
}

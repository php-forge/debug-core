<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Vite;

use function count;

/**
 * Typed aggregate view-model for the Vite detail view and toolbar summary.
 */
final readonly class ViteSummary
{
    /**
     * @param list<ViteComponent> $components Captured Vite integrations.
     */
    public function __construct(public array $components) {}

    /**
     * Returns the number of captured integrations.
     */
    public function count(): int
    {
        return count($this->components);
    }

    /**
     * Returns whether the summary contains no integrations.
     */
    public function isEmpty(): bool
    {
        return $this->components === [];
    }

    /**
     * Returns the shared runtime mode, `Mixed`, or `Unknown` when no reliable common mode exists.
     */
    public function modeLabel(): string
    {
        if ($this->components === []) {
            return 'Unknown';
        }

        $mode = $this->components[0]->mode;

        foreach ($this->components as $component) {
            if ($component->mode !== $mode) {
                return 'Mixed';
            }
        }

        return match ($mode) {
            ViteComponent::MODE_DEVELOPMENT => 'Development',
            ViteComponent::MODE_PRODUCTION => 'Production',
            default => 'Unknown',
        };
    }
}

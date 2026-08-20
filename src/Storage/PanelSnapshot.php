<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;

/**
 * Defines a typed debug-panel snapshot.
 */
interface PanelSnapshot extends JsonSerializable
{
    /**
     * Returns the panel snapshot for JSON serialization.
     *
     * @return array<string, mixed> Serialized panel fields.
     */
    public function jsonSerialize(): array;
}

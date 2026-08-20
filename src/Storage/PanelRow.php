<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;

/**
 * Defines a typed row held by a panel snapshot.
 */
interface PanelRow extends JsonSerializable
{
    /**
     * Returns the typed row for JSON serialization.
     *
     * @return array<string, mixed> Serialized row fields.
     */
    public function jsonSerialize(): array;
}

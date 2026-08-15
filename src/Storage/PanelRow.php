<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;

/**
 * Defines a typed row held by a panel snapshot.
 *
 * Rows are narrowed once, at capture time, and persisted in that typed form; hydration restores them through
 * {@see Payload} without coercion.
 */
interface PanelRow extends JsonSerializable
{
    /**
     * Returns the typed row for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $data = $row->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Serialized row fields.
     */
    public function jsonSerialize(): array;
}

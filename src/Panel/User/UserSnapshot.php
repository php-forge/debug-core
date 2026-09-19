<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\User;

use PHPForge\Debug\Storage\{ArrayPayloadSnapshot, PanelSnapshot};

/**
 * Canonical User panel identity and RBAC snapshot.
 */
final readonly class UserSnapshot implements PanelSnapshot
{
    use ArrayPayloadSnapshot;

    /**
     * Returns the captured identity and RBAC payload.
     *
     * @return array<array-key, mixed> Captured identity attributes, roles, and permissions.
     */
    public function data(): array
    {
        return $this->values();
    }

    /**
     * Returns the key under which the user payload is stored.
     *
     * @return string Payload key.
     */
    protected static function payloadKey(): string
    {
        return 'data';
    }
}

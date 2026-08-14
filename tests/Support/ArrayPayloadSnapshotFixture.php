<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\Storage\{ArrayPayloadSnapshot, PanelSnapshot};

/**
 * Exercises the portable capture and hydration trait in the package that declares it.
 */
final class ArrayPayloadSnapshotFixture implements PanelSnapshot
{
    use ArrayPayloadSnapshot;

    /**
     * Returns the restored fixture payload.
     *
     * @return array<array-key, mixed> Restored fixture payload.
     */
    public function data(): array
    {
        return $this->values();
    }

    /**
     * Returns the fixture payload key.
     *
     * @return string Fixture payload key.
     */
    protected static function payloadKey(): string
    {
        return 'values';
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function abs;
use function crc32;
use function strtolower;

/**
 * Derives stable, deterministic avatar colours from arbitrary identifying strings.
 */
final class Avatar
{
    /**
     * Fallback hue used when `$seed` is empty.
     */
    private const int DEFAULT_HUE = 210;

    /**
     * Returns a stable hue (`0..359`) for the given seed, or {@see self::DEFAULT_HUE} when the seed is empty.
     *
     * @param string $seed Identifying value used to derive the hue.
     *
     * @return int Hue in the `0..359` range.
     */
    public static function hueFor(string $seed): int
    {
        if ($seed === '') {
            return self::DEFAULT_HUE;
        }

        return abs(crc32(strtolower($seed))) % 360;
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function abs;
use function crc32;
use function mb_strtoupper;
use function mb_substr;
use function strtolower;

/**
 * Derives stable, deterministic avatar colours and monogram initials from arbitrary identifying strings.
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

    /**
     * Returns the uppercased first character of the given seed, or `'?'` when the seed is empty.
     *
     * Multibyte-safe: the initial is taken as one character, not one byte.
     *
     * @param string $seed Identifying value the monogram is derived from.
     *
     * @return string Single uppercased character, or `'?'`.
     */
    public static function initial(string $seed): string
    {
        if ($seed === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($seed, 0, 1));
    }
}

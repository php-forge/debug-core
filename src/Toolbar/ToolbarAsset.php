<?php

declare(strict_types=1);

namespace PHPForge\Debug\Toolbar;

use PHPForge\Debug\Asset\DebugAsset;
use RuntimeException;

use function base64_encode;
use function file_get_contents;

/**
 * Provides self-contained toolbar assets shared by framework debug adapters.
 */
final class ToolbarAsset
{
    private static string|null $script = null;
    private static string|null $yiiLogo = null;

    /**
     * Returns the minified toolbar Web Component runtime.
     *
     * Usage example:
     *
     * ```php
     * $script = \PHPForge\Debug\Toolbar\ToolbarAsset::script();
     * ```
     *
     * @param bool $refresh Whether to bypass the in-process cache.
     *
     * @return string Minified JavaScript runtime.
     *
     * @throws RuntimeException When the packaged asset cannot be read.
     */
    public static function script(bool $refresh = false): string
    {
        if (!$refresh && self::$script !== null) {
            return self::$script;
        }

        $script = file_get_contents(DebugAsset::sourcePath() . '/dist/js/toolbar.min.js');

        if ($script === false || $script === '') {
            throw new RuntimeException('Unable to read the packaged debug toolbar runtime.');
        }

        return self::$script = $script;
    }

    /**
     * Returns the bundled Yii logo as a self-contained SVG data URI.
     *
     * Usage example:
     *
     * ```php
     * $logo = \PHPForge\Debug\Toolbar\ToolbarAsset::yiiLogo();
     * ```
     *
     * @param bool $refresh Whether to bypass the in-process cache.
     *
     * @return string SVG data URI.
     *
     * @throws RuntimeException When the packaged asset cannot be read.
     */
    public static function yiiLogo(bool $refresh = false): string
    {
        if (!$refresh && self::$yiiLogo !== null) {
            return self::$yiiLogo;
        }

        $svg = DebugAsset::icon('yii', $refresh);

        if ($svg === '') {
            throw new RuntimeException('Unable to read the packaged Yii logo.');
        }

        return self::$yiiLogo = 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

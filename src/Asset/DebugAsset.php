<?php

declare(strict_types=1);

namespace PHPForge\Debug\Asset;

use RuntimeException;

use function base64_encode;
use function file_get_contents;
use function is_file;
use function preg_match;
use function str_replace;

/**
 * Provides the compiled debugger frontend and shared SVG icon library.
 */
final class DebugAsset
{
    /**
     * @var list<string> Font files referenced by the compiled stylesheet.
     */
    private const array FONTS = [
        'ibm-plex-sans-latin-400-normal.woff2',
        'ibm-plex-sans-latin-500-normal.woff2',
        'ibm-plex-sans-latin-600-normal.woff2',
        'jetbrains-mono-latin-400-normal.woff2',
        'jetbrains-mono-latin-500-normal.woff2',
        'jetbrains-mono-latin-700-normal.woff2',
    ];

    /**
     * @var array<string, string> Cached SVG markup indexed by icon name.
     */
    private static array $icons = [];
    private static string|null $inlineStylesheet = null;
    private static string|null $script = null;
    private static string|null $stylesheet = null;

    /**
     * Returns one trusted bundled SVG icon.
     *
     * Usage example:
     *
     * ```php
     * $svg = \PHPForge\Debug\Asset\DebugAsset::icon('request');
     * ```
     *
     * @param string $name Icon basename without the `.svg` extension.
     * @param bool $refresh Whether to bypass the in-process cache.
     *
     * @return string SVG markup or an empty string when the icon is unavailable.
     */
    public static function icon(string $name, bool $refresh = false): string
    {
        if (!$refresh && isset(self::$icons[$name])) {
            return self::$icons[$name];
        }

        $path = self::iconPath($name);

        if ($path === null) {
            return self::$icons[$name] = '';
        }

        $svg = file_get_contents($path);

        return self::$icons[$name] = $svg === false ? '' : $svg;
    }

    /**
     * Resolves one bundled SVG icon path.
     *
     * Usage example:
     *
     * ```php
     * $path = \PHPForge\Debug\Asset\DebugAsset::iconPath('request');
     * ```
     *
     * @param string $name Icon basename without the `.svg` extension.
     *
     * @return string|null Absolute icon path or `null` when unavailable.
     */
    public static function iconPath(string $name): string|null
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/D', $name) !== 1) {
            return null;
        }

        $path = self::sourcePath() . '/svg/' . $name . '.svg';

        return is_file($path) ? $path : null;
    }

    /**
     * Returns the compiled stylesheet with its fonts embedded as data URIs.
     *
     * Usage example:
     *
     * ```php
     * $stylesheet = \PHPForge\Debug\Asset\DebugAsset::inlineStylesheet();
     * ```
     *
     * @param bool $refresh Whether to bypass the in-process cache.
     *
     * @return string Self-contained compiled stylesheet.
     *
     * @throws RuntimeException When a packaged asset cannot be read.
     */
    public static function inlineStylesheet(bool $refresh = false): string
    {
        if (!$refresh && self::$inlineStylesheet !== null) {
            return self::$inlineStylesheet;
        }

        $stylesheet = self::stylesheet($refresh);

        foreach (self::FONTS as $font) {
            $contents = self::read(
                self::sourcePath() . '/dist/fonts/' . $font,
                "Unable to read the packaged debugger font '{$font}'.",
            );
            $stylesheet = str_replace(
                "url(../fonts/{$font})",
                'url(data:font/woff2;base64,' . base64_encode($contents) . ')',
                $stylesheet,
            );
        }

        return self::$inlineStylesheet = $stylesheet;
    }

    /**
     * Returns the compiled full-page debugger JavaScript.
     *
     * Usage example:
     *
     * ```php
     * $script = \PHPForge\Debug\Asset\DebugAsset::script();
     * ```
     *
     * @param bool $refresh Whether to bypass the in-process cache.
     *
     * @return string Compiled JavaScript.
     *
     * @throws RuntimeException When the packaged asset cannot be read.
     */
    public static function script(bool $refresh = false): string
    {
        if (!$refresh && self::$script !== null) {
            return self::$script;
        }

        return self::$script = self::read(
            self::sourcePath() . '/dist/js/debug.min.js',
            'Unable to read the packaged debugger JavaScript.',
        );
    }

    /**
     * Returns the asset directory published by framework adapters.
     *
     * Usage example:
     *
     * ```php
     * $sourcePath = \PHPForge\Debug\Asset\DebugAsset::sourcePath();
     * ```
     *
     * @return string Absolute asset source path.
     */
    public static function sourcePath(): string
    {
        return dirname(__DIR__, 2) . '/resources/assets';
    }

    /**
     * Returns the compiled full-page debugger stylesheet.
     *
     * Usage example:
     *
     * ```php
     * $stylesheet = \PHPForge\Debug\Asset\DebugAsset::stylesheet();
     * ```
     *
     * @param bool $refresh Whether to bypass the in-process cache.
     *
     * @return string Compiled CSS.
     *
     * @throws RuntimeException When the packaged asset cannot be read.
     */
    public static function stylesheet(bool $refresh = false): string
    {
        if (!$refresh && self::$stylesheet !== null) {
            return self::$stylesheet;
        }

        return self::$stylesheet = self::read(
            self::sourcePath() . '/dist/css/debug.min.css',
            'Unable to read the packaged debugger stylesheet.',
        );
    }

    /**
     * Reads one required packaged frontend asset.
     *
     * @param string $path Absolute asset path.
     * @param string $errorMessage Exception message used when the read fails.
     *
     * @return string Asset contents.
     *
     * @throws RuntimeException When the asset cannot be read.
     */
    private static function read(string $path, string $errorMessage): string
    {
        $content = file_get_contents($path);

        if ($content === false || $content === '') {
            throw new RuntimeException($errorMessage);
        }

        return $content;
    }
}

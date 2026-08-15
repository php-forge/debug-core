<?php

declare(strict_types=1);

namespace PHPForge\Debug\Toolbar;

use JsonException;

use function htmlspecialchars;
use function json_encode;
use function strripos;
use function substr_replace;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Renders and injects the framework-neutral debug toolbar custom element.
 */
final class ToolbarRenderer
{
    /**
     * Injects rendered toolbar markup immediately before the final closing body tag.
     *
     * Usage example:
     *
     * ```php
     * $html = (new \PHPForge\Debug\Toolbar\ToolbarRenderer())->inject($html, $toolbar);
     * ```
     *
     * @param string $html Response HTML.
     * @param string $toolbar Rendered toolbar markup.
     *
     * @return string HTML containing the toolbar, appended when no closing body tag exists.
     */
    public function inject(string $html, string $toolbar): string
    {
        $offset = strripos($html, '</body>');

        return $offset === false
            ? $html . $toolbar
            : substr_replace($html, $toolbar, $offset, 0);
    }
    /**
     * Renders the toolbar element and its self-contained runtime.
     *
     * Usage example:
     *
     * ```php
     * $html = (new \PHPForge\Debug\Toolbar\ToolbarRenderer())->render('/debug/toolbar?tag=request-1');
     * ```
     *
     * @param string $dataUrl Toolbar payload URL.
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     * @param string $position Initial toolbar position.
     * @param int $height Initial drawer height percentage.
     *
     * @return string Toolbar element followed by its inline runtime.
     *
     * @throws JsonException When skip URLs cannot be encoded.
     */
    public function render(
        string $dataUrl,
        array $skipUrls = [],
        string $position = 'bottom',
        int $height = 50,
    ): string {
        return $this->renderElement($dataUrl, $skipUrls, $position, $height)
            . '<script>' . ToolbarAsset::script() . '</script>';
    }

    /**
     * Renders the toolbar custom element without its runtime.
     *
     * Usage example:
     *
     * ```php
     * $element = (new \PHPForge\Debug\Toolbar\ToolbarRenderer())->renderElement('/debug/toolbar?tag=request-1');
     * ```
     *
     * @param string $dataUrl Toolbar payload URL.
     * @param list<string> $skipUrls Same-origin URLs excluded from AJAX tracking.
     * @param string $position Initial toolbar position.
     * @param int $height Initial drawer height percentage.
     *
     * @return string Toolbar custom element.
     *
     * @throws JsonException When skip URLs cannot be encoded.
     */
    public function renderElement(
        string $dataUrl,
        array $skipUrls = [],
        string $position = 'bottom',
        int $height = 50,
    ): string {
        $skipUrlsAttribute = $skipUrls === []
            ? ''
            : ' data-skip-urls="'
                . self::escape(json_encode($skipUrls, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
                . '"';

        return '<yii-debug-toolbar id="yii-debug-toolbar"'
            . ' data-url="' . self::escape($dataUrl) . '"'
            . $skipUrlsAttribute
            . ' data-position="' . self::escape($position) . '"'
            . ' data-height="' . $height . '"'
            . ' style="display:none"></yii-debug-toolbar>';
    }

    /**
     * Escapes an HTML attribute value.
     *
     * @param string $value Raw attribute value.
     *
     * @return string Escaped attribute value.
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Toolbar;

use JsonSerializable;

use function array_map;

/**
 * Represents the framework-neutral payload loaded by the debug toolbar runtime.
 */
final readonly class ToolbarData implements JsonSerializable
{
    /**
     * @param string $tag Captured request tag.
     * @param string $title Toolbar brand title.
     * @param string $indexUrl Debug history URL.
     * @param string $configUrl Framework configuration URL.
     * @param list<ToolbarPanel> $items Toolbar panels.
     * @param string $position Initial toolbar position.
     * @param int $defaultHeight Initial drawer height percentage.
     * @param string $iconBaseUrl Base URL for optional SVG icons.
     * @param string|null $logo Primary logo URL or `null` to use the fallback.
     * @param string|null $logoFallback Fallback logo URL or `null` to render the built-in mark.
     * @param string|null $phpInfoUrl PHP information URL or `null` when unavailable.
     * @param string|null $phpVersion PHP version label or `null` when omitted.
     * @param string|null $yiiVersion Yii version label or `null` when omitted.
     */
    public function __construct(
        public string $tag,
        public string $title,
        public string $indexUrl,
        public string $configUrl,
        public array $items,
        public string $position = 'bottom',
        public int $defaultHeight = 50,
        public string $iconBaseUrl = '',
        public string|null $logo = null,
        public string|null $logoFallback = null,
        public string|null $phpInfoUrl = null,
        public string|null $phpVersion = null,
        public string|null $yiiVersion = null,
    ) {}

    /**
     * Returns the complete payload consumed by the toolbar runtime.
     *
     * Usage example:
     *
     * ```php
     * $payload = $toolbarData->jsonSerialize();
     * ```
     *
     * @return array{
     *     configUrl: string,
     *     defaultHeight: int,
     *     iconBaseUrl: string,
     *     indexUrl: string,
     *     items: list<array{
     *         id: string,
     *         title: string,
     *         items: list<array{
     *             value: string,
     *             status: string,
     *             label?: string,
     *             icon?: string,
     *             title?: string,
     *             url?: string,
     *         }>,
     *         url?: string,
     *         icon?: string,
     *     }>,
     *     logo: string|null,
     *     logoFallback: string|null,
     *     phpInfoUrl: string|null,
     *     phpVersion: string|null,
     *     position: string,
     *     tag: string,
     *     title: string,
     *     yiiVersion: string|null,
     * } Serialized toolbar payload.
     */
    public function jsonSerialize(): array
    {
        return [
            'configUrl' => $this->configUrl,
            'defaultHeight' => $this->defaultHeight,
            'iconBaseUrl' => $this->iconBaseUrl,
            'indexUrl' => $this->indexUrl,
            'items' => array_map(
                static fn(ToolbarPanel $panel): array => $panel->jsonSerialize(),
                $this->items,
            ),
            'logo' => $this->logo,
            'logoFallback' => $this->logoFallback,
            'phpInfoUrl' => $this->phpInfoUrl,
            'phpVersion' => $this->phpVersion,
            'position' => $this->position,
            'tag' => $this->tag,
            'title' => $this->title,
            'yiiVersion' => $this->yiiVersion,
        ];
    }
}

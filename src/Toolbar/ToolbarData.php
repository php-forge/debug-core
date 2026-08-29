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
     * Creates a minimal toolbar payload ready for immutable enrichment.
     */
    public static function create(string $tag, string $title): self
    {
        return new self(
            tag: $tag,
            title: $title,
            indexUrl: '',
            configUrl: '',
            items: [],
        );
    }

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
     *             id?: string,
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

    /**
     * Returns a copy with brand assets and version labels.
     */
    public function withBranding(
        string|null $logo,
        string|null $logoFallback,
        string|null $phpVersion,
        string|null $yiiVersion,
    ): self {
        return new self(
            tag: $this->tag,
            title: $this->title,
            indexUrl: $this->indexUrl,
            configUrl: $this->configUrl,
            items: $this->items,
            position: $this->position,
            defaultHeight: $this->defaultHeight,
            iconBaseUrl: $this->iconBaseUrl,
            logo: $logo,
            logoFallback: $logoFallback,
            phpInfoUrl: $this->phpInfoUrl,
            phpVersion: $phpVersion,
            yiiVersion: $yiiVersion,
        );
    }

    /**
     * Returns a copy with debugger navigation URLs.
     */
    public function withNavigation(string $indexUrl, string $configUrl, string|null $phpInfoUrl): self
    {
        return new self(
            tag: $this->tag,
            title: $this->title,
            indexUrl: $indexUrl,
            configUrl: $configUrl,
            items: $this->items,
            position: $this->position,
            defaultHeight: $this->defaultHeight,
            iconBaseUrl: $this->iconBaseUrl,
            logo: $this->logo,
            logoFallback: $this->logoFallback,
            phpInfoUrl: $phpInfoUrl,
            phpVersion: $this->phpVersion,
            yiiVersion: $this->yiiVersion,
        );
    }

    /**
     * Returns a copy with toolbar panels.
     *
     * @param list<ToolbarPanel> $items
     */
    public function withPanels(array $items): self
    {
        return new self(
            tag: $this->tag,
            title: $this->title,
            indexUrl: $this->indexUrl,
            configUrl: $this->configUrl,
            items: $items,
            position: $this->position,
            defaultHeight: $this->defaultHeight,
            iconBaseUrl: $this->iconBaseUrl,
            logo: $this->logo,
            logoFallback: $this->logoFallback,
            phpInfoUrl: $this->phpInfoUrl,
            phpVersion: $this->phpVersion,
            yiiVersion: $this->yiiVersion,
        );
    }

    /**
     * Returns a copy with drawer presentation settings.
     */
    public function withPresentation(string $position, int $defaultHeight, string $iconBaseUrl = ''): self
    {
        return new self(
            tag: $this->tag,
            title: $this->title,
            indexUrl: $this->indexUrl,
            configUrl: $this->configUrl,
            items: $this->items,
            position: $position,
            defaultHeight: $defaultHeight,
            iconBaseUrl: $iconBaseUrl,
            logo: $this->logo,
            logoFallback: $this->logoFallback,
            phpInfoUrl: $this->phpInfoUrl,
            phpVersion: $this->phpVersion,
            yiiVersion: $this->yiiVersion,
        );
    }
}

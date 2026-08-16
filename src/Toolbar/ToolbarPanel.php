<?php

declare(strict_types=1);

namespace PHPForge\Debug\Toolbar;

use JsonSerializable;

use function array_filter;
use function array_map;

/**
 * Represents a navigable group of metrics in the debug toolbar.
 */
final readonly class ToolbarPanel implements JsonSerializable
{
    /**
     * @param string $id Stable panel identifier.
     * @param string $title Display title.
     * @param string|null $url Debug page URL or `null` when only individual metrics are navigable.
     * @param string|null $icon Shared icon name or `null` when no icon is available.
     * @param list<ToolbarItem> $items Panel metrics.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string|null $url = null,
        public string|null $icon = null,
        public array $items = [],
    ) {}

    /**
     * Returns the panel payload consumed by the toolbar runtime.
     *
     * Usage example:
     *
     * ```php
     * $payload = (new \PHPForge\Debug\Toolbar\ToolbarPanel('request', 'Request'))->jsonSerialize();
     * ```
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     items: list<array{value: string, status: string, label?: string, icon?: string, title?: string, url?: string}>,
     *     url?: string,
     *     icon?: string,
     * } Serialized panel payload.
     */
    public function jsonSerialize(): array
    {
        return array_filter(
            [
                'id' => $this->id,
                'title' => $this->title,
                'url' => $this->url,
                'icon' => $this->icon,
                'items' => array_map(
                    static fn(ToolbarItem $item): array => $item->jsonSerialize(),
                    $this->items,
                ),
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }
}

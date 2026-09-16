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
     * @param bool $extension `true` when the panel is provider-owned or optional and the toolbar groups it under its
     * Extensions menu; `false` when the panel is built in and stays inline.
     */
    private function __construct(
        public string $id,
        public string $title,
        public string|null $url = null,
        public string|null $icon = null,
        public array $items = [],
        public bool $extension = false,
    ) {}

    /**
     * Creates a panel with no metrics or optional navigation.
     *
     * @param string $id Stable panel identifier.
     * @param string $title Display title.
     *
     * @return self Panel carrying only its identity.
     */
    public static function create(string $id, string $title): self
    {
        return new self($id, $title);
    }

    /**
     * Returns the panel payload consumed by the toolbar runtime.
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     items: list<array{value: string, status: string, label?: string, icon?: string, title?: string, url?: string,
     *     id?: string}>,
     *     url?: string,
     *     icon?: string,
     *     extension?: true,
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
                'extension' => $this->extension ? true : null,
                'items' => array_map(
                    static fn(ToolbarItem $item): array => $item->jsonSerialize(),
                    $this->items,
                ),
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }

    /**
     * Returns a copy with the specified extension classification.
     *
     * An extension panel is provider-owned or optional, so the toolbar groups it under its Extensions menu, while
     * built-in panels stay inline.
     *
     * @param bool $extension `true` to group the panel under the Extensions menu; `false` to keep it inline.
     *
     * @return self Panel with the classification applied.
     */
    public function withExtension(bool $extension): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            url: $this->url,
            icon: $this->icon,
            items: $this->items,
            extension: $extension,
        );
    }

    /**
     * Returns a copy with the specified icon.
     *
     * @param string|null $icon Shared icon name, or `null` when no icon is available.
     *
     * @return self Panel with the icon applied.
     */
    public function withIcon(string|null $icon): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            url: $this->url,
            icon: $icon,
            items: $this->items,
            extension: $this->extension,
        );
    }

    /**
     * Returns a copy with the replacement metric list.
     *
     * @param list<ToolbarItem> $items Panel metrics in display order; `[]` removes all metrics.
     *
     * @return self Panel with the metrics applied.
     */
    public function withItems(array $items): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            url: $this->url,
            icon: $this->icon,
            items: $items,
            extension: $this->extension,
        );
    }

    /**
     * Returns a copy with the specified URL.
     *
     * @param string|null $url Debug page URL, or `null` when only individual metrics are navigable.
     *
     * @return self Panel with the URL applied.
     */
    public function withUrl(string|null $url): self
    {
        return new self(
            id: $this->id,
            title: $this->title,
            url: $url,
            icon: $this->icon,
            items: $this->items,
            extension: $this->extension,
        );
    }
}

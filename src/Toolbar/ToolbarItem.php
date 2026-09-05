<?php

declare(strict_types=1);

namespace PHPForge\Debug\Toolbar;

use JsonSerializable;

use function array_filter;

/**
 * Represents one metric rendered inside a debug toolbar panel.
 */
final readonly class ToolbarItem implements JsonSerializable
{
    /**
     * @param string $value Metric value shown in the toolbar.
     * @param string|null $label Short metric label or `null` when an icon identifies the value.
     * @param string|null $icon Shared icon name or `null` when the label identifies the value.
     * @param string $status Semantic badge status.
     * @param string|null $title Tooltip text or `null` when no tooltip is needed.
     * @param string|null $url Debug page URL or `null` when the metric is not navigable.
     * @param string|null $id Stable semantic metric identifier or `null` for presentation-only metrics.
     */
    public function __construct(
        public string $value,
        public string|null $label = null,
        public string|null $icon = null,
        public string $status = 'default',
        public string|null $title = null,
        public string|null $url = null,
        public string|null $id = null,
    ) {}

    /**
     * Creates a metric with default presentation options.
     */
    public static function create(string $value): self
    {
        return new self($value);
    }

    /**
     * Returns the metric payload consumed by the toolbar runtime.
     *
     * @return array{
     *   value: string,
     *   status: string,
     *   label?: string,
     *   icon?: string,
     *   title?: string, url?: string,
     *   id?: string
     * } Serialized metric payload.
     */
    public function jsonSerialize(): array
    {
        return array_filter(
            [
                'label' => $this->label,
                'icon' => $this->icon,
                'value' => $this->value,
                'status' => $this->status,
                'title' => $this->title,
                'url' => $this->url,
                'id' => $this->id,
            ],
            static fn(string|null $value): bool => $value !== null,
        );
    }

    /**
     * Returns a copy with the specified icon.
     */
    public function withIcon(string|null $icon): self
    {
        return new self(
            value: $this->value,
            label: $this->label,
            icon: $icon,
            status: $this->status,
            title: $this->title,
            url: $this->url,
            id: $this->id,
        );
    }

    /**
     * Returns a copy with the specified metric ID.
     */
    public function withId(string|null $id): self
    {
        return new self(
            value: $this->value,
            label: $this->label,
            icon: $this->icon,
            status: $this->status,
            title: $this->title,
            url: $this->url,
            id: $id,
        );
    }

    /**
     * Returns a copy with the specified label.
     */
    public function withLabel(string|null $label): self
    {
        return new self(
            value: $this->value,
            label: $label,
            icon: $this->icon,
            status: $this->status,
            title: $this->title,
            url: $this->url,
            id: $this->id,
        );
    }

    /**
     * Returns a copy with the specified status.
     */
    public function withStatus(string $status): self
    {
        return new self(
            value: $this->value,
            label: $this->label,
            icon: $this->icon,
            status: $status,
            title: $this->title,
            url: $this->url,
            id: $this->id,
        );
    }

    /**
     * Returns a copy with the specified title.
     */
    public function withTitle(string|null $title): self
    {
        return new self(
            value: $this->value,
            label: $this->label,
            icon: $this->icon,
            status: $this->status,
            title: $title,
            url: $this->url,
            id: $this->id,
        );
    }

    /**
     * Returns a copy with the specified URL.
     */
    public function withUrl(string|null $url): self
    {
        return new self(
            value: $this->value,
            label: $this->label,
            icon: $this->icon,
            status: $this->status,
            title: $this->title,
            url: $url,
            id: $this->id,
        );
    }
}

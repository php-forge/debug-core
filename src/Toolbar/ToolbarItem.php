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
     */
    public function __construct(
        public string $value,
        public string|null $label = null,
        public string|null $icon = null,
        public string $status = 'default',
        public string|null $title = null,
        public string|null $url = null,
    ) {}

    /**
     * Returns the metric payload consumed by the toolbar runtime.
     *
     * Usage example:
     *
     * ```php
     * $payload = (new \PHPForge\Debug\Toolbar\ToolbarItem('12 ms', label: 'Time'))->jsonSerialize();
     * ```
     *
     * @return array{value: string, status: string, label?: string, icon?: string, title?: string, url?: string}
     * Serialized metric payload.
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
            ],
            static fn(string|null $value): bool => $value !== null,
        );
    }
}

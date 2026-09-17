<?php

declare(strict_types=1);

namespace PHPForge\Debug\Registration;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;
use PHPForge\Debug\Helper\Icon;

use function preg_match;
use function trim;

/**
 * Represents the host registration metadata of one debug panel.
 *
 * The ID is the stable association with captured data, so renaming a panel through {@see self::withOverride()} never
 * changes the stored key or any URL.
 */
final readonly class PanelRegistration
{
    /**
     * Validates the registration metadata and rejects an ID that is empty or padded with whitespace.
     *
     * @param string $id Stable panel identifier, never normalized.
     * @param string $title Display title.
     * @param string $icon Icon key, or `''` when the panel has no icon.
     * @param bool $extension `true` when the host groups the panel under its Extensions menu; `false` for a built-in.
     * @param int|null $position Sort weight among extensions, or `null` to order the entry by title.
     *
     * @throws InvalidArgumentException When the ID, the title, or the icon key is invalid.
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $icon,
        public bool $extension,
        public int|null $position = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException(
                Message::PANEL_ID_EMPTY->getMessage(),
            );
        }

        if (trim($id) !== $id) {
            throw new InvalidArgumentException(
                Message::PANEL_ID_WHITESPACE->getMessage($id),
            );
        }

        if ($title === '') {
            throw new InvalidArgumentException(
                Message::PANEL_TITLE_EMPTY->getMessage(),
            );
        }

        if ($icon !== '' && preg_match(Icon::KEY_PATTERN, $icon) !== 1) {
            throw new InvalidArgumentException(
                Message::PANEL_ICON_INVALID->getMessage($icon),
            );
        }
    }

    /**
     * Creates a registration for a host built-in panel, which keeps its fixed display order.
     *
     * @param string $id Stable panel identifier.
     * @param string $title Provider default title.
     * @param string $icon Provider default icon key, or `''` when the panel has no icon.
     *
     * @throws InvalidArgumentException When the ID, the title, or the icon key is invalid.
     *
     * @return self Built-in registration.
     */
    public static function builtIn(string $id, string $title, string $icon): self
    {
        return new self($id, $title, $icon, false);
    }

    /**
     * Creates a registration for a provider-owned panel, which the host groups under its Extensions menu.
     *
     * @param string $id Stable panel identifier.
     * @param string $title Provider default title.
     * @param string $icon Provider default icon key, or `''` when the panel has no icon.
     *
     * @throws InvalidArgumentException When the ID, the title, or the icon key is invalid.
     *
     * @return self Extension registration.
     */
    public static function extension(string $id, string $title, string $icon): self
    {
        return new self($id, $title, $icon, true);
    }

    /**
     * Returns a copy with the configured title, icon, and position applied.
     *
     * Values the override leaves `null` keep the provider default.
     *
     * @param PanelOverride $override Application configuration for this panel.
     *
     * @throws InvalidArgumentException When the override sets a position on a built-in panel.
     *
     * @return self Registration carrying the effective metadata.
     */
    public function withOverride(PanelOverride $override): self
    {
        if ($override->position !== null && $this->extension === false) {
            throw new InvalidArgumentException(
                Message::PANEL_POSITION_BUILT_IN->getMessage($this->id),
            );
        }

        return new self(
            id: $this->id,
            title: $override->title ?? $this->title,
            icon: $override->icon ?? $this->icon,
            extension: $this->extension,
            position: $override->position ?? $this->position,
        );
    }
}

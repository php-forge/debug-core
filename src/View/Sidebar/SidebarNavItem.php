<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Sidebar;

/**
 * Typed view-model for one entry in the debugger sidebar panel navigation.
 */
final readonly class SidebarNavItem
{
    public function __construct(
        /**
         * Visible label shown next to the icon ('History', 'Request', 'Database', ...).
         */
        public string $label,
        /**
         * Pre-rendered SVG glyph for the link icon; empty string when the panel did not supply a toolbar icon.
         */
        public string $iconSvg,
        /**
         * Pre-built link target for the `href` attribute.
         */
        public string $url,
        /**
         * `title` / `aria-label` hover text.
         */
        public string $tooltip,
        /**
         * `true` when this entry represents the currently active route drives the `is-active` modifier and the
         * `aria-current="page"` attribute on the rendered `<a>`.
         */
        public bool $isActive,
    ) {}
}

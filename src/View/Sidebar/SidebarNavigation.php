<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Sidebar;

/**
 * Represents the navigator row of the sidebar snapshot card: the four capture links and whether they act as a grid
 * cursor.
 *
 * The host computes every value from its own manifest and URL scheme, so this object carries no routing logic. The
 * defaults describe a single capture with nowhere to move.
 */
final readonly class SidebarNavigation
{
    public function __construct(
        /**
         * `true` when the sidebar is rendered for the index page and the navigator buttons act as a grid cursor.
         */
        public bool $isCursor = false,
        /**
         * Optional tag the cursor JS should land on when the sidebar arrives from a panel view's History link
         * (`?cursor=<tag>`). Empty string falls back to the newest captured request.
         */
        public string $cursorInitTag = '',
        /**
         * Newest request link target (top of list); empty string renders an empty `href`.
         */
        public string $newestUrl = '',
        /**
         * Oldest request link target (bottom of list); empty string renders an empty `href`.
         */
        public string $oldestUrl = '',
        /**
         * Newer request link target; empty string when the snapshot is already on the newest row.
         */
        public string $newerUrl = '',
        /**
         * Older request link target; empty string when the snapshot is already on the oldest row.
         */
        public string $olderUrl = '',
        /**
         * `true` when the snapshot is the newest captured request; disables the Newest button.
         */
        public bool $isNewest = true,
        /**
         * `true` when the snapshot is the oldest captured request; disables the Oldest button.
         */
        public bool $isOldest = true,
        /**
         * `true` when there is a newer request available; controls the Newer button.
         */
        public bool $hasNewer = false,
        /**
         * `true` when there is an older request available; controls the Older button.
         */
        public bool $hasOlder = false,
    ) {}
}

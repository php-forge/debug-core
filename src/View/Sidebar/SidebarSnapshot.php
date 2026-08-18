<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Sidebar;

/**
 * Typed view-model for the snapshot card surfaced at the top of the debugger sidebar ('CURRENT REQUEST' /
 * 'NEWEST REQUEST').
 *
 * Pre-resolves every loose-array access on the request summary ('method', 'url', 'statusCode', 'time', 'ajax') plus
 * the navigation URLs ('Newest' / 'Newer' / 'Older' / 'Oldest'), the status-pill variant, path-only URL display, and
 * the cursor-mode flag that the shared history-cursor JavaScript needs to wire the grid highlight.
 */
final readonly class SidebarSnapshot
{
    public function __construct(
        /**
         * Section heading shown above the snapshot card ('Current request' / 'Newest request').
         */
        public string $title,
        /**
         * Accessible name for the surrounding `<section>` element.
         */
        public string $ariaLabel,
        /**
         * HTTP method ('GET', 'POST', ...). Empty when not captured.
         */
        public string $method,
        /**
         * Path-only URL display (scheme/host stripped). Empty when not captured.
         */
        public string $path,
        /**
         * Full URL captured in the request summary; used as the `title` hover on the URL chip.
         */
        public string $fullUrl,
        /**
         * Response status code; '0' when not captured.
         */
        public int $statusCode,
        /**
         * Status-pill CSS modifier ('success' / 'muted' / 'warning' / 'danger') derived from `$statusCode`.
         */
        public string $statusVariant,
        /**
         * Formatted request time ('HH:MM:SS'); empty when not captured.
         */
        public string $time,
        /**
         * Whether the captured request was an AJAX request; surfaces the 'AJAX' tag in the card meta strip.
         */
        public bool $isAjax,
        /**
         * `true` when the sidebar is rendered for the index page and the navigator buttons act as a grid cursor.
         */
        public bool $isCursor,
        /**
         * Optional tag the cursor JS should land on when the sidebar arrives from a panel view's History link
         * (`?cursor=<tag>`). Empty string falls back to the newest captured request.
         */
        public string $cursorInitTag,
        /**
         * Newest request link target (top of list); empty string renders an empty `href`.
         */
        public string $newestUrl,
        /**
         * Oldest request link target (bottom of list); empty string renders an empty `href`.
         */
        public string $oldestUrl,
        /**
         * Newer request link target; empty string when the snapshot is already on the newest row.
         */
        public string $newerUrl,
        /**
         * Older request link target; empty string when the snapshot is already on the oldest row.
         */
        public string $olderUrl,
        /**
         * `true` when the snapshot is the newest captured request; disables the Newest button.
         */
        public bool $isNewest,
        /**
         * `true` when the snapshot is the oldest captured request; disables the Oldest button.
         */
        public bool $isOldest,
        /**
         * `true` when there is a newer request available; controls the Newer button.
         */
        public bool $hasNewer,
        /**
         * `true` when there is an older request available; controls the Older button.
         */
        public bool $hasOlder,
    ) {}
}

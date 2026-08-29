<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Sidebar;

/**
 * Typed view-model for the snapshot card surfaced at the top of the debugger sidebar ('CURRENT REQUEST' /
 * 'NEWEST REQUEST').
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

    /**
     * Creates an empty sidebar snapshot ready for immutable enrichment.
     */
    public static function create(string $title, string|null $ariaLabel = null): self
    {
        return new self(
            title: $title,
            ariaLabel: $ariaLabel ?? $title,
            method: '',
            path: '',
            fullUrl: '',
            statusCode: 0,
            statusVariant: 'muted',
            time: '',
            isAjax: false,
            isCursor: false,
            cursorInitTag: '',
            newestUrl: '',
            oldestUrl: '',
            newerUrl: '',
            olderUrl: '',
            isNewest: true,
            isOldest: true,
            hasNewer: false,
            hasOlder: false,
        );
    }

    /**
     * Returns a copy with history-cursor behavior.
     */
    public function withCursor(bool $isCursor = true, string $cursorInitTag = ''): self
    {
        return new self(
            title: $this->title,
            ariaLabel: $this->ariaLabel,
            method: $this->method,
            path: $this->path,
            fullUrl: $this->fullUrl,
            statusCode: $this->statusCode,
            statusVariant: $this->statusVariant,
            time: $this->time,
            isAjax: $this->isAjax,
            isCursor: $isCursor,
            cursorInitTag: $cursorInitTag,
            newestUrl: $this->newestUrl,
            oldestUrl: $this->oldestUrl,
            newerUrl: $this->newerUrl,
            olderUrl: $this->olderUrl,
            isNewest: $this->isNewest,
            isOldest: $this->isOldest,
            hasNewer: $this->hasNewer,
            hasOlder: $this->hasOlder,
        );
    }

    /**
     * Returns a copy with navigator availability state.
     */
    public function withNavigationState(bool $isNewest, bool $isOldest, bool $hasNewer, bool $hasOlder): self
    {
        return new self(
            title: $this->title,
            ariaLabel: $this->ariaLabel,
            method: $this->method,
            path: $this->path,
            fullUrl: $this->fullUrl,
            statusCode: $this->statusCode,
            statusVariant: $this->statusVariant,
            time: $this->time,
            isAjax: $this->isAjax,
            isCursor: $this->isCursor,
            cursorInitTag: $this->cursorInitTag,
            newestUrl: $this->newestUrl,
            oldestUrl: $this->oldestUrl,
            newerUrl: $this->newerUrl,
            olderUrl: $this->olderUrl,
            isNewest: $isNewest,
            isOldest: $isOldest,
            hasNewer: $hasNewer,
            hasOlder: $hasOlder,
        );
    }

    /**
     * Returns a copy with navigator URLs.
     */
    public function withNavigationUrls(
        string $newestUrl,
        string $oldestUrl,
        string $newerUrl,
        string $olderUrl,
    ): self {
        return new self(
            title: $this->title,
            ariaLabel: $this->ariaLabel,
            method: $this->method,
            path: $this->path,
            fullUrl: $this->fullUrl,
            statusCode: $this->statusCode,
            statusVariant: $this->statusVariant,
            time: $this->time,
            isAjax: $this->isAjax,
            isCursor: $this->isCursor,
            cursorInitTag: $this->cursorInitTag,
            newestUrl: $newestUrl,
            oldestUrl: $oldestUrl,
            newerUrl: $newerUrl,
            olderUrl: $olderUrl,
            isNewest: $this->isNewest,
            isOldest: $this->isOldest,
            hasNewer: $this->hasNewer,
            hasOlder: $this->hasOlder,
        );
    }

    /**
     * Returns a copy with request identity and URL data.
     */
    public function withRequest(
        string $method,
        string $path,
        string $fullUrl,
        string $time = '',
        bool $isAjax = false,
    ): self {
        return new self(
            title: $this->title,
            ariaLabel: $this->ariaLabel,
            method: $method,
            path: $path,
            fullUrl: $fullUrl,
            statusCode: $this->statusCode,
            statusVariant: $this->statusVariant,
            time: $time,
            isAjax: $isAjax,
            isCursor: $this->isCursor,
            cursorInitTag: $this->cursorInitTag,
            newestUrl: $this->newestUrl,
            oldestUrl: $this->oldestUrl,
            newerUrl: $this->newerUrl,
            olderUrl: $this->olderUrl,
            isNewest: $this->isNewest,
            isOldest: $this->isOldest,
            hasNewer: $this->hasNewer,
            hasOlder: $this->hasOlder,
        );
    }

    /**
     * Returns a copy with response metadata.
     */
    public function withResponse(int $statusCode, string $statusVariant): self
    {
        return new self(
            title: $this->title,
            ariaLabel: $this->ariaLabel,
            method: $this->method,
            path: $this->path,
            fullUrl: $this->fullUrl,
            statusCode: $statusCode,
            statusVariant: $statusVariant,
            time: $this->time,
            isAjax: $this->isAjax,
            isCursor: $this->isCursor,
            cursorInitTag: $this->cursorInitTag,
            newestUrl: $this->newestUrl,
            oldestUrl: $this->oldestUrl,
            newerUrl: $this->newerUrl,
            olderUrl: $this->olderUrl,
            isNewest: $this->isNewest,
            isOldest: $this->isOldest,
            hasNewer: $this->hasNewer,
            hasOlder: $this->hasOlder,
        );
    }
}

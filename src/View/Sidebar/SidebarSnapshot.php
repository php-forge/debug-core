<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Sidebar;

use PHPForge\Debug\Helper\{Text, Vocabulary};
use PHPForge\Debug\Storage\RequestSummary;

use function date;

/**
 * Typed view-model for the snapshot card surfaced at the top of the debugger sidebar ('CURRENT REQUEST' /
 * 'NEWEST REQUEST').
 *
 * The request identity is derived from the capture summary, so every host renders the method, URL, status, and time
 * of a capture the same way; the navigator row comes from the host through {@see SidebarNavigation}.
 */
final readonly class SidebarSnapshot
{
    private function __construct(
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
         * Status-pill CSS modifier ('2xx' / '3xx' / '4xx' / '5xx' / 'none') derived from `$statusCode`.
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
         * Navigator row moving away from this capture.
         */
        public SidebarNavigation $navigation,
    ) {}

    /**
     * Creates the snapshot card of one capture.
     *
     * @param RequestSummary $summary Summary of the capture the card describes.
     * @param SidebarNavigation $navigation Navigator row the host computed for that capture.
     * @param string $title Section heading shown above the card.
     * @param string|null $ariaLabel Accessible name of the section, or `null` to reuse the heading.
     *
     * @return self Snapshot card view-model.
     */
    public static function fromSummary(
        RequestSummary $summary,
        SidebarNavigation $navigation,
        string $title,
        string|null $ariaLabel = null,
    ): self {
        $unix = (int) $summary->time;

        return new self(
            title: $title,
            ariaLabel: $ariaLabel ?? $title,
            method: $summary->method,
            path: Text::urlToPath($summary->url),
            fullUrl: $summary->url,
            statusCode: $summary->statusCode,
            statusVariant: Vocabulary::statusClass($summary->statusCode),
            time: $unix > 0 ? date('H:i:s', $unix) : '',
            isAjax: $summary->ajax,
            navigation: $navigation,
        );
    }
}

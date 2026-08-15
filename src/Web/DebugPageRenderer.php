<?php

declare(strict_types=1);

namespace PHPForge\Debug\Web;

use JsonException;
use PHPForge\Debug\Asset\DebugAsset;
use PHPForge\Debug\Storage\{DebugSnapshot, PanelFailure, RequestSummary};
use PHPForge\Debug\Toolbar\ToolbarAsset;

use function array_key_exists;
use function count;
use function date;
use function htmlspecialchars;
use function in_array;
use function json_encode;
use function number_format;
use function rawurlencode;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders self-contained debugger history and snapshot pages shared by framework adapters.
 */
final readonly class DebugPageRenderer
{
    /**
     * Creates a renderer for one adapter's debug routes and version labels.
     *
     * @param string $routePrefix Route prefix serving the debugger pages.
     * @param string $yiiVersion Framework version label.
     * @param string $phpVersion PHP version label.
     */
    public function __construct(
        private string $routePrefix = '/debug',
        private string $yiiVersion = '3',
        private string $phpVersion = PHP_VERSION,
    ) {}

    /**
     * Renders captured request history.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->history($store->loadManifest());
     * ```
     *
     * @param array<string, RequestSummary> $summaries Captured requests ordered newest first.
     *
     * @return string Complete self-contained HTML document.
     */
    public function history(array $summaries): string
    {
        $rows = '';

        foreach ($summaries as $summary) {
            $url = $this->viewUrl($summary->tag, 'summary');
            $rows .= '<tr data-yii-debug-tag="' . self::escape($summary->tag) . '">'
                . '<td><a class="yii-debug-tag-link" href="' . self::escape($url) . '">'
                . self::escape($summary->tag) . '</a></td>'
                . '<td><span class="yii-debug-method yii-debug-verb-' . self::methodVariant($summary->method) . '">'
                . self::escape($summary->method) . '</span></td>'
                . '<td><span class="yii-debug-badge yii-debug-status-' . self::statusVariant($summary->statusCode)
                . '">' . $summary->statusCode . '</span></td>'
                . '<td>' . self::escape(self::duration($summary->processingTime)) . '</td>'
                . '<td>' . self::escape(self::memory($summary->peakMemory)) . '</td>'
                . '<td class="yii-debug-cell-nowrap">' . self::escape(date('Y-m-d H:i:s', (int) $summary->time))
                . '</td>'
                . '<td><a class="yii-debug-url-cell" href="' . self::escape($url) . '" title="'
                . self::escape($summary->url) . '">' . self::escape($summary->url) . '</a></td>'
                . '</tr>';
        }

        $content = '<h1 class="yii-debug-sr-only">Request history</h1>'
            . '<header class="yii-debug-grid-summary"><span><strong>' . count($summaries)
            . '</strong> Requests</span></header>';

        $content .= $rows === ''
            ? '<div class="yii-debug-empty-state">No debug snapshots are available.</div>'
            : '<div class="yii-debug-table-wrap yii-debug-grid-history"><table class="yii-debug-table">'
                . '<thead><tr><th>ID</th><th>Method</th><th>Status</th><th>Duration</th><th>Memory</th>'
                . '<th>Captured</th><th>URL</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';

        return $this->layout(
            title: 'Request history',
            sidebar: $this->historySidebar(count($summaries)),
            content: $content,
        );
    }

    /**
     * Renders one captured snapshot and its selected payload.
     *
     * Usage example:
     *
     * ```php
     * $html = $renderer->snapshot($snapshot, 'request');
     * ```
     *
     * @param DebugSnapshot $snapshot Captured request snapshot.
     * @param string|null $selectedPanel Selected panel ID or `null` for the request summary.
     *
     * @return string Complete self-contained HTML document.
     *
     * @throws JsonException When a stored payload cannot be rendered as JSON.
     */
    public function snapshot(DebugSnapshot $snapshot, string|null $selectedPanel = null): string
    {
        $panel = $selectedPanel !== null && array_key_exists($selectedPanel, $snapshot->panels)
            ? $selectedPanel
            : 'summary';
        $payload = $panel === 'summary'
            ? $snapshot->summary->jsonSerialize()
            : ($snapshot->panels[$panel] ?? []);
        $summary = $snapshot->summary;
        $content = '<h1 class="yii-debug-sr-only">' . self::escape($summary->method . ' ' . $summary->url) . '</h1>'
            . self::requestHero($summary)
            . self::metrics($summary)
            . '<h2>' . self::escape(self::panelLabel($panel)) . '</h2><pre><code>'
            . self::escape(
                json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
            )
            . '</code></pre>'
            . self::failure($snapshot->failures[$panel] ?? null);

        return $this->layout(
            title: $summary->method . ' ' . $summary->url,
            sidebar: $this->snapshotSidebar($snapshot, $panel),
            content: $content,
            peakMemory: self::memory($summary->peakMemory),
        );
    }

    /**
     * Renders the shared debugger brand bar.
     *
     * @param string|null $peakMemory Formatted peak memory or `null` on history pages.
     *
     * @return string Brand bar markup.
     */
    private function brandBar(string|null $peakMemory): string
    {
        $memory = $peakMemory === null
            ? ''
            : '<span class="yii-debug-brand-chip yii-debug-brand-chip-mem"><span class="yii-debug-brand-label">'
                . 'Memory</span><span class="yii-debug-brand-value">' . self::escape($peakMemory) . '</span></span>';
        $sun = DebugAsset::icon('sun');
        $moon = DebugAsset::icon('moon');

        return '<header class="yii-debug-brand-bar">'
            . '<a class="yii-debug-brand-chip yii-debug-brand-chip-yii" href="' . self::escape($this->routePrefix)
            . '"><span class="yii-debug-brand-icon">' . DebugAsset::icon('yii') . '</span>'
            . '<span class="yii-debug-brand-label">Yii</span><span class="yii-debug-brand-value">'
            . self::escape($this->yiiVersion) . '</span></a>'
            . '<span class="yii-debug-brand-chip yii-debug-brand-chip-php"><span class="yii-debug-brand-icon">'
            . DebugAsset::icon('php-alt') . '</span><span class="yii-debug-brand-value">'
            . self::escape($this->phpVersion) . '</span></span>'
            . $memory
            . '<a class="yii-debug-brand-chip yii-debug-brand-chip-config" href="'
            . self::escape($this->routePrefix) . '" title="View request history" aria-label="View request history">'
            . '<span class="yii-debug-brand-icon" aria-hidden="true">' . DebugAsset::icon('history') . '</span>'
            . '<span class="yii-debug-brand-label">History</span></a>'
            . '<button class="yii-debug-brand-chip yii-debug-brand-chip-theme" type="button" '
            . 'title="Toggle debug panel theme" aria-label="Toggle debug panel theme" '
            . 'data-yii-debug-theme-toggle data-current-theme="light" data-icon-sun="' . self::escape($sun)
            . '" data-icon-moon="' . self::escape($moon) . '"><span class="yii-debug-brand-icon" aria-hidden="true">'
            . $moon . '</span></button></header>';
    }

    /**
     * Formats processing duration.
     *
     * @param float|null $duration Processing duration in seconds or `null` when unavailable.
     *
     * @return string Formatted duration.
     */
    private static function duration(float|null $duration): string
    {
        return $duration === null ? 'n/a' : number_format($duration * 1000, 1) . ' ms';
    }

    /**
     * Escapes text for HTML output.
     *
     * @param string $value Raw text.
     *
     * @return string Escaped text.
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Renders a captured panel failure.
     *
     * @param PanelFailure|null $failure Captured failure or `null` when collection succeeded.
     *
     * @return string Failure markup.
     */
    private static function failure(PanelFailure|null $failure): string
    {
        return $failure === null
            ? ''
            : '<div class="yii-debug-callout yii-debug-callout-danger"><div><strong>Panel '
                . self::escape($failure->stage) . ' failed.</strong><br>'
                . self::escape((string) $failure->exception) . '</div></div>';
    }

    /**
     * Builds a readable fallback label for an adapter-defined panel ID.
     *
     * @param string $panel Panel identifier.
     *
     * @return string Human-readable panel label.
     */
    private static function fallbackPanelLabel(string $panel): string
    {
        $label = ucwords(str_replace(['_', '.', '-'], ' ', trim($panel, '_')));

        return $label !== '' ? $label : 'Panel';
    }

    /**
     * Renders the history sidebar.
     *
     * @param int $requestCount Number of captured requests.
     *
     * @return string Sidebar markup.
     */
    private function historySidebar(int $requestCount): string
    {
        return '<aside class="yii-debug-sidebar"><section class="yii-debug-side-section">'
            . '<span class="yii-debug-side-section-title">Debugger</span><div class="yii-debug-history-card">'
            . '<div class="yii-debug-snapshot-line"><span class="yii-debug-snapshot-method">History</span>'
            . '<span class="yii-debug-snapshot-url">Captured requests</span></div>'
            . '<div class="yii-debug-snapshot-meta"><span class="yii-debug-snapshot-tag">' . $requestCount
            . '</span></div></div></section>'
            . $this->navigation('summary', [], true)
            . '</aside>';
    }

    /**
     * Renders the complete debug page document.
     *
     * @param string $title Page title.
     * @param string $sidebar Sidebar markup.
     * @param string $content Main content markup.
     * @param string|null $peakMemory Formatted peak memory or `null` when unavailable.
     *
     * @return string Complete HTML document.
     */
    private function layout(string $title, string $sidebar, string $content, string|null $peakMemory = null): string
    {
        $stylesheet = str_replace('</style', '<\/style', DebugAsset::inlineStylesheet());
        $script = str_replace('</script', '<\/script', DebugAsset::script());

        return '<!doctype html><html lang="en" data-yii-debug-theme="light"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="none">'
            . '<title>' . self::escape($title) . ' — Yii Debugger</title><link rel="icon" type="image/svg+xml" href="'
            . self::escape(ToolbarAsset::yiiLogo()) . '"><style>' . $stylesheet . '</style></head>'
            . '<body class="yii-debug"><div class="yii-debug-page">' . $this->brandBar($peakMemory)
            . '<div class="yii-debug-layout">' . $sidebar . '<main class="yii-debug-main yii-debug-card">'
            . $content . '</main></div></div><script>' . $script . '</script></body></html>';
    }

    /**
     * Formats peak memory usage.
     *
     * @param int|null $bytes Peak memory in bytes or `null` when unavailable.
     *
     * @return string Formatted memory usage.
     */
    private static function memory(int|null $bytes): string
    {
        return $bytes === null ? 'n/a' : number_format($bytes / 1_048_576, 1) . ' MiB';
    }

    /**
     * Returns a semantic method class suffix.
     *
     * @param string $method HTTP request method.
     *
     * @return string Semantic method suffix.
     */
    private static function methodVariant(string $method): string
    {
        $method = strtolower($method);

        return in_array($method, ['get', 'post', 'put', 'delete'], true) ? $method : 'other';
    }

    /**
     * Renders one labeled summary metric.
     *
     * @param string $label Metric label.
     * @param string $value Metric value.
     *
     * @return string Metric markup.
     */
    private static function metric(string $label, string $value): string
    {
        return '<span><strong>' . self::escape($value) . '</strong>' . self::escape($label) . '</span>';
    }

    /**
     * Renders summary metrics for one snapshot.
     *
     * @param RequestSummary $summary Captured request metadata.
     *
     * @return string Summary strip markup.
     */
    private static function metrics(RequestSummary $summary): string
    {
        return '<header class="yii-debug-grid-summary">'
            . self::metric('Method', $summary->method)
            . '<span class="yii-debug-grid-summary-sep">·</span>'
            . self::metric('Status', (string) $summary->statusCode)
            . '<span class="yii-debug-grid-summary-sep">·</span>'
            . self::metric('Duration', self::duration($summary->processingTime))
            . '<span class="yii-debug-grid-summary-sep">·</span>'
            . self::metric('Peak memory', self::memory($summary->peakMemory))
            . '<span class="yii-debug-grid-summary-sep">·</span>'
            . self::metric('Client IP', $summary->ip === '' ? 'n/a' : $summary->ip)
            . '</header>';
    }

    /**
     * Renders snapshot panel navigation.
     *
     * @param string $selectedPanel Active panel ID.
     * @param array<string, array<string, mixed>> $panels Captured panel payloads.
     * @param bool $history Whether the history entry is active.
     * @param string $tag Captured request tag.
     *
     * @return string Navigation markup.
     */
    private function navigation(
        string $selectedPanel,
        array $panels,
        bool $history = false,
        string $tag = '',
    ): string {
        $items = $this->navigationLink(
            label: 'History',
            icon: 'history',
            url: $this->routePrefix,
            active: $history,
        );

        if (!$history) {
            $items .= $this->navigationLink(
                label: self::panelLabel('summary'),
                icon: self::panelIcon('summary'),
                url: $this->viewUrl($tag, 'summary'),
                active: $selectedPanel === 'summary',
            );

            foreach ($panels as $id => $_payload) {
                $items .= $this->navigationLink(
                    label: self::panelLabel($id),
                    icon: self::panelIcon($id),
                    url: $this->viewUrl($tag, $id),
                    active: $selectedPanel === $id,
                );
            }
        }

        return '<nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debugger panels"><ul>'
            . $items . '</ul></nav>';
    }

    /**
     * Renders one navigation link.
     *
     * @param string $label Navigation label.
     * @param string $icon Shared icon name.
     * @param string $url Navigation URL.
     * @param bool $active Whether the link represents the current page.
     *
     * @return string Navigation list item markup.
     */
    private function navigationLink(string $label, string $icon, string $url, bool $active): string
    {
        $class = 'yii-debug-nav-link' . ($active ? ' is-active' : '');
        $current = $active ? ' aria-current="page"' : '';

        return '<li><a class="' . $class . '" href="' . self::escape($url) . '"' . $current . '>'
            . '<span class="yii-debug-nav-link-icon" aria-hidden="true">' . DebugAsset::icon($icon) . '</span>'
            . '<span class="yii-debug-nav-link-label">' . self::escape($label) . '</span></a></li>';
    }

    /**
     * Returns the shared icon name for a panel.
     *
     * @param string $panel Panel identifier.
     *
     * @return string Shared icon name.
     */
    private static function panelIcon(string $panel): string
    {
        return match ($panel) {
            'summary', '_yii3.summary' => 'request',
            '_yii3.data' => 'config',
            '_yii3.objects' => 'code',
            default => 'dump',
        };
    }

    /**
     * Returns a human-readable label for a panel.
     *
     * @param string $panel Panel identifier.
     *
     * @return string Human-readable panel label.
     */
    private static function panelLabel(string $panel): string
    {
        return match ($panel) {
            'summary' => 'Request',
            '_yii3.data' => 'Collectors',
            '_yii3.objects' => 'Objects',
            '_yii3.summary' => 'Yii summary',
            default => self::fallbackPanelLabel($panel),
        };
    }

    /**
     * Renders the request hero.
     *
     * @param RequestSummary $summary Captured request metadata.
     *
     * @return string Request hero markup.
     */
    private static function requestHero(RequestSummary $summary): string
    {
        return '<div class="yii-debug-request-hero"><div class="yii-debug-request-hero-line">'
            . '<span class="yii-debug-request-hero-method yii-debug-verb-' . self::methodVariant($summary->method)
            . '">' . self::escape($summary->method) . '</span><span class="yii-debug-request-hero-url" title="'
            . self::escape($summary->url) . '">' . self::escape($summary->url) . '</span>'
            . '<span class="yii-debug-snapshot-status yii-debug-status-' . self::statusVariant($summary->statusCode)
            . '">' . $summary->statusCode . '</span></div><div class="yii-debug-request-hero-meta">'
            . '<span>' . self::escape(date('Y-m-d H:i:s', (int) $summary->time)) . '</span>'
            . '<span>' . self::escape($summary->ip === '' ? 'IP n/a' : 'IP ' . $summary->ip) . '</span>'
            . ($summary->ajax ? '<span>AJAX</span>' : '')
            . '<span>' . self::escape($summary->tag) . '</span></div></div>';
    }

    /**
     * Renders the sidebar for one snapshot.
     *
     * @param DebugSnapshot $snapshot Captured request snapshot.
     * @param string $selectedPanel Active panel ID.
     *
     * @return string Sidebar markup.
     */
    private function snapshotSidebar(DebugSnapshot $snapshot, string $selectedPanel): string
    {
        $summary = $snapshot->summary;

        return '<aside class="yii-debug-sidebar"><section class="yii-debug-side-section">'
            . '<span class="yii-debug-side-section-title">Current request</span>'
            . '<div class="yii-debug-history-card" title="' . self::escape($summary->method . ' ' . $summary->url) . '">'
            . '<div class="yii-debug-snapshot-line"><span class="yii-debug-snapshot-method yii-debug-verb-'
            . self::methodVariant($summary->method) . '">' . self::escape($summary->method) . '</span>'
            . '<span class="yii-debug-snapshot-url">' . self::escape($summary->url) . '</span></div>'
            . '<div class="yii-debug-snapshot-meta"><span class="yii-debug-snapshot-status yii-debug-status-'
            . self::statusVariant($summary->statusCode) . '">' . $summary->statusCode . '</span>'
            . '<span>' . self::escape(date('H:i:s', (int) $summary->time)) . '</span>'
            . ($summary->ajax ? '<span class="yii-debug-snapshot-tag">AJAX</span>' : '')
            . '</div></div></section>'
            . $this->navigation($selectedPanel, $snapshot->panels, tag: $summary->tag)
            . '</aside>';
    }

    /**
     * Returns a semantic status class suffix.
     *
     * @param int $statusCode HTTP response status code.
     *
     * @return string Semantic status suffix.
     */
    private static function statusVariant(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => '5xx',
            $statusCode >= 400 => '4xx',
            $statusCode >= 300 => '3xx',
            $statusCode >= 200 => '2xx',
            default => 'none',
        };
    }

    /**
     * Builds a snapshot panel URL.
     *
     * @param string $tag Captured request tag.
     * @param string $panel Panel identifier.
     *
     * @return string Snapshot panel URL.
     */
    private function viewUrl(string $tag, string $panel): string
    {
        return $this->routePrefix . '/view?tag=' . rawurlencode($tag) . '&panel=' . rawurlencode($panel);
    }
}

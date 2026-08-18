<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\History;

use PHPForge\Debug\Helper\{Format, Gauge, Vocabulary};
use PHPForge\Debug\View\Grid\RowClass;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;

use function date;
use function implode;
use function number_format;

/**
 * Renders the History index summary header + the per-cell HTML consumed by the grid columns and the typed
 * row-attributes builder.
 *
 * Stateless static helpers; every method takes a typed {@see HistoryRow} or {@see HistorySummary} and returns a
 * ready-to-echo HTML string (or, for the row-attributes builder, the attribute map the grid consumes for `<tr>`).
 * Link targets are pre-built URL strings supplied by the adapter, so the renderer stays framework-neutral.
 */
final class HistoryCellRenderer
{
    /**
     * Builds the row-attributes map for one captured-request row. The `data-*` attributes feed the sidebar's history
     * cursor.
     *
     * @param HistoryRow $row Typed history row.
     * @param bool $isCritical Whether the row's status code counts as critical (adds the danger row class).
     *
     * @return array<string, mixed>
     */
    public static function buildRowAttributes(HistoryRow $row, bool $isCritical): array
    {
        $base = $isCritical ? RowClass::for('danger') : [];

        $base['data-yii-debug-tag'] = $row->tag;
        $base['data-yii-debug-method'] = $row->method;
        $base['data-yii-debug-url'] = $row->url;
        $base['data-yii-debug-status'] = (string) $row->statusCode;
        $base['data-yii-debug-time'] = $row->timeCompact;
        $base['data-yii-debug-ajax'] = $row->ajax ? '1' : '';

        return $base;
    }

    /**
     * Renders the AJAX column cell (`'Yes'` / `'No'`).
     */
    public static function renderAjaxCell(HistoryRow $row): string
    {
        return $row->ajax ? 'Yes' : 'No';
    }

    /**
     * Renders the duration column cell (`'X ms'` or `'(not set)'` muted placeholder when missing), with a micro-gauge
     * rail scaled against the page maximum when one exists.
     *
     * @param HistoryRow $row Typed history row.
     * @param float $maxProcessingTime Page maximum in seconds ({@see HistoryScale::$maxProcessingTime}).
     */
    public static function renderDurationCell(HistoryRow $row, float $maxProcessingTime): string
    {
        if ($row->processingTime === null) {
            return Span::tag()
                ->class('yii-debug-not-set')
                ->content('(not set)')
                ->render();
        }

        return Gauge::render(
            number_format($row->processingTime * 1000) . ' ms',
            $row->processingTime,
            $maxProcessingTime,
        );
    }

    /**
     * Renders the memory column cell (`'X.XXX MB'` or `'(not set)'`), with a micro-gauge rail scaled against the page
     * maximum when one exists.
     *
     * @param HistoryRow $row Typed history row.
     * @param int $maxPeakMemory Page maximum in bytes ({@see HistoryScale::$maxPeakMemory}).
     */
    public static function renderMemoryCell(HistoryRow $row, int $maxPeakMemory): string
    {
        if ($row->peakMemory === null) {
            return Span::tag()
                ->class('yii-debug-not-set')
                ->content('(not set)')
                ->render();
        }

        return Gauge::render(
            Format::bytesToMb($row->peakMemory, 3),
            (float) $row->peakMemory,
            (float) $maxPeakMemory,
        );
    }

    /**
     * Renders the method column cell as vocabulary-colored text, or an empty string when the method was not captured.
     */
    public static function renderMethodCell(HistoryRow $row): string
    {
        if ($row->method === '') {
            return '';
        }

        return Span::tag()
            ->class('yii-debug-method yii-debug-verb-' . Vocabulary::verb($row->method))
            ->content($row->method)
            ->render();
    }

    /**
     * Renders the SQL-query column cell (count + warning chip + deep-link to the DB panel).
     *
     * @param HistoryRow $row Typed history row.
     * @param string $url Pre-built link target to the DB panel view for this request.
     * @param bool $isQueryCountCritical Whether the row's query count exceeds the critical threshold.
     * @param int $criticalQueryThreshold Threshold surfaced in the warning tooltip when the count is critical.
     */
    public static function renderSqlCountCell(
        HistoryRow $row,
        string $url,
        bool $isQueryCountCritical,
        int $criticalQueryThreshold,
    ): string {
        $title = "Executed {$row->sqlCount} database queries.";

        $warningParts = [];

        if ($isQueryCountCritical) {
            $warningParts[] = "Too many queries. Allowed count is {$criticalQueryThreshold}";
        }

        if ($row->excessiveCallersCount > 0) {
            $callerLabel = $row->excessiveCallersCount === 1 ? 'caller is' : 'callers are';
            $warningParts[] = "{$row->excessiveCallersCount} {$callerLabel} making too many calls.";
        }

        $warning = implode(' &#10;', $warningParts);

        $content = (string) $row->sqlCount;

        if ($warning !== '') {
            $warningHtml = Span::tag()
                ->title($warning)
                ->content('⚠')
                ->render();

            $content = "{$content} {$warningHtml}";
        }

        return A::tag()
            ->href($url)
            ->title($title)
            ->html($content)
            ->render();
    }

    /**
     * Renders the status-code badge cell; an uncaptured (`0`) code displays as a successful `200`.
     */
    public static function renderStatusCell(HistoryRow $row): string
    {
        $statusCode = $row->statusCode === 0 ? 200 : $row->statusCode;

        return Span::tag()
            ->class('yii-debug-badge yii-debug-status-' . Vocabulary::statusClass($statusCode))
            ->content((string) $statusCode)
            ->render();
    }

    /**
     * Renders the summary header (`<header class="yii-debug-grid-summary">`) with the request total and the
     * status-bucket pills.
     *
     * @param HistorySummary $summary Typed summary aggregate.
     * @param array<string, string> $bucketUrls Bucket-label-to-URL map for the pill deep links (missing labels render
     * without an `href`).
     * @param string $pageSizeHtml Pre-rendered page-size selector markup supplied by the adapter.
     */
    public static function renderSummary(HistorySummary $summary, array $bucketUrls, string $pageSizeHtml): string
    {
        if ($summary->totalRequests === 0) {
            return '';
        }

        $requestLabel = $summary->totalRequests === 1 ? 'captured request' : 'captured requests';

        $children = [
            Span::tag()->html(
                Strong::tag()->content((string) $summary->totalRequests),
                " {$requestLabel}",
            ),
        ];

        foreach ($summary->statusBuckets as $bucket) {
            $children[] = Span::tag()
                ->class('yii-debug-grid-summary-sep')
                ->content('·');
            $children[] = A::tag()
                ->class("yii-debug-grid-summary-stat-{$bucket->variant}")
                ->href($bucketUrls[$bucket->label] ?? '')
                ->title("Filter to {$bucket->label} responses (sample {$bucket->sampleCode})")
                ->html(Strong::tag()->content((string) $bucket->count), " {$bucket->label}");
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$children, ...[$pageSizeHtml])
            ->render();
    }

    /**
     * Renders the request-tag column cell as a link to the panel view.
     *
     * @param HistoryRow $row Typed history row.
     * @param string $url Pre-built link target to the request's panel view.
     */
    public static function renderTagCell(HistoryRow $row, string $url): string
    {
        return A::tag()
            ->class('yii-debug-tag-link')
            ->href($url)
            ->content($row->tag)
            ->render();
    }

    /**
     * Renders the time column cell — compact `HH:MM:SS` with a full `Y-m-d H:i:s` tooltip on hover.
     */
    public static function renderTimeCell(HistoryRow $row): string
    {
        if ($row->time === 0.0) {
            return Span::tag()
                ->class('yii-debug-not-set')
                ->content('(not set)')
                ->render();
        }

        $timestamp = (int) $row->time;

        return Span::tag()
            ->class('yii-debug-nowrap')
            ->title(date('Y-m-d H:i:s', $timestamp))
            ->content(date('H:i:s', $timestamp))
            ->render();
    }

    /**
     * Renders the URL column cell with a hover-truncate wrapper.
     */
    public static function renderUrlCell(HistoryRow $row): string
    {
        return Span::tag()
            ->class('yii-debug-url-cell')
            ->title($row->url)
            ->content($row->url)
            ->render();
    }
}

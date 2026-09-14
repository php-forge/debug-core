<?php

declare(strict_types=1);

namespace PHPForge\Debug\Theme;

use PHPForge\Debug\Tone;

/**
 * Publishes the debugger's shared CSS class vocabulary.
 *
 * The stylesheet ships in this package while framework adapters render into it, so every class name spelled at a call
 * site is a cross-repository contract that the language cannot check. Names used by more than one renderer, or by an
 * adapter, belong here; names private to a single renderer stay at their call site and are covered by
 * `npm run check:css-vocabulary`, which compares every emitted name against the stylesheet sources.
 */
final class Css
{
    /**
     * Status chip carrying a semantic tone.
     */
    public const string BADGE = 'yii-debug-badge';
    /**
     * Paragraph promoted to a semantic callout.
     */
    public const string CALLOUT = 'yii-debug-callout';
    /**
     * Grid cell rendered in the monospace face.
     */
    public const string CELL_MONO = 'yii-debug-cell-mono';
    /**
     * Collapsible clamp wrapping an overlong grid cell.
     */
    public const string CELL_MORE = 'yii-debug-cell-more';
    /**
     * Clamped body revealed by the cell expand toggle.
     */
    public const string CELL_MORE_BODY = 'yii-debug-cell-more-body';
    /**
     * Expand and collapse control of a clamped cell.
     */
    public const string CELL_MORE_TOGGLE = 'yii-debug-cell-more-toggle';
    /**
     * Grid cell kept on a single line.
     */
    public const string CELL_NOWRAP = 'yii-debug-cell-nowrap';
    /**
     * Grid cell aligning digits across rows.
     */
    public const string CELL_NUMERIC = 'yii-debug-cell-numeric';
    /**
     * Grid cell holding serialized payload text.
     */
    public const string CELL_PAYLOAD = 'yii-debug-cell-payload';
    /**
     * Grid cell presenting its content as a compact pill.
     */
    public const string CELL_PILL = 'yii-debug-cell-pill';
    /**
     * Collapsible section built on `<details>`.
     */
    public const string DISCLOSURE = 'yii-debug-disclosure';
    /**
     * Body revealed when a collapsible section expands.
     */
    public const string DISCLOSURE_BODY = 'yii-debug-disclosure-body';
    /**
     * Expand and collapse affordance shown in a disclosure summary.
     */
    public const string DISCLOSURE_HINT = 'yii-debug-disclosure-hint';
    /**
     * Summary row of a collapsible section.
     */
    public const string DISCLOSURE_SUMMARY = 'yii-debug-disclosure-summary';
    /**
     * Heading text inside a disclosure summary.
     */
    public const string DISCLOSURE_TITLE = 'yii-debug-disclosure-title';
    /**
     * Card shown when a panel captured nothing for the request.
     */
    public const string EMPTY_STATE = 'yii-debug-empty-state';
    /**
     * Chip reporting an extension name and its state.
     */
    public const string EXT_PILL = 'yii-debug-ext-pill';
    /**
     * Status LED of an extension pill.
     */
    public const string EXT_PILL_DOT = 'yii-debug-ext-pill-dot';
    /**
     * Extension name inside an extension pill.
     */
    public const string EXT_PILL_LABEL = 'yii-debug-ext-pill-label';
    /**
     * State text inside an extension pill.
     */
    public const string EXT_PILL_STATE = 'yii-debug-ext-pill-state';
    /**
     * Strip laying out a row of status pills.
     */
    public const string EXT_STRIP = 'yii-debug-ext-strip';
    /**
     * One label and value pair of a fact strip.
     */
    public const string FACT = 'yii-debug-fact';
    /**
     * Name of a fact.
     */
    public const string FACT_LABEL = 'yii-debug-fact-label';
    /**
     * Compact strip laying out label and value pairs.
     */
    public const string FACT_STRIP = 'yii-debug-fact-strip';
    /**
     * Recorded value of a fact.
     */
    public const string FACT_VALUE = 'yii-debug-fact-value';
    /**
     * Search input narrowing the rows of its filter scope.
     */
    public const string FILTER_INPUT = 'yii-debug-filter-input';
    /**
     * Micro-gauge rail drawn behind a numeric readout.
     */
    public const string GAUGE = 'yii-debug-gauge';
    /**
     * Filled portion of a micro-gauge.
     */
    public const string GAUGE_BAR = 'yii-debug-gauge-bar';
    /**
     * Readout text of a micro-gauge.
     */
    public const string GAUGE_VALUE = 'yii-debug-gauge-value';
    /**
     * Summary strip above a panel's content blocks.
     */
    public const string GRID_SUMMARY = 'yii-debug-grid-summary';
    /**
     * Separator between two summary-strip metrics.
     */
    public const string GRID_SUMMARY_SEP = 'yii-debug-grid-summary-sep';
    /**
     * Vendor-grouped package roster card.
     */
    public const string MANIFEST = 'yii-debug-manifest';
    /**
     * Package tally shown at the end of a manifest header.
     */
    public const string MANIFEST_COUNT = 'yii-debug-manifest-count';
    /**
     * Dense multi-column grid holding the manifest entries.
     */
    public const string MANIFEST_GRID = 'yii-debug-manifest-grid';
    /**
     * Header naming the vendor a manifest groups.
     */
    public const string MANIFEST_HEAD = 'yii-debug-manifest-head';
    /**
     * One package entry of a manifest.
     */
    public const string MANIFEST_ITEM = 'yii-debug-manifest-item';
    /**
     * Package name inside a manifest entry.
     */
    public const string MANIFEST_NAME = 'yii-debug-manifest-name';
    /**
     * Resolved version inside a manifest entry.
     */
    public const string MANIFEST_VERSION = 'yii-debug-manifest-version';
    /**
     * De-emphasized text the reader can usually skip.
     */
    public const string MUTED = 'yii-debug-muted';
    /**
     * Placeholder standing in for a value the capture did not record.
     */
    public const string NOT_SET = 'yii-debug-not-set';
    /**
     * Labeled section grouping a panel's content blocks.
     */
    public const string PANEL_GROUP = 'yii-debug-panel-group';
    /**
     * Headline card carrying one metric of a readout row.
     */
    public const string READOUT_CARD = 'yii-debug-readout-card';
    /**
     * Row of headline readout cards.
     */
    public const string READOUT_GRID = 'yii-debug-readout-grid';
    /**
     * Metric name of a readout card.
     */
    public const string READOUT_LABEL = 'yii-debug-readout-label';
    /**
     * Qualifier shown under the value of a readout card.
     */
    public const string READOUT_META = 'yii-debug-readout-meta';
    /**
     * Headline value of a readout card.
     */
    public const string READOUT_VALUE = 'yii-debug-readout-value';
    /**
     * Titled content section of a panel.
     */
    public const string SECTION = 'yii-debug-section';
    /**
     * Tally shown at the end of a section title.
     */
    public const string SECTION_COUNT = 'yii-debug-section-count';
    /**
     * Header strip introducing a content section.
     */
    public const string SECTION_HEADER = 'yii-debug-section-header';
    /**
     * Glyph shown before a section title.
     */
    public const string SECTION_MARK = 'yii-debug-section-mark';
    /**
     * Title of a content section.
     */
    public const string SECTION_TITLE = 'yii-debug-section-title';
    /**
     * Content exposed to assistive technology only.
     */
    public const string SR_ONLY = 'yii-debug-sr-only';
    /**
     * Single entry of a tab list.
     */
    public const string TAB = 'yii-debug-tab';
    /**
     * Container holding every tab panel.
     */
    public const string TAB_CONTENT = 'yii-debug-tab-content';
    /**
     * Control selecting a tab.
     */
    public const string TAB_LINK = 'yii-debug-tab-link';
    /**
     * Content region a tab reveals.
     */
    public const string TAB_PANEL = 'yii-debug-tab-panel';
    /**
     * Data grid shell.
     */
    public const string TABLE = 'yii-debug-table';
    /**
     * Data grid rendered in the monospace face.
     */
    public const string TABLE_MONO = 'yii-debug-table-mono';
    /**
     * Data grid laid out as a label and value overview.
     */
    public const string TABLE_OVERVIEW = 'yii-debug-table-overview';
    /**
     * Horizontal scroll wrapper around a data grid.
     */
    public const string TABLE_WRAP = 'yii-debug-table-wrap';
    /**
     * Tab list.
     */
    public const string TABS = 'yii-debug-tabs';
    /**
     * List of captured source frames.
     */
    public const string TRACE = 'yii-debug-trace';

    /**
     * Returns the class list of a status chip rendered in the requested tone.
     *
     * Usage example:
     * ```php
     * $class = \PHPForge\Debug\Theme\Css::badge(\PHPForge\Debug\Tone::SUCCESS);
     * ```
     *
     * @param Tone $tone Semantic tone of the chip.
     *
     * @return string Base and variant classes of the chip.
     */
    public static function badge(Tone $tone): string
    {
        return match ($tone) {
            Tone::DANGER => 'yii-debug-badge yii-debug-badge-danger',
            Tone::INFO => 'yii-debug-badge yii-debug-badge-info',
            Tone::MUTED => 'yii-debug-badge yii-debug-badge-muted',
            Tone::SUCCESS => 'yii-debug-badge yii-debug-badge-success',
            Tone::WARNING => 'yii-debug-badge yii-debug-badge-warning',
        };
    }

    /**
     * Returns the class list of a callout paragraph rendered in the requested tone.
     *
     * Only the accent border varies by tone, so the muted variant deliberately carries no rule of its own and reads
     * as the neutral base.
     *
     * Usage example:
     * ```php
     * $class = \PHPForge\Debug\Theme\Css::callout(\PHPForge\Debug\Tone::DANGER);
     * ```
     *
     * @param Tone $tone Semantic tone of the callout.
     *
     * @return string Base and variant classes of the callout.
     */
    public static function callout(Tone $tone): string
    {
        return match ($tone) {
            Tone::DANGER => 'yii-debug-callout yii-debug-callout-danger',
            Tone::INFO => 'yii-debug-callout yii-debug-callout-info',
            Tone::MUTED => 'yii-debug-callout yii-debug-callout-muted',
            Tone::SUCCESS => 'yii-debug-callout yii-debug-callout-success',
            Tone::WARNING => 'yii-debug-callout yii-debug-callout-warning',
        };
    }

    /**
     * Returns the tinted row class for a status variant.
     *
     * Usage example:
     * ```php
     * $class = \PHPForge\Debug\Theme\Css::row('warning');
     * ```
     *
     * @param string $variant Row variant: `danger`, `info`, `success`, or `warning`.
     *
     * @return string Row class carrying the variant tint.
     */
    public static function row(string $variant): string
    {
        return 'yii-debug-row-' . $variant;
    }

    /**
     * Returns the status-pill class for an HTTP status class.
     *
     * Usage example:
     * ```php
     * $class = \PHPForge\Debug\Theme\Css::status(\PHPForge\Debug\Helper\Vocabulary::statusClass(404));
     * ```
     *
     * @param string $variant Status class produced by {@see \PHPForge\Debug\Helper\Vocabulary::statusClass()}: `2xx`,
     * `3xx`, `4xx`, `5xx`, or `none`.
     *
     * @return string Status-pill class carrying the variant hue.
     */
    public static function status(string $variant): string
    {
        return 'yii-debug-status-' . $variant;
    }

    /**
     * Returns the tinted class of a summary-strip statistic.
     *
     * Usage example:
     * ```php
     * $class = \PHPForge\Debug\Theme\Css::summaryStat('warn');
     * ```
     *
     * @param string $variant Statistic variant: an HTTP status class (`2xx` to `5xx`) or a severity (`danger`,
     * `info`, `trace`, `warn`).
     *
     * @return string Summary-statistic class carrying the variant hue.
     */
    public static function summaryStat(string $variant): string
    {
        return 'yii-debug-grid-summary-stat-' . $variant;
    }

    /**
     * Returns the method-chip class for an HTTP verb.
     *
     * Usage example:
     * ```php
     * $class = \PHPForge\Debug\Theme\Css::verb(\PHPForge\Debug\Helper\Vocabulary::verb('POST'));
     * ```
     *
     * @param string $verb Verb suffix produced by {@see \PHPForge\Debug\Helper\Vocabulary::verb()}: `delete`, `get`,
     * `other`, `post`, or `put`.
     *
     * @return string Method-chip class carrying the verb hue.
     */
    public static function verb(string $verb): string
    {
        return 'yii-debug-verb-' . $verb;
    }
}

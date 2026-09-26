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
     * Grid laying out a set of entity cards.
     */
    public const string CARD_GRID = 'yii-debug-card-grid';
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
     * Card presenting one entity of a panel's inventory.
     */
    public const string ENTITY = 'yii-debug-entity';
    /**
     * Body laying out the titled columns of an entity card.
     */
    public const string ENTITY_BODY = 'yii-debug-entity-body';
    /**
     * Titled column of an entity card body.
     */
    public const string ENTITY_COLUMN = 'yii-debug-entity-column';
    /**
     * Title of an entity card column.
     */
    public const string ENTITY_COLUMN_TITLE = 'yii-debug-entity-column-title';
    /**
     * Header row of an entity card.
     */
    public const string ENTITY_HEAD = 'yii-debug-entity-head';
    /**
     * Glyph identifying an entity card.
     */
    public const string ENTITY_ICON = 'yii-debug-entity-icon';
    /**
     * Chip row summarizing an entity at the end of its header.
     */
    public const string ENTITY_META = 'yii-debug-entity-meta';
    /**
     * Headline name of an entity.
     */
    public const string ENTITY_NAME = 'yii-debug-entity-name';
    /**
     * Qualifier shown under the name of an entity.
     */
    public const string ENTITY_SUBTITLE = 'yii-debug-entity-subtitle';
    /**
     * Name and subtitle pair of an entity header.
     */
    public const string ENTITY_TITLE = 'yii-debug-entity-title';
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
     * One entry of a file list.
     */
    public const string FILE = 'yii-debug-file';
    /**
     * List of the files an entity contributes.
     */
    public const string FILE_LIST = 'yii-debug-file-list';
    /**
     * Path or URL of a file entry.
     */
    public const string FILE_NAME = 'yii-debug-file-name';
    /**
     * Kind pill of a file entry.
     */
    public const string FILE_TYPE = 'yii-debug-file-type';
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
     * Header row of a hero, holding its identity and its status.
     */
    public const string HERO_HEADER = 'yii-debug-hero-header';
    /**
     * Mark and title group of a hero header.
     */
    public const string HERO_IDENTITY = 'yii-debug-hero-identity';
    /**
     * Avatar tile carrying the short mark before the hero title.
     */
    public const string HERO_MARK = 'yii-debug-hero-mark';
    /**
     * One label and value pair of the hero metric row.
     */
    public const string HERO_METRIC = 'yii-debug-hero-metric';
    /**
     * Metric row under the hero header.
     */
    public const string HERO_METRICS = 'yii-debug-hero-metrics';
    /**
     * Status slot opposite the hero title.
     */
    public const string HERO_STATUS = 'yii-debug-hero-status';
    /**
     * Qualifier shown under the hero title.
     */
    public const string HERO_SUBTITLE = 'yii-debug-hero-subtitle';
    /**
     * Title and subtitle stack of a hero.
     */
    public const string HERO_TEXT = 'yii-debug-hero-text';
    /**
     * Subject name of a hero.
     */
    public const string HERO_TITLE = 'yii-debug-hero-title';
    /**
     * Pill-shaped cross-reference of a link strip.
     */
    public const string LINK_PILL = 'yii-debug-link-pill';
    /**
     * Labeled strip of cross-references.
     */
    public const string LINK_STRIP = 'yii-debug-link-strip';
    /**
     * Name of a link strip.
     */
    public const string LINK_STRIP_LABEL = 'yii-debug-link-strip-label';
    /**
     * Wrapper laying out the pills of a link strip.
     */
    public const string LINK_STRIP_LIST = 'yii-debug-link-strip-list';
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
     * Tile carrying one headline metric of a stat strip.
     */
    public const string STAT = 'yii-debug-stat';
    /**
     * Glyph of a stat tile.
     */
    public const string STAT_ICON = 'yii-debug-stat-icon';
    /**
     * Metric name of a stat tile.
     */
    public const string STAT_LABEL = 'yii-debug-stat-label';
    /**
     * Row of stat tiles.
     */
    public const string STAT_STRIP = 'yii-debug-stat-strip';
    /**
     * Headline value of a stat tile.
     */
    public const string STAT_VALUE = 'yii-debug-stat-value';
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
     * Returns the class list of a file-kind pill rendered in the requested tone.
     *
     * @param Tone $tone Semantic tone of the pill.
     *
     * @return string Base and variant classes of the pill.
     */
    public static function fileType(Tone $tone): string
    {
        return match ($tone) {
            Tone::DANGER => 'yii-debug-file-type yii-debug-file-type-danger',
            Tone::INFO => 'yii-debug-file-type yii-debug-file-type-info',
            Tone::MUTED => 'yii-debug-file-type yii-debug-file-type-muted',
            Tone::SUCCESS => 'yii-debug-file-type yii-debug-file-type-success',
            Tone::WARNING => 'yii-debug-file-type yii-debug-file-type-warning',
        };
    }

    /**
     * Returns the class list of a hero whose accent takes the requested tone.
     *
     * The tone sets the hue the accent rail, the mark, and the status chip share, so the muted variant deliberately
     * carries no rule of its own and reads as the neutral base.
     *
     * @param Tone $tone Semantic tone of the hero status.
     *
     * @return string Base and variant classes of the hero.
     */
    public static function hero(Tone $tone): string
    {
        return match ($tone) {
            Tone::DANGER => 'yii-debug-hero yii-debug-hero-danger',
            Tone::INFO => 'yii-debug-hero yii-debug-hero-info',
            Tone::MUTED => 'yii-debug-hero yii-debug-hero-muted',
            Tone::SUCCESS => 'yii-debug-hero yii-debug-hero-success',
            Tone::WARNING => 'yii-debug-hero yii-debug-hero-warning',
        };
    }

    /**
     * Returns the tinted row class for a status variant.
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
     * Returns the class list of a stat tile rendered in the requested tone.
     *
     * Only the glyph takes the tone, so the muted variant keeps the panel accent and reads as the neutral base.
     *
     * @param Tone $tone Semantic tone of the tile.
     *
     * @return string Base and variant classes of the tile.
     */
    public static function stat(Tone $tone): string
    {
        return match ($tone) {
            Tone::DANGER => 'yii-debug-stat yii-debug-stat-danger',
            Tone::INFO => 'yii-debug-stat yii-debug-stat-info',
            Tone::MUTED => 'yii-debug-stat yii-debug-stat-muted',
            Tone::SUCCESS => 'yii-debug-stat yii-debug-stat-success',
            Tone::WARNING => 'yii-debug-stat yii-debug-stat-warning',
        };
    }

    /**
     * Returns the status-pill class for an HTTP status class.
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

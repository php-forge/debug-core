<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\PanelView;
use PHPForge\Debug\Presenter\{
    BadgeInline,
    Block,
    CardBlock,
    ColumnEntry,
    EmptyStateBlock,
    FactsBlock,
    FilesBlock,
    GroupBlock,
    HeadingBlock,
    Inline,
    LinkInline,
    LinksBlock,
    ManifestBlock,
    OverviewBlock,
    ParagraphBlock,
    PillsBlock,
    ReadoutsBlock,
    SectionBlock,
    StatsBlock,
    SummaryMetric,
    TableBlock,
    TextInline,
};
use PHPUnit\Framework\TestCase;

/**
 * Narrows the presentation tree {@see PanelView} exports, so panel tests never assert on an unnarrowed value.
 *
 * @phpstan-require-extends TestCase
 */
trait PanelViewAccessors
{
    /**
     * @param Inline $inline Cell or field value to narrow.
     *
     * @return BadgeInline Narrowed badge.
     */
    protected static function badge(Inline $inline): BadgeInline
    {
        return $inline instanceof BadgeInline ? $inline : self::fail('The value must be a badge.');
    }

    /**
     * @param PanelView $view View to read.
     * @param int $index Position of the block in display order.
     *
     * @return Block Block declared at the requested position.
     */
    protected static function blockAt(PanelView $view, int $index): Block
    {
        return $view->blocks()[$index] ?? self::fail('The block structure must be complete.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return CardBlock Narrowed card.
     */
    protected static function card(Block $block): CardBlock
    {
        return $block instanceof CardBlock ? $block : self::fail('The block must be a card.');
    }

    /**
     * @param GroupBlock $block Group whose child view is read.
     * @param int $index Position of the block inside the group.
     *
     * @return Block Block declared at the requested position.
     */
    protected static function childBlockAt(GroupBlock $block, int $index): Block
    {
        return $block->content->blocks()[$index] ?? self::fail('The group structure must be complete.');
    }

    /**
     * @param CardBlock $block Card to read.
     * @param int $index Position of the column in display order.
     *
     * @return ColumnEntry Column declared at the requested position.
     */
    protected static function column(CardBlock $block, int $index): ColumnEntry
    {
        return $block->columns[$index] ?? self::fail('The card body must be complete.');
    }

    /**
     * @param ColumnEntry $column Column whose child view is read.
     * @param int $index Position of the block inside the column.
     *
     * @return Block Block declared at the requested position.
     */
    protected static function columnBlockAt(ColumnEntry $column, int $index): Block
    {
        return $column->content->blocks()[$index] ?? self::fail('The column structure must be complete.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return EmptyStateBlock Narrowed empty state.
     */
    protected static function emptyState(Block $block): EmptyStateBlock
    {
        return $block instanceof EmptyStateBlock ? $block : self::fail('The block must be an empty state.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return FactsBlock Narrowed fact strip.
     */
    protected static function facts(Block $block): FactsBlock
    {
        return $block instanceof FactsBlock ? $block : self::fail('The block must be a fact strip.');
    }

    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, Inline> Field values keyed by their label, in display order.
     */
    protected static function fields(OverviewBlock $block): array
    {
        $fields = [];

        foreach ($block->fields as $field) {
            $fields[$field->label] = $field->value;
        }

        return $fields;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return FilesBlock Narrowed file list.
     */
    protected static function files(Block $block): FilesBlock
    {
        return $block instanceof FilesBlock ? $block : self::fail('The block must be a file list.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return GroupBlock Narrowed group.
     */
    protected static function group(Block $block): GroupBlock
    {
        return $block instanceof GroupBlock ? $block : self::fail('The block must be a group.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return HeadingBlock Narrowed heading.
     */
    protected static function heading(Block $block): HeadingBlock
    {
        return $block instanceof HeadingBlock ? $block : self::fail('The block must be a heading.');
    }

    /**
     * @param ParagraphBlock $block Paragraph whose inline content is read.
     *
     * @return list<string> Text carried by each inline value, in display order.
     */
    protected static function inlineValues(ParagraphBlock $block): array
    {
        $values = [];

        foreach ($block->content as $inline) {
            $values[] = self::textValue($inline);
        }

        return $values;
    }

    /**
     * @param Inline $inline Cell or field value to narrow.
     *
     * @return LinkInline Narrowed link.
     */
    protected static function link(Inline $inline): LinkInline
    {
        return $inline instanceof LinkInline ? $inline : self::fail('The value must be a link.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return LinksBlock Narrowed link strip.
     */
    protected static function links(Block $block): LinksBlock
    {
        return $block instanceof LinksBlock ? $block : self::fail('The block must be a link strip.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return ManifestBlock Narrowed vendor manifest.
     */
    protected static function manifest(Block $block): ManifestBlock
    {
        return $block instanceof ManifestBlock ? $block : self::fail('The block must be a manifest.');
    }

    /**
     * @param list<SummaryMetric> $metrics Metrics in display order.
     *
     * @return list<string> Metric labels in display order.
     */
    protected static function metricLabels(array $metrics): array
    {
        $labels = [];

        foreach ($metrics as $metric) {
            $labels[] = $metric->label;
        }

        return $labels;
    }

    /**
     * @param list<SummaryMetric> $metrics Metrics in display order.
     *
     * @return list<string> Metric values in display order.
     */
    protected static function metricValues(array $metrics): array
    {
        $values = [];

        foreach ($metrics as $metric) {
            $values[] = self::textValue($metric->value);
        }

        return $values;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return OverviewBlock Narrowed overview.
     */
    protected static function overview(Block $block): OverviewBlock
    {
        return $block instanceof OverviewBlock ? $block : self::fail('The block must be an overview.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return ParagraphBlock Narrowed paragraph.
     */
    protected static function paragraph(Block $block): ParagraphBlock
    {
        return $block instanceof ParagraphBlock ? $block : self::fail('The block must be a paragraph.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return PillsBlock Narrowed pill strip.
     */
    protected static function pills(Block $block): PillsBlock
    {
        return $block instanceof PillsBlock ? $block : self::fail('The block must be a pill strip.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return ReadoutsBlock Narrowed readout row.
     */
    protected static function readouts(Block $block): ReadoutsBlock
    {
        return $block instanceof ReadoutsBlock ? $block : self::fail('The block must be a readout row.');
    }

    /**
     * @param TableBlock $block Table to read.
     * @param int $index Position of the row in display order.
     *
     * @return list<Inline> Cells of the requested row, in display order.
     */
    protected static function row(TableBlock $block, int $index): array
    {
        return $block->rows[$index] ?? self::fail('The table must describe every entry.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return SectionBlock Narrowed section.
     */
    protected static function section(Block $block): SectionBlock
    {
        return $block instanceof SectionBlock ? $block : self::fail('The block must be a section.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return StatsBlock Narrowed stat strip.
     */
    protected static function stats(Block $block): StatsBlock
    {
        return $block instanceof StatsBlock ? $block : self::fail('The block must be a stat strip.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return TableBlock Narrowed table.
     */
    protected static function table(Block $block): TableBlock
    {
        return $block instanceof TableBlock ? $block : self::fail('The block must be a table.');
    }

    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, string> Field text keyed by label, in display order.
     */
    protected static function textFields(OverviewBlock $block): array
    {
        $fields = [];

        foreach ($block->fields as $field) {
            $fields[$field->label] = self::textValue($field->value);
        }

        return $fields;
    }

    /**
     * @param Inline $inline Cell or field value to read.
     *
     * @return string Text carried by the value.
     */
    protected static function textValue(Inline $inline): string
    {
        return $inline instanceof TextInline ? $inline->value : self::fail('The value must be plain text.');
    }

    /**
     * @param list<Inline> $row Row cells in display order.
     *
     * @return list<string> Cell text in display order.
     */
    protected static function textValues(array $row): array
    {
        $values = [];

        foreach ($row as $cell) {
            $values[] = self::textValue($cell);
        }

        return $values;
    }
}

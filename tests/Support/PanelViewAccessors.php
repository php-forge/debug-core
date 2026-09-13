<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\PanelView;
use PHPUnit\Framework\TestCase;

/**
 * Provides typed readers for the shapes {@see PanelView} exports, so panel tests never index a general array.
 *
 * @phpstan-import-type BadgeInline from PanelView
 * @phpstan-import-type Block from PanelView
 * @phpstan-import-type EmptyStateBlock from PanelView
 * @phpstan-import-type GroupBlock from PanelView
 * @phpstan-import-type Inline from PanelView
 * @phpstan-import-type LinkInline from PanelView
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type Pair from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type TableBlock from PanelView
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
    protected static function badge(array $inline): array
    {
        return match ($inline['kind']) {
            'badge' => $inline,
            default => self::fail('The value must be a badge.'),
        };
    }

    /**
     * @param PanelView $view View to read.
     * @param int $index Position of the block in display order.
     *
     * @return Block Block declared at the requested position.
     */
    protected static function blockAt(PanelView $view, int $index): array
    {
        return $view->blocks()[$index] ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * @param GroupBlock $block Group whose child view is read.
     * @param int $index Position of the block inside the group.
     *
     * @return Block Block declared at the requested position.
     */
    protected static function childBlockAt(array $block, int $index): array
    {
        return $block['content']->blocks()[$index]
            ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return EmptyStateBlock Narrowed empty state.
     */
    protected static function emptyState(array $block): array
    {
        return match ($block['kind']) {
            'emptyState' => $block,
            default => self::fail('An empty capture must be explained by an empty state.'),
        };
    }

    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, Inline> Field values keyed by their label, in display order.
     */
    protected static function fields(array $block): array
    {
        $fields = [];

        foreach ($block['fields'] as $field) {
            $fields[$field['label']] = $field['value'];
        }

        return $fields;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return GroupBlock Narrowed group.
     */
    protected static function group(array $block): array
    {
        return match ($block['kind']) {
            'group' => $block,
            default => self::fail('Each detail must have an accessible group.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return array{kind: 'heading', title: string, section: bool} Narrowed heading.
     */
    protected static function heading(array $block): array
    {
        return match ($block['kind']) {
            'heading' => $block,
            default => self::fail('Each section must have a visible heading.'),
        };
    }

    /**
     * @param ParagraphBlock $block Paragraph whose inline content is read.
     *
     * @return list<string> Text carried by each inline value, in display order.
     */
    protected static function inlineValues(array $block): array
    {
        $values = [];

        foreach ($block['content'] as $inline) {
            $values[] = self::textValue($inline);
        }

        return $values;
    }

    /**
     * @param Inline $inline Cell or field value to narrow.
     *
     * @return LinkInline Narrowed link.
     */
    protected static function link(array $inline): array
    {
        return match ($inline['kind']) {
            'link' => $inline,
            default => self::fail('The value must be a link.'),
        };
    }

    /**
     * @param list<Pair> $metrics Metrics in display order.
     *
     * @return list<string> Metric values in display order.
     */
    protected static function metricValues(array $metrics): array
    {
        $values = [];

        foreach ($metrics as $metric) {
            $values[] = self::textValue($metric['value']);
        }

        return $values;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return OverviewBlock Narrowed overview.
     */
    protected static function overview(array $block): array
    {
        return match ($block['kind']) {
            'overview' => $block,
            default => self::fail('The capture must remain inspectable.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return ParagraphBlock Narrowed paragraph.
     */
    protected static function paragraph(array $block): array
    {
        return match ($block['kind']) {
            'paragraph' => $block,
            default => self::fail('The explanation must be a paragraph.'),
        };
    }

    /**
     * @param TableBlock $block Table to read.
     * @param int $index Position of the row in display order.
     *
     * @return list<Inline> Cells of the requested row, in display order.
     */
    protected static function row(array $block, int $index): array
    {
        return $block['rows'][$index] ?? self::fail('The table must describe every captured entry.');
    }




    /**
     * @param Block $block Block to narrow.
     *
     * @return TableBlock Narrowed table.
     */
    protected static function table(array $block): array
    {
        return match ($block['kind']) {
            'table' => $block,
            default => self::fail('The capture must use the shared table contract.'),
        };
    }



    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, string> Field text keyed by label, in display order.
     */
    protected static function textFields(array $block): array
    {
        $fields = [];

        foreach ($block['fields'] as $field) {
            $fields[$field['label']] = self::textValue($field['value']);
        }

        return $fields;
    }

    /**
     * @param Inline $inline Cell or field value to read.
     *
     * @return string Text carried by the value.
     */
    protected static function textValue(array $inline): string
    {
        return match ($inline['kind']) {
            'text' => $inline['value'],
            default => self::fail('The value must be plain text.'),
        };
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

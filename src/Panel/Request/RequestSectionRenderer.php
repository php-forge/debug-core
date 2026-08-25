<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\{Dump, Tabs, Vocabulary};
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};

use function htmlspecialchars;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Renders the Request panel detail view.
 */
final class RequestSectionRenderer
{
    /**
     * Renders the hero header: method pill, URL, status pill, and the `ip` / `time` / `durationMs` / flags meta strip.
     */
    public static function renderHero(RequestHero $hero): string
    {
        $line = [];

        if ($hero->method !== '') {
            $line[] = Span::tag()
                ->class('yii-debug-request-hero-method yii-debug-verb-' . Vocabulary::verb($hero->method))
                ->content($hero->method);
        }

        $line[] = Span::tag()
            ->class('yii-debug-request-hero-url')
            ->title($hero->url)
            ->content($hero->url);

        if ($hero->statusCode > 0) {
            $line[] = Span::tag()
                ->class("yii-debug-snapshot-status yii-debug-status-{$hero->statusVariant}")
                ->content((string) $hero->statusCode);
        }

        $meta = [];

        foreach (['IP' => $hero->ip, 'Time' => $hero->time, 'Duration' => $hero->durationMs] as $label => $value) {
            if ($value !== '') {
                $meta[] = Span::tag()
                    ->class('yii-debug-request-hero-meta-item')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-request-hero-meta-label')
                            ->content($label),
                        Span::tag()
                            ->class('yii-debug-request-hero-meta-value')
                            ->content($value),
                    );
            }
        }

        foreach ($hero->flags as $flag) {
            $meta[] = Span::tag()
                ->class('yii-debug-snapshot-tag')
                ->content($flag);
        }

        return Header::tag()
            ->class('yii-debug-request-hero')
            ->html(
                Div::tag()->class('yii-debug-request-hero-line')->html(...$line),
                Div::tag()->class('yii-debug-request-hero-meta')->html(...$meta),
            )
            ->render();
    }

    /**
     * Renders a single name/value section as `<header>` + `<table>`, or as an empty-state `<p>` when the section has
     * no entries.
     */
    public static function renderSection(RequestSection $section): string
    {
        $header = self::renderSectionHeader($section);

        if ($section->entries === []) {
            $emptyState = P::tag()
                ->class('yii-debug-table-empty')
                ->content('No data')
                ->render();

            return "{$header}{$emptyState}";
        }

        $table = self::renderSectionTable($section);

        return "{$header}{$table}";
    }

    /**
     * Renders the full tab strip plus the per-tab content panels, wrapping the sections returned by
     * {@see renderSection()}.
     *
     * @param list<RequestTab> $tabs Tabs in display order.
     */
    public static function renderTabs(array $tabs): string
    {
        $items = [];

        foreach ($tabs as $tab) {
            $content = '';

            foreach ($tab->sections as $section) {
                $content .= self::renderSection($section);
            }

            $items[] = ['label' => $tab->label, 'content' => $content];
        }

        return Tabs::render('request', 'Request data', $items);
    }

    /**
     * Renders one row of the section table: name in the `<th>`, value dumped via {@see Dump::asString()} in the
     * `<td>` with `htmlspecialchars` (`ENT_QUOTES | ENT_SUBSTITUTE`) escaping, so invalid byte sequences degrade to
     * substitution characters instead of blanking the row.
     */
    private static function renderRow(int|string $name, mixed $value): Tr
    {
        $valueText = Dump::asString($value);

        $escaped = htmlspecialchars($valueText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);

        return Tr::tag()
            ->html(
                Th::tag()
                    ->scope('row')
                    ->content((string) $name),
                Td::tag()->html($escaped),
            );
    }

    /**
     * Builds the `<header>` with the section caption and the optional filter input.
     */
    private static function renderSectionHeader(RequestSection $section): string
    {
        $children = [H2::tag()->content($section->caption)];

        if ($section->filterable && $section->entries !== []) {
            $children[] = InputSearch::tag()
                ->addAriaAttribute('label', "Filter {$section->caption}")
                ->addDataAttribute('yii-debug-filter', true)
                ->class('yii-debug-filter-input')
                ->placeholder('Filter…');
        }

        return Header::tag()
            ->class('yii-debug-section-header')
            ->html(...$children)
            ->render();
    }

    /**
     * Builds the section table with the name/value rows.
     */
    private static function renderSectionTable(RequestSection $section): string
    {
        $rows = [];

        foreach ($section->entries as $name => $value) {
            $rows[] = self::renderRow($name, $value);
        }

        $wrap = Div::tag()
            ->class('yii-debug-table-wrap');

        if ($section->filterable) {
            $wrap = $wrap->addDataAttribute('yii-debug-filter-target', true);
        }

        return $wrap
            ->html(
                Table::tag()
                    ->class('yii-debug-table yii-debug-table-mono')
                    ->style(['table-layout' => 'fixed'])
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()
                                    ->html(
                                        Th::tag()
                                            ->scope('col')
                                            ->content('Name'),
                                        Th::tag()
                                            ->scope('col')
                                            ->content('Value'),
                                    ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }
}

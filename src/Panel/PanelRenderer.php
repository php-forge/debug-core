<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

use PHPForge\Debug\{ColumnStyle, PanelView};
use PHPForge\Debug\Helper\{Badge, CellMore, Disclosure, EmptyState, Format, Table, Trace};
use PHPForge\Debug\Panel\Db\SqlHighlighter;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;
use UIAwesome\Html\Table\{Td, Th, Tr};

use function array_map;
use function count;
use function implode;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Renders external panel descriptions exclusively through the existing debugger frontend.
 *
 * Dispatch is exhaustive over the shapes {@see PanelView} produces: an unknown kind cannot pass static analysis
 * and a forged one raises `UnhandledMatchError`.
 *
 * @phpstan-import-type Block from PanelView
 * @phpstan-import-type Inline from PanelView
 * @phpstan-import-type LinkInline from PanelView
 * @phpstan-import-type TraceInline from PanelView
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type TableBlock from PanelView
 */
final class PanelRenderer
{
    /**
     * @param Trace $trace Renderer applied to every captured source frame.
     */
    private function __construct(private Trace $trace) {}

    /**
     * Renders one panel description through the existing debugger frontend.
     *
     * @param string $name Panel title used as the accessible heading.
     * @param PanelView $view Description to render.
     * @param Trace|null $trace Adapter-configured frame renderer, or `null` to use the default source links.
     *
     * @return string Rendered panel markup.
     */
    public static function render(string $name, PanelView $view, Trace|null $trace = null): string
    {
        return (new self($trace ?? Trace::create()))->panel($name, $view);
    }

    /**
     * Renders an inline link, opening external targets in a new browsing context.
     *
     * @param LinkInline $inline Validated inline link.
     *
     * @return string Rendered anchor.
     */
    private function anchor(array $inline): string
    {
        $anchor = A::tag()
            ->href($inline['href'])
            ->content($inline['label']);

        if ($inline['external']) {
            $anchor = $anchor
                ->rel('noopener')
                ->target('_blank');
        }

        return $anchor->render();
    }

    /**
     * @param Block $block
     */
    private function block(array $block): string
    {
        return match ($block['kind']) {
            'disclosure' => Disclosure::render(
                $block['title'],
                Pre::tag()->content($block['content'])->render(),
            ),
            'emptyState' => EmptyState::card(
                $block['title'],
                ...array_map($this->paragraph(...), $block['paragraphs']),
            ),
            'group' => Section::tag()
                ->addAriaAttribute('label', $block['label'])
                ->class('yii-debug-panel-group')
                ->html($this->blocks($block['content']->blocks()))
                ->render(),
            'heading' => $block['section']
                ? Div::tag()
                    ->class('yii-debug-section-header')
                    ->html(H2::tag()->content($block['title']))
                    ->render()
                : H2::tag()
                    ->content($block['title'])
                    ->render(),
            'overview' => $this->overview($block),
            'paragraph' => $this->paragraph($block),
            'table' => $this->table($block),
        };
    }

    /**
     * @param list<Block> $blocks
     */
    private function blocks(array $blocks): string
    {
        return implode('', array_map($this->block(...), $blocks));
    }

    /**
     * Wraps a filterable table with the in-place row filter the debugger frontend already ships.
     *
     * The scope element pairs the search input with its table, so no panel has to depend on DOM adjacency.
     *
     * @param string $label Accessible label of the table the filter narrows.
     * @param string $table Rendered table markup.
     *
     * @return string Filter scope containing the search input and the table.
     */
    private static function filterScope(string $label, string $table): string
    {
        $input = InputSearch::tag()
            ->addAriaAttribute('label', "Filter {$label}")
            ->addDataAttribute('yii-debug-filter', true)
            ->class('yii-debug-filter-input')
            ->placeholder('Filter…');

        return Div::tag()
            ->addDataAttribute('yii-debug-filter-scope', true)
            ->html(
                Header::tag()
                    ->class('yii-debug-section-header')
                    ->html($input)
                    ->render(),
                $table,
            )
            ->render();
    }

    /**
     * Renders captured source frames through the adapter-configured frame renderer.
     *
     * @param TraceInline $inline Validated inline trace.
     *
     * @return string Rendered frame list.
     */
    private function frames(array $inline): string
    {
        $items = [];

        foreach ($inline['frames'] as $frame) {
            $items[] = Li::tag()->html($this->trace->render($frame));
        }

        return Ul::tag()
            ->class('yii-debug-trace')
            ->html(...$items)
            ->render();
    }

    /**
     * @param Inline $inline
     */
    private function inline(array $inline): string
    {
        return match ($inline['kind']) {
            'badge' => Badge::render($inline['label'], $inline['tone']->value)->render(),
            'link' => $this->anchor($inline),
            'trace' => $this->frames($inline),
            'text' => match ($inline['style']) {
                'code' => Code::tag()
                    ->content($inline['value'])
                    ->render(),
                'plain' => Encode::content($inline['value']),
                'preview' => CellMore::clamp(
                    Encode::content($inline['value']),
                    $inline['value'],
                ),
                'sql' => CellMore::clamp(
                    SqlHighlighter::highlight($inline['value']),
                    $inline['value'],
                ),
                'strong' => Strong::tag()
                    ->content($inline['value'])
                    ->render(),
            },
            'value' => $inline['typeOnly']
                ? Encode::content(Format::typeOf($inline['value']))
                : $this->preview($inline['value']),
        };
    }

    /**
     * @param OverviewBlock $block
     */
    private function overview(array $block): string
    {
        $rows = [];
        foreach ($block['fields'] as $field) {
            $rows[] = Tr::tag()
                ->html(
                    Th::tag()
                        ->scope('row')
                        ->content($field['label']),
                    Td::tag()->html($this->inline($field['value'])),
                );
        }
        return Table::render(
            [],
            $rows,
            'yii-debug-table yii-debug-table-mono' . ($block['compact'] ? ' yii-debug-table-overview' : ''),
        );
    }

    /**
     * Renders the accessible heading, the summary strip, and the content blocks.
     *
     * @param string $name Panel title used as the accessible heading.
     * @param PanelView $view Description to render.
     *
     * @return string Rendered panel markup.
     */
    private function panel(string $name, PanelView $view): string
    {
        $summary = [];

        foreach ($view->summaryMetrics() as $metric) {
            if ($summary !== []) {
                $summary[] = Span::tag()
                    ->class('yii-debug-grid-summary-sep')
                    ->content('·');
            }

            $summary[] = Span::tag()->html($this->inline($metric['value']), Encode::content($metric['label']));
        }

        $heading = H1::tag()
            ->class('yii-debug-sr-only')
            ->content($name)
            ->render();

        $strip = $summary === []
            ? ''
            : Header::tag()
                ->class('yii-debug-grid-summary')
                ->html(...$summary)
                ->render();

        return $heading . $strip . $this->blocks($view->blocks());
    }

    /**
     * @param ParagraphBlock $block
     */
    private function paragraph(array $block): string
    {
        $paragraph = P::tag()->html(...array_map($this->inline(...), $block['content']));

        if ($block['tone'] !== null) {
            $paragraph = $paragraph
                ->class('yii-debug-callout yii-debug-callout-' . $block['tone']->value)
                ->role('status');
        }

        return $paragraph->render();
    }

    private function preview(mixed $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return CellMore::clamp(Encode::content($json), $json);
    }

    /**
     * @param TableBlock $block
     */
    private function table(array $block): string
    {
        $rows = [];

        foreach ($block['rows'] as $row) {
            $cells = [];

            foreach ($row as $column => $inline) {
                $style = $block['styles'][$column] ?? ColumnStyle::PLAIN;

                $class = match ($style) {
                    ColumnStyle::PLAIN => '',
                    ColumnStyle::MONOSPACE => 'yii-debug-cell-mono',
                    ColumnStyle::IDENTIFIER => 'yii-debug-cell-mono yii-debug-cell-nowrap',
                    ColumnStyle::NUMBER => 'yii-debug-cell-numeric',
                    ColumnStyle::PILL => 'yii-debug-cell-pill',
                    ColumnStyle::PAYLOAD => 'yii-debug-cell-mono yii-debug-cell-payload',
                };

                $value = $this->inline($inline);

                if ($style === ColumnStyle::PILL && $inline['kind'] === 'text') {
                    $value = Span::tag()
                        ->html($value)
                        ->render();
                }

                $tag = Td::tag()->html($value);

                $cells[] = $class === '' ? $tag : $tag->class($class);
            }

            $rows[] = Tr::tag()->html(...$cells);
        }

        $label = implode(', ', $block['headers']);

        $wrap = Div::tag()
            ->addAriaAttribute('label', $label)
            ->addAttribute('tabindex', 0)
            ->class('yii-debug-table-wrap')
            ->role('region')
            ->html(Table::build($block['headers'], $rows));

        if ($block['filterable']) {
            $wrap = $wrap->addDataAttribute('yii-debug-filter-target', true);
        }

        $html = $wrap->render();

        if ($block['collapsible'] && count($rows) > CellMore::ROW_THRESHOLD) {
            $html = CellMore::wrap($html);
        }

        return $block['filterable'] ? self::filterScope($label, $html) : $html;
    }

}

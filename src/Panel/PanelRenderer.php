<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

use PHPForge\Debug\{ColumnStyle, PanelView};
use PHPForge\Debug\Helper\{Badge, CellMore, Disclosure, EmptyState, ExtensionPill, Format, Table, Trace};
use PHPForge\Debug\Panel\Db\SqlHighlighter;
use PHPForge\Debug\Theme\Css;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Helper\Encode;
use UIAwesome\Html\List\{Dd, Dl, Dt, Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Article, Section};
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
 * @phpstan-import-type FactsBlock from PanelView
 * @phpstan-import-type ManifestBlock from PanelView
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type PillsBlock from PanelView
 * @phpstan-import-type ReadoutsBlock from PanelView
 * @phpstan-import-type SectionBlock from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type TableBlock from PanelView
 */
final class PanelRenderer
{
    /**
     * Class list of a cell holding a short machine name that must stay on one line.
     */
    private const string CELL_IDENTIFIER_CLASS = Css::CELL_MONO . ' ' . Css::CELL_NOWRAP;
    /**
     * Class list of a cell holding serialized payload text.
     */
    private const string CELL_PAYLOAD_CLASS = Css::CELL_MONO . ' ' . Css::CELL_PAYLOAD;
    /**
     * Class list of a data grid laid out as a label and value overview.
     */
    private const string TABLE_COMPACT_CLASS = Css::TABLE . ' ' . Css::TABLE_MONO . ' ' . Css::TABLE_OVERVIEW;
    /**
     * Class list of a data grid rendered in the monospace face.
     */
    private const string TABLE_MONO_CLASS = Css::TABLE . ' ' . Css::TABLE_MONO;

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
                ->class(Css::PANEL_GROUP)
                ->html($this->blocks($block['content']->blocks()))
                ->render(),
            'heading' => $block['section']
                ? Div::tag()
                    ->class(Css::SECTION_HEADER)
                    ->html(H2::tag()->content($block['title']))
                    ->render()
                : H2::tag()
                    ->content($block['title'])
                    ->render(),
            'facts' => self::facts($block),
            'manifest' => self::manifest($block),
            'overview' => $this->overview($block),
            'paragraph' => $this->paragraph($block),
            'pills' => self::pills($block),
            'readouts' => self::readouts($block),
            'section' => $this->section($block),
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
     * Renders the compact strip of label and value pairs.
     *
     * @param FactsBlock $block Validated fact strip.
     *
     * @return string Rendered fact strip.
     */
    private static function facts(array $block): string
    {
        $facts = [];

        foreach ($block['facts'] as $fact) {
            $facts[] = Div::tag()
                ->class(Css::FACT)
                ->html(
                    Dt::tag()
                        ->class(Css::FACT_LABEL)
                        ->content($fact['label']),
                    Dd::tag()
                        ->class(Css::FACT_VALUE)
                        ->title($fact['value'])
                        ->content($fact['value']),
                );
        }

        return Dl::tag()
            ->class(Css::FACT_STRIP)
            ->html(...$facts)
            ->render();
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
            ->class(Css::FILTER_INPUT)
            ->placeholder('Filter…');

        return Div::tag()
            ->addDataAttribute('yii-debug-filter-scope', true)
            ->html(
                Header::tag()
                    ->class(Css::SECTION_HEADER)
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
            ->class(Css::TRACE)
            ->html(...$items)
            ->render();
    }

    /**
     * @param Inline $inline
     */
    private function inline(array $inline): string
    {
        return match ($inline['kind']) {
            'badge' => Badge::render($inline['label'], $inline['tone'])->render(),
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
     * Renders the vendor-grouped package roster.
     *
     * @param ManifestBlock $block Validated manifest block.
     *
     * @return string Rendered manifest card.
     */
    private static function manifest(array $block): string
    {
        $items = [];

        foreach ($block['packages'] as $package) {
            $items[] = Div::tag()
                ->class(Css::MANIFEST_ITEM)
                ->html(
                    Span::tag()
                        ->class(Css::MANIFEST_NAME)
                        ->content($package['name']),
                    Span::tag()
                        ->class(Css::MANIFEST_VERSION)
                        ->content($package['version']),
                );
        }

        $total = count($items);

        return Section::tag()
            ->addAriaAttribute('label', $block['label'])
            ->class(Css::MANIFEST)
            ->html(
                Header::tag()
                    ->class(Css::MANIFEST_HEAD)
                    ->html(
                        Span::tag()->content($block['label']),
                        Span::tag()
                            ->class(Css::MANIFEST_COUNT)
                            ->content($total === 1 ? '1 package' : "{$total} packages"),
                    ),
                Div::tag()
                    ->class(Css::MANIFEST_GRID)
                    ->html(...$items),
            )
            ->render();
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
            $block['compact'] ? self::TABLE_COMPACT_CLASS : self::TABLE_MONO_CLASS,
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
                    ->class(Css::GRID_SUMMARY_SEP)
                    ->content('·');
            }

            $summary[] = Span::tag()->html($this->inline($metric['value']), Encode::content($metric['label']));
        }

        $heading = H1::tag()
            ->class(Css::SR_ONLY)
            ->content($name)
            ->render();

        $strip = $summary === []
            ? ''
            : Header::tag()
                ->class(Css::GRID_SUMMARY)
                ->html(...$summary)
                ->render();

        return "{$heading}{$strip}" . $this->blocks($view->blocks());
    }

    /**
     * @param ParagraphBlock $block
     */
    private function paragraph(array $block): string
    {
        $paragraph = P::tag()->html(...array_map($this->inline(...), $block['content']));

        if ($block['tone'] !== null) {
            $paragraph = $paragraph
                ->class(Css::callout($block['tone']))
                ->role('status');
        }

        return $paragraph->render();
    }

    /**
     * Renders the strip of status pills.
     *
     * @param PillsBlock $block Validated pill strip.
     *
     * @return string Rendered pill strip.
     */
    private static function pills(array $block): string
    {
        $pills = [];

        foreach ($block['pills'] as $pill) {
            $pills[] = ExtensionPill::render($pill['label'], $pill['state'], $pill['enabled']);
        }

        return Div::tag()
            ->class(Css::EXT_STRIP)
            ->html(...$pills)
            ->render();
    }

    private function preview(mixed $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return CellMore::clamp(Encode::content($json), $json);
    }

    /**
     * Renders the row of headline readout cards.
     *
     * @param ReadoutsBlock $block Validated readout row.
     *
     * @return string Rendered readout row.
     */
    private static function readouts(array $block): string
    {
        $cards = [];

        foreach ($block['readouts'] as $readout) {
            $parts = [
                Span::tag()
                    ->class(Css::READOUT_LABEL)
                    ->content($readout['label']),
                Span::tag()
                    ->class(Css::READOUT_VALUE)
                    ->content($readout['value']),
            ];

            if ($readout['caption'] !== '') {
                $parts[] = Span::tag()
                    ->class(Css::READOUT_META)
                    ->content($readout['caption']);
            }

            $cards[] = Article::tag()
                ->class(Css::READOUT_CARD)
                ->html(...$parts);
        }

        return Div::tag()
            ->class(Css::READOUT_GRID)
            ->html(...$cards)
            ->render();
    }

    /**
     * Renders a titled section wrapping its own blocks.
     *
     * @param SectionBlock $block Validated section.
     *
     * @return string Rendered section.
     */
    private function section(array $block): string
    {
        $title = [
            Span::tag()
                ->class(Css::SECTION_MARK)
                ->content($block['mark']),
            Encode::content($block['title']),
        ];

        if ($block['count'] !== null) {
            $title[] = Span::tag()
                ->class(Css::SECTION_COUNT)
                ->content((string) $block['count']);
        }

        return Section::tag()
            ->addAriaAttribute('label', $block['title'])
            ->class(Css::SECTION)
            ->html(
                H2::tag()
                    ->class(Css::SECTION_TITLE)
                    ->html(...$title),
                $this->blocks($block['content']->blocks()),
            )
            ->render();
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
                    ColumnStyle::MONOSPACE => Css::CELL_MONO,
                    ColumnStyle::IDENTIFIER => self::CELL_IDENTIFIER_CLASS,
                    ColumnStyle::NUMBER => Css::CELL_NUMERIC,
                    ColumnStyle::PILL => Css::CELL_PILL,
                    ColumnStyle::PAYLOAD => self::CELL_PAYLOAD_CLASS,
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
            ->class(Css::TABLE_WRAP)
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

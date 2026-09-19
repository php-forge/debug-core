<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

use JsonException;
use PHPForge\Debug\{ColumnStyle, PanelView};
use PHPForge\Debug\Helper\{Badge, CellMore, Disclosure, EmptyState, ExtensionPill, Format, Icon, Table, Trace};
use PHPForge\Debug\Panel\Db\SqlHighlighter;
use PHPForge\Debug\Presenter\{
    BadgeInline,
    Block,
    CardBlock,
    DisclosureBlock,
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
    TableBlock,
    TextInline,
    TextStyle,
    TraceInline,
    ValueInline,
};
use PHPForge\Debug\Theme\Css;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\{H1, H2, H3};
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
 * Dispatch narrows the sealed {@see Block} and {@see Inline} unions with `instanceof`, so static analysis proves that
 * every value {@see PanelView} produces reaches a renderer.
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
     * @param string $class Class list of the anchor, or `''` to leave it unstyled.
     *
     * @return string Rendered anchor.
     */
    private function anchor(LinkInline $inline, string $class): string
    {
        $anchor = A::tag()
            ->href($inline->href)
            ->content($inline->label);

        if ($class !== '') {
            $anchor = $anchor->class($class);
        }

        if ($inline->external) {
            $anchor = $anchor
                ->rel('noopener')
                ->target('_blank');
        }

        return $anchor->render();
    }

    /**
     * Renders one content block through the renderer its type selects.
     *
     * @param Block $block Block to render.
     *
     * @return string Rendered block.
     */
    private function block(Block $block): string
    {
        return match (true) {
            $block instanceof CardBlock => $this->card($block),
            $block instanceof DisclosureBlock => Disclosure::render(
                $block->title,
                Pre::tag()->content($block->content)->render(),
            ),
            $block instanceof EmptyStateBlock => EmptyState::card(
                $block->title,
                ...array_map($this->paragraph(...), $block->paragraphs),
            ),
            $block instanceof FactsBlock => self::facts($block),
            $block instanceof FilesBlock => self::files($block),
            $block instanceof GroupBlock => Section::tag()
                ->addAriaAttribute('label', $block->label)
                ->class(Css::PANEL_GROUP)
                ->html($this->blocks($block->content->blocks()))
                ->render(),
            $block instanceof HeadingBlock => $block->section
                ? Div::tag()
                    ->class(Css::SECTION_HEADER)
                    ->html(H2::tag()->content($block->title))
                    ->render()
                : H2::tag()
                    ->content($block->title)
                    ->render(),
            $block instanceof LinksBlock => $this->links($block),
            $block instanceof ManifestBlock => self::manifest($block),
            $block instanceof OverviewBlock => $this->overview($block),
            $block instanceof ParagraphBlock => $this->paragraph($block),
            $block instanceof PillsBlock => self::pills($block),
            $block instanceof ReadoutsBlock => self::readouts($block),
            $block instanceof SectionBlock => $this->section($block),
            $block instanceof StatsBlock => self::stats($block),
            $block instanceof TableBlock => $this->table($block),
        };
    }

    /**
     * Renders the content blocks in display order.
     *
     * @param list<Block> $blocks Blocks to render.
     *
     * @return string Concatenated block markup.
     */
    private function blocks(array $blocks): string
    {
        return implode('', array_map($this->block(...), $blocks));
    }

    /**
     * Renders one entity as a card: an identifying header and, when the entity has content, its titled columns.
     *
     * @param CardBlock $block Validated card.
     *
     * @return string Rendered card.
     */
    private function card(CardBlock $block): string
    {
        $title = [
            H2::tag()
                ->class(Css::ENTITY_NAME)
                ->content($block->title),
        ];

        if ($block->subtitle !== '') {
            $title[] = Span::tag()
                ->class(Css::ENTITY_SUBTITLE)
                ->content($block->subtitle);
        }

        $head = [];

        if ($block->icon !== '') {
            $head[] = Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class(Css::ENTITY_ICON)
                ->html(Icon::render($block->icon));
        }

        $head[] = Div::tag()
            ->class(Css::ENTITY_TITLE)
            ->html(...$title);

        if ($block->meta !== []) {
            $head[] = Div::tag()
                ->class(Css::ENTITY_META)
                ->html(...array_map($this->inline(...), $block->meta));
        }

        $parts = [
            Header::tag()
                ->class(Css::ENTITY_HEAD)
                ->html(...$head),
        ];

        if ($block->columns !== []) {
            $columns = [];

            foreach ($block->columns as $column) {
                $columns[] = Section::tag()
                    ->addAriaAttribute('label', $column->title)
                    ->class(Css::ENTITY_COLUMN)
                    ->html(
                        H3::tag()
                            ->class(Css::ENTITY_COLUMN_TITLE)
                            ->content($column->title),
                        $this->blocks($column->content->blocks()),
                    );
            }

            $parts[] = Div::tag()
                ->addDataAttribute('cols', (string) count($columns))
                ->class(Css::ENTITY_BODY)
                ->html(...$columns);
        }

        $card = Article::tag()
            ->class(Css::ENTITY)
            ->html(...$parts);

        return ($block->id === '' ? $card : $card->id($block->id))->render();
    }

    /**
     * Renders the compact strip of label and value pairs.
     *
     * @param FactsBlock $block Validated fact strip.
     *
     * @return string Rendered fact strip.
     */
    private static function facts(FactsBlock $block): string
    {
        $facts = [];

        foreach ($block->facts as $fact) {
            $facts[] = Div::tag()
                ->class(Css::FACT)
                ->html(
                    Dt::tag()
                        ->class(Css::FACT_LABEL)
                        ->content($fact->label),
                    Dd::tag()
                        ->class(Css::FACT_VALUE)
                        ->title($fact->value)
                        ->content($fact->value),
                );
        }

        return Dl::tag()
            ->class(Css::FACT_STRIP)
            ->html(...$facts)
            ->render();
    }

    /**
     * Renders the list of typed file names.
     *
     * @param FilesBlock $block Validated file list.
     *
     * @return string Rendered file list.
     */
    private static function files(FilesBlock $block): string
    {
        $items = [];

        foreach ($block->files as $file) {
            $items[] = Li::tag()
                ->class(Css::FILE)
                ->html(
                    Span::tag()
                        ->class(Css::fileType($file->tone))
                        ->content($file->type),
                    Span::tag()
                        ->class(Css::FILE_NAME)
                        ->title($file->name)
                        ->content($file->name),
                );
        }

        return Ul::tag()
            ->class(Css::FILE_LIST)
            ->html(...$items)
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
    private function frames(TraceInline $inline): string
    {
        $items = [];

        foreach ($inline->frames as $frame) {
            $items[] = Li::tag()->html($this->trace->render($frame));
        }

        return Ul::tag()
            ->class(Css::TRACE)
            ->html(...$items)
            ->render();
    }

    /**
     * Renders one inline value through the renderer its type selects.
     *
     * @param Inline $inline Value to render.
     *
     * @return string Rendered inline markup.
     */
    private function inline(Inline $inline): string
    {
        return match (true) {
            $inline instanceof BadgeInline => Badge::render($inline->label, $inline->tone)->render(),
            $inline instanceof LinkInline => $this->anchor($inline, ''),
            $inline instanceof TextInline => match ($inline->style) {
                TextStyle::CODE => Code::tag()
                    ->content($inline->value)
                    ->render(),
                TextStyle::PLAIN => Encode::content($inline->value),
                TextStyle::PREVIEW => CellMore::clamp(
                    Encode::content($inline->value),
                    $inline->value,
                ),
                TextStyle::SQL => CellMore::clamp(
                    SqlHighlighter::highlight($inline->value),
                    $inline->value,
                ),
                TextStyle::STRONG => Strong::tag()
                    ->content($inline->value)
                    ->render(),
            },
            $inline instanceof TraceInline => $this->frames($inline),
            $inline instanceof ValueInline => $inline->typeOnly
                ? Encode::content(Format::typeOf($inline->value))
                : $this->preview($inline->value),
        };
    }

    /**
     * Renders the labeled strip of navigation links as pills.
     *
     * @param LinksBlock $block Validated link strip.
     *
     * @return string Rendered link strip.
     */
    private function links(LinksBlock $block): string
    {
        $pills = [];

        foreach ($block->links as $link) {
            $pills[] = $this->anchor($link, Css::LINK_PILL);
        }

        return Div::tag()
            ->class(Css::LINK_STRIP)
            ->html(
                Span::tag()
                    ->class(Css::LINK_STRIP_LABEL)
                    ->content($block->label),
                Div::tag()
                    ->class(Css::LINK_STRIP_LIST)
                    ->html(...$pills),
            )
            ->render();
    }

    /**
     * Renders the vendor-grouped package roster.
     *
     * @param ManifestBlock $block Validated manifest block.
     *
     * @return string Rendered manifest card.
     */
    private static function manifest(ManifestBlock $block): string
    {
        $items = [];

        foreach ($block->packages as $package) {
            $items[] = Div::tag()
                ->class(Css::MANIFEST_ITEM)
                ->html(
                    Span::tag()
                        ->class(Css::MANIFEST_NAME)
                        ->content($package->name),
                    Span::tag()
                        ->class(Css::MANIFEST_VERSION)
                        ->content($package->version),
                );
        }

        $total = count($items);

        return Section::tag()
            ->addAriaAttribute('label', $block->label)
            ->class(Css::MANIFEST)
            ->html(
                Header::tag()
                    ->class(Css::MANIFEST_HEAD)
                    ->html(
                        Span::tag()->content($block->label),
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
     * Renders the labeled fields as a two-column data grid.
     *
     * @param OverviewBlock $block Validated overview.
     *
     * @return string Rendered overview grid.
     */
    private function overview(OverviewBlock $block): string
    {
        $rows = [];
        foreach ($block->fields as $field) {
            $rows[] = Tr::tag()
                ->html(
                    Th::tag()
                        ->scope('row')
                        ->content($field->label),
                    Td::tag()->html($this->inline($field->value)),
                );
        }
        return Table::render(
            [],
            $rows,
            $block->compact ? self::TABLE_COMPACT_CLASS : self::TABLE_MONO_CLASS,
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

            $summary[] = Span::tag()->html($this->inline($metric->value), Encode::content($metric->label));
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
     * Renders one paragraph, adding the callout presentation when the block declares a tone.
     *
     * @param ParagraphBlock $block Validated paragraph.
     *
     * @return string Rendered paragraph.
     */
    private function paragraph(ParagraphBlock $block): string
    {
        $paragraph = P::tag()->html(...array_map($this->inline(...), $block->content));

        if ($block->tone !== null) {
            $paragraph = $paragraph
                ->class(Css::callout($block->tone))
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
    private static function pills(PillsBlock $block): string
    {
        $pills = [];

        foreach ($block->pills as $pill) {
            $pills[] = ExtensionPill::render($pill->label, $pill->state, $pill->enabled);
        }

        return Div::tag()
            ->class(Css::EXT_STRIP)
            ->html(...$pills)
            ->render();
    }

    /**
     * Renders an inline value as its JSON text, clamped when it overflows the cell.
     *
     * @param mixed $value Value to encode.
     *
     * @throws JsonException When the value cannot be encoded.
     *
     * @return string Encoded JSON document, clamped behind the cell expander when it is long.
     */
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
    private static function readouts(ReadoutsBlock $block): string
    {
        $cards = [];

        foreach ($block->readouts as $readout) {
            $parts = [
                Span::tag()
                    ->class(Css::READOUT_LABEL)
                    ->content($readout->label),
                Span::tag()
                    ->class(Css::READOUT_VALUE)
                    ->content($readout->value),
            ];

            if ($readout->caption !== '') {
                $parts[] = Span::tag()
                    ->class(Css::READOUT_META)
                    ->content($readout->caption);
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
    private function section(SectionBlock $block): string
    {
        $title = [
            Span::tag()
                ->class(Css::SECTION_MARK)
                ->content($block->mark),
            Encode::content($block->title),
        ];

        if ($block->count !== null) {
            $title[] = Span::tag()
                ->class(Css::SECTION_COUNT)
                ->content((string) $block->count);
        }

        return Section::tag()
            ->addAriaAttribute('label', $block->title)
            ->class(Css::SECTION)
            ->html(
                H2::tag()
                    ->class(Css::SECTION_TITLE)
                    ->html(...$title),
                $this->blocks($block->content->blocks()),
            )
            ->render();
    }

    /**
     * Renders the strip of headline stat tiles.
     *
     * @param StatsBlock $block Validated stat strip.
     *
     * @return string Rendered stat strip.
     */
    private static function stats(StatsBlock $block): string
    {
        $tiles = [];

        foreach ($block->stats as $stat) {
            $tiles[] = Div::tag()
                ->class(Css::stat($stat->tone))
                ->html(
                    Span::tag()
                        ->addAriaAttribute('hidden', 'true')
                        ->class(Css::STAT_ICON)
                        ->html(Icon::render($stat->icon)),
                    Strong::tag()
                        ->class(Css::STAT_VALUE)
                        ->content($stat->value),
                    Span::tag()
                        ->class(Css::STAT_LABEL)
                        ->content($stat->label),
                );
        }

        return Div::tag()
            ->class(Css::STAT_STRIP)
            ->html(...$tiles)
            ->render();
    }

    /**
     * Renders the data grid, applying the declared column style to every cell.
     *
     * @param TableBlock $block Validated table.
     *
     * @return string Rendered table, wrapped in the filter scope when the table declares one.
     */
    private function table(TableBlock $block): string
    {
        $rows = [];

        foreach ($block->rows as $row) {
            $cells = [];

            foreach ($row as $column => $inline) {
                $style = $block->styles[$column] ?? ColumnStyle::PLAIN;

                $class = match ($style) {
                    ColumnStyle::PLAIN => '',
                    ColumnStyle::MONOSPACE => Css::CELL_MONO,
                    ColumnStyle::IDENTIFIER => self::CELL_IDENTIFIER_CLASS,
                    ColumnStyle::NUMBER => Css::CELL_NUMERIC,
                    ColumnStyle::PILL => Css::CELL_PILL,
                    ColumnStyle::PAYLOAD => self::CELL_PAYLOAD_CLASS,
                };

                $value = $this->inline($inline);

                if ($style === ColumnStyle::PILL && $inline instanceof TextInline) {
                    $value = Span::tag()
                        ->html($value)
                        ->render();
                }

                $tag = Td::tag()->html($value);

                $cells[] = $class === '' ? $tag : $tag->class($class);
            }

            $rows[] = Tr::tag()->html(...$cells);
        }

        $label = implode(', ', $block->headers);

        $wrap = Div::tag()
            ->addAriaAttribute('label', $label)
            ->addAttribute('tabindex', 0)
            ->class(Css::TABLE_WRAP)
            ->role('region')
            ->html(Table::build($block->headers, $rows));

        if ($block->filterable) {
            $wrap = $wrap->addDataAttribute('yii-debug-filter-target', true);
        }

        $html = $wrap->render();

        if ($block->collapsible && count($rows) > CellMore::ROW_THRESHOLD) {
            $html = CellMore::wrap($html);
        }

        return $block->filterable ? self::filterScope($label, $html) : $html;
    }

}

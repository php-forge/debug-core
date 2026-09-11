<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel;

use PHPForge\Debug\{ColumnStyle, PanelView};
use PHPForge\Debug\Helper\{Badge, CellMore, Disclosure, EmptyState, Format, Table};
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Helper\Encode;
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
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type TableBlock from PanelView
 */
final class PanelRenderer
{
    public static function render(string $name, PanelView $view): string
    {
        $summary = [];

        foreach ($view->summaryMetrics() as $metric) {
            if ($summary !== []) {
                $summary[] = Span::tag()
                    ->class('yii-debug-grid-summary-sep')
                    ->content('·');
            }

            $summary[] = Span::tag()->html(self::inline($metric['value']), Encode::content($metric['label']));
        }

        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content($name)
            ->render()
            . Header::tag()
                ->class('yii-debug-grid-summary')
                ->html(...$summary)
                ->render()
            . self::blocks($view->blocks());
    }

    /**
     * @param Block $block
     */
    private static function block(array $block): string
    {
        return match ($block['kind']) {
            'disclosure' => Disclosure::render($block['title'], Pre::tag()->content($block['content'])->render()),
            'emptyState' => EmptyState::card($block['title'], ...array_map(self::paragraph(...), $block['paragraphs'])),
            'group' => Section::tag()
                ->addAriaAttribute('label', $block['label'])
                ->class('yii-debug-panel-group')
                ->html(self::blocks($block['content']->blocks()))
                ->render(),
            'heading' => $block['section']
                ? Div::tag()
                    ->class('yii-debug-section-header')
                    ->html(H2::tag()->content($block['title']))
                    ->render()
                : H2::tag()
                    ->content($block['title'])
                    ->render(),
            'overview' => self::overview($block),
            'paragraph' => self::paragraph($block),
            'table' => self::table($block),
        };
    }

    /**
     * @param list<Block> $blocks
     */
    private static function blocks(array $blocks): string
    {
        return implode('', array_map(self::block(...), $blocks));
    }

    /**
     * @param Inline $inline
     */
    private static function inline(array $inline): string
    {
        return match ($inline['kind']) {
            'badge' => Badge::render($inline['label'], $inline['tone']->value)->render(),
            'text' => match ($inline['style']) {
                'code' => Code::tag()
                    ->content($inline['value'])
                    ->render(),
                'plain' => Encode::content($inline['value']),
                'preview' => CellMore::clamp(Encode::content($inline['value']), $inline['value']),
                'strong' => Strong::tag()
                    ->content($inline['value'])
                    ->render(),
            },
            'value' => $inline['typeOnly']
                ? Encode::content(Format::typeOf($inline['value']))
                : self::preview($inline['value']),
        };
    }

    /**
     * @param OverviewBlock $block
     */
    private static function overview(array $block): string
    {
        $rows = [];
        foreach ($block['fields'] as $field) {
            $rows[] = Tr::tag()
                ->html(
                    Th::tag()
                        ->scope('row')
                        ->content($field['label']),
                    Td::tag()->html(self::inline($field['value'])),
                );
        }
        return Table::render(
            [],
            $rows,
            'yii-debug-table yii-debug-table-mono' . ($block['compact'] ? ' yii-debug-table-overview' : ''),
        );
    }

    /**
     * @param ParagraphBlock $block
     */
    private static function paragraph(array $block): string
    {
        $paragraph = P::tag()->html(...array_map(self::inline(...), $block['content']));

        if ($block['tone'] !== null) {
            $paragraph = $paragraph
                ->class('yii-debug-callout yii-debug-callout-' . $block['tone']->value)
                ->role('status');
        }

        return $paragraph->render();
    }

    private static function preview(mixed $value): string
    {
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return CellMore::clamp(Encode::content($json), $json);
    }

    /**
     * @param TableBlock $block
     */
    private static function table(array $block): string
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

                $value = self::inline($inline);

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

        $html = Div::tag()
            ->addAriaAttribute('label', implode(', ', $block['headers']))
            ->addAttribute('tabindex', 0)
            ->class('yii-debug-table-wrap')
            ->role('region')
            ->html(Table::build($block['headers'], $rows))
            ->render();

        return $block['collapsible'] && count($rows) > CellMore::ROW_THRESHOLD ? CellMore::wrap($html) : $html;
    }
}

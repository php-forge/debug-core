<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Timeline;

use PHPForge\Debug\Helper\{Format, Fqcn};
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\{Button, Form, InputHidden, InputNumber, InputText};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Em, Label, Span, Strong};
use UIAwesome\Html\Root\{Footer, Header};
use UIAwesome\Html\Sectioning\Section;

use function count;
use function number_format;
use function rtrim;
use function sprintf;

/**
 * Renders the shared Timeline summary, filters, empty hint, and positioned span chart.
 */
final class TimelineRenderer
{
    private const array LEGEND_LABELS = [
        'app' => 'Application',
        'db' => 'Database',
        'view' => 'View',
        'cache' => 'Cache',
        'mail' => 'Mail',
        'queue' => 'Queue',
        'other' => 'Other',
    ];

    /**
     * Renders the complete timeline chart from prepared spans and ruler offsets.
     *
     * @param list<TimelineSpanRow> $rows Positioned timeline spans.
     * @param array<int, float> $rulers Ruler offsets keyed by milliseconds.
     */
    public static function renderChart(
        array $rows,
        array $rulers,
        string $memorySvg = '',
        int $memory = 0,
        int $memoryHeight = 40,
    ): string {
        if ($rows === []) {
            return '';
        }

        $children = [
            self::renderAxis($rulers),
            ...self::renderLegend($rows),
            self::renderRows($rows),
        ];

        if ($memorySvg !== '') {
            $children[] = self::renderMemoryFooter($memorySvg, $memory, $memoryHeight);
        }

        return Section::tag()
            ->class('yii-debug-tl')
            ->html(...$children)
            ->render();
    }

    /**
     * Renders the empty-state hint linking to the sortable Profiling panel.
     */
    public static function renderEmptyHint(bool $hasRows, string $profilingUrl): string
    {
        if ($hasRows) {
            return '';
        }

        return Div::tag()
            ->class('yii-debug-tl-hint')
            ->html(
                P::tag()
                    ->class('yii-debug-tl-hint-title')
                    ->content('No spans matched your filter.'),
                P::tag()
                    ->class('yii-debug-tl-hint-body')
                    ->html(
                        'The timeline is most useful for requests that take hundreds of milliseconds, where you can ',
                        Em::tag()
                            ->content('see'),
                        ' which operations dominate. For quick requests the ',
                        A::tag()
                            ->href($profilingUrl)
                            ->content('Profiling panel'),
                        ' presents the same data as a sortable list easier to scan.',
                    ),
            )
            ->render();
    }

    /**
     * Renders the filter form while preserving adapter-owned route parameters.
     *
     * @param array<string, string> $hiddenParams Hidden route and theme parameters.
     */
    public static function renderFilterForm(
        string $action,
        array $hiddenParams,
        string $duration,
        string $category,
    ): string {
        $children = [];

        foreach ($hiddenParams as $name => $value) {
            $children[] = InputHidden::tag()->name($name)->value($value);
        }

        $children[] = Div::tag()
            ->class('yii-debug-tl-field')
            ->html(
                Label::tag()
                    ->content('Min duration (ms)')
                    ->for('tl-duration'),
                InputNumber::tag()
                    ->id('tl-duration')
                    ->min(0)
                    ->name('Timeline[duration]')
                    ->placeholder('0')
                    ->step(0.1)
                    ->value($duration),
            );
        $children[] = Div::tag()
            ->class('yii-debug-tl-field yii-debug-tl-field-grow')
            ->html(
                Label::tag()
                    ->content('Category')
                    ->for('tl-category'),
                InputText::tag()
                    ->id('tl-category')
                    ->name('Timeline[category]')
                    ->placeholder('yii\\db\\Command::query')
                    ->value($category),
            );
        $children[] = Button::tag()
            ->class('yii-debug-btn yii-debug-btn-primary yii-debug-btn-sm')
            ->content('Apply')
            ->type('submit');

        return Form::tag()
            ->action($action)
            ->class('yii-debug-tl-filter')
            ->html(...$children)
            ->method('get')
            ->render();
    }

    /**
     * Renders total duration, peak memory, and visible span count.
     */
    public static function renderSummary(float $duration, int $memory, int $spanCount): string
    {
        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(
                Span::tag()
                    ->html(
                        Strong::tag()
                            ->content(number_format($duration)),
                        ' ms total',
                    ),
                Span::tag()
                    ->class('yii-debug-grid-summary-sep')
                    ->content('·'),
                Span::tag()
                    ->html(
                        Strong::tag()
                            ->content(
                                Format::bytesToMb($memory)
                            ),
                        ' peak memory',
                    ),
                Span::tag()
                    ->class('yii-debug-grid-summary-sep')
                    ->content('·'),
                Span::tag()
                    ->html(
                        Strong::tag()
                    ->content(
                        (string) $spanCount
                    ),
                        ' spans',
                    ),
            )
            ->render();
    }

    private static function formatTickLabel(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return "{$milliseconds} ms";
        }

        $seconds = rtrim(rtrim(sprintf('%.1f', $milliseconds / 1000), '0'), '.');

        return "{$seconds} s";
    }

    /**
     * @param array<int, float> $rulers Ruler offsets keyed by milliseconds.
     */
    private static function renderAxis(array $rulers): Header
    {
        $ticks = [];

        foreach ($rulers as $milliseconds => $left) {
            $ticks[] = Span::tag()
                ->class('yii-debug-tl-tick')
                ->content(self::formatTickLabel($milliseconds))
                ->style(['left' => Format::cssPercent($left)]);
        }

        return Header::tag()->class('yii-debug-tl-axis')->html(...$ticks);
    }

    /**
     * @param list<TimelineSpanRow> $rows Positioned timeline spans.
     *
     * @return list<Div> Legend container, or an empty list for a single category.
     */
    private static function renderLegend(array $rows): array
    {
        $present = [];

        foreach ($rows as $row) {
            $present[$row->variant] = true;
        }

        if (count($present) < 2) {
            return [];
        }

        $items = [];

        foreach (self::LEGEND_LABELS as $variant => $label) {
            if (!isset($present[$variant])) {
                continue;
            }

            $items[] = Span::tag()
                ->class("yii-debug-tl-legend-item yii-debug-tl-row-{$variant}")
                ->html(
                    Span::tag()
                        ->class('yii-debug-tl-dot')
                        ->addAttribute('aria-hidden', 'true'),
                    Span::tag()
                        ->class('yii-debug-tl-legend-label')
                        ->content($label),
                );
        }

        return [Div::tag()->class('yii-debug-tl-legend')->html(...$items)];
    }

    private static function renderMemoryFooter(string $svg, int $memory, int $height): Footer
    {
        return Footer::tag()
            ->class('yii-debug-tl-memory')
            ->html(
                Span::tag()
                    ->class('yii-debug-tl-memory-label')
                    ->content('Memory'),
                Div::tag()
                    ->class('yii-debug-tl-memory-track')
                    ->html($svg)
                    ->style(['height' => "{$height}px"]),
                Span::tag()
                    ->class('yii-debug-tl-memory-peak')
                    ->content(Format::bytesToMb($memory)),
            );
    }

    private static function renderRow(TimelineSpanRow $row): Div
    {
        return Div::tag()
            ->addAttribute('role', 'listitem')
            ->class("yii-debug-tl-row yii-debug-tl-row-{$row->variant}")
            ->html(
                Div::tag()
                    ->class('yii-debug-tl-label')
                    ->style(['--depth' => $row->depth])
                    ->html(
                        Span::tag()
                            ->class('yii-debug-tl-dot')
                            ->addAttribute('aria-hidden', 'true'),
                        Span::tag()
                            ->class('yii-debug-tl-name')
                            ->html(Fqcn::renderLabel($row->category)),
                    ),
                Div::tag()
                    ->class('yii-debug-tl-track')
                    ->html(
                        Div::tag()
                            ->class('yii-debug-tl-bar')
                            ->style([
                                'left' => $row->cssLeft . '%',
                                'width' => $row->cssWidth . '%',
                            ])
                            ->html(
                                Span::tag()
                                    ->class('yii-debug-tl-bar-duration')
                                    ->content(sprintf('%.1f ms', $row->duration)),
                            ),
                    ),
            )
            ->title($row->tooltip);
    }

    /**
     * @param list<TimelineSpanRow> $rows Positioned timeline spans.
     */
    private static function renderRows(array $rows): Div
    {
        $rendered = [];

        foreach ($rows as $row) {
            $rendered[] = self::renderRow($row);
        }

        return Div::tag()
            ->class('yii-debug-tl-rows')
            ->addAttribute('role', 'list')
            ->html(...$rendered);
    }
}

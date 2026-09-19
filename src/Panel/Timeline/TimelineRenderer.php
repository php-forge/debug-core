<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Timeline;

use PHPForge\Debug\Helper\{Format, Fqcn};
use PHPForge\Debug\Theme\Css;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\{Button, Form, InputHidden, InputNumber, InputText};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Em, Label, Span, Strong};
use UIAwesome\Html\Root\{Footer, Header};
use UIAwesome\Html\Sectioning\Section;

use function explode;
use function number_format;
use function rtrim;
use function sprintf;
use function str_starts_with;

/**
 * Renders the shared Timeline summary, filters, empty hint, and positioned span chart.
 */
final class TimelineRenderer
{
    /**
     * Renders the complete timeline chart from prepared spans and ruler offsets.
     *
     * @param list<TimelineSpanRow> $rows Positioned timeline spans.
     * @param array<int, float> $rulers Ruler offsets keyed by milliseconds.
     * @param string $memorySvg Inline memory graph markup, or an empty string to omit the footer.
     * @param int $memory Peak memory reported in the footer, in bytes.
     * @param int $memoryHeight Memory graph track height, in pixels.
     *
     * @return string Chart markup, or an empty string when no span survived filtering.
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
     *
     * @param bool $hasRows Whether any span survived filtering.
     * @param string $profilingUrl URL of the Profiling panel for the same request.
     *
     * @return string Hint markup, or an empty string when spans are present.
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
                        Em::tag()->content('see'),
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
     * @param string $action Form action URL owned by the adapter.
     * @param array<string, string> $hiddenParams Hidden route and theme parameters.
     * @param string $duration Minimum duration filter, in milliseconds.
     * @param string $category Category filter matched against span names.
     *
     * @return string Filter form markup.
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
     *
     * @param float $duration Total request duration, in milliseconds.
     * @param int $memory Peak memory, in bytes.
     * @param int $spanCount Number of spans left after filtering.
     *
     * @return string Summary header markup.
     */
    public static function renderSummary(float $duration, int $memory, int $spanCount): string
    {
        return Header::tag()
            ->class(Css::GRID_SUMMARY)
            ->html(
                Span::tag()
                    ->html(
                        Strong::tag()->content(number_format($duration)),
                        ' ms total',
                    ),
                Span::tag()
                    ->class(Css::GRID_SUMMARY_SEP)
                    ->content('·'),
                Span::tag()
                    ->html(
                        Strong::tag()->content(Format::bytesToMb($memory)),
                        ' peak memory',
                    ),
                Span::tag()
                    ->class(Css::GRID_SUMMARY_SEP)
                    ->content('·'),
                Span::tag()
                    ->html(
                        Strong::tag()->content((string) $spanCount),
                        ' spans',
                    ),
            )
            ->render();
    }

    /**
     * Builds the accessible row label, prefixing the category only when the tooltip omits it.
     *
     * @param TimelineSpanRow $row Span to label.
     *
     * @return string Accessible label for the span row.
     */
    private static function accessibleRowLabel(TimelineSpanRow $row): string
    {
        if ($row->category === '' || str_starts_with($row->tooltip, $row->category . "\n")) {
            return $row->tooltip;
        }

        return "{$row->category}\n{$row->tooltip}";
    }

    /**
     * Formats an axis tick in milliseconds below one second and in seconds above it.
     *
     * @param int $milliseconds Tick offset, in milliseconds.
     *
     * @return string Tick label carrying its unit.
     */
    private static function formatTickLabel(int $milliseconds): string
    {
        if ($milliseconds < 1000) {
            return "{$milliseconds} ms";
        }

        $seconds = rtrim(rtrim(sprintf('%.1f', $milliseconds / 1000), '0'), '.');

        return "{$seconds} s";
    }

    /**
     * Renders the ruler axis, positioning each tick at its offset.
     *
     * @param array<int, float> $rulers Ruler offsets keyed by milliseconds.
     *
     * @return Header Axis header holding the positioned ticks.
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
     * Renders the memory footer holding the graph and the peak-memory readout.
     *
     * @param string $svg Inline memory graph markup.
     * @param int $memory Peak memory, in bytes.
     * @param int $height Graph track height, in pixels.
     *
     * @return Footer Memory footer element.
     */
    private static function renderMemoryFooter(string $svg, int $memory, int $height): Footer
    {
        return Footer::tag()
            ->class('yii-debug-tl-memory')
            ->html(
                Span::tag()
                    ->class('yii-debug-tl-memory-label')
                    ->content('Memory'),
                Div::tag()
                    ->addAriaAttribute('hidden', 'true')
                    ->class('yii-debug-tl-memory-track')
                    ->html($svg)
                    ->style(['height' => "{$height}px"]),
                Span::tag()
                    ->addAriaAttribute('label', 'Peak memory ' . Format::bytesToMb($memory))
                    ->class('yii-debug-tl-memory-peak')
                    ->content(Format::bytesToMb($memory)),
            );
    }

    /**
     * Renders one span as a labelled row carrying its positioned bar.
     *
     * @param TimelineSpanRow $row Positioned timeline span.
     *
     * @return Div Span row element.
     */
    private static function renderRow(TimelineSpanRow $row): Div
    {
        return Div::tag()
            ->addAriaAttribute('label', self::accessibleRowLabel($row))
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
                            ->html(Strong::tag()->content(self::shortCategoryName($row->category)))
                            ->title($row->category),
                        Span::tag()
                            ->class('yii-debug-tl-bar-duration')
                            ->content(sprintf('%.1f ms', $row->duration)),
                    ),
                Div::tag()
                    ->addAriaAttribute('hidden', 'true')
                    ->class('yii-debug-tl-track')
                    ->html(
                        Div::tag()
                            ->class('yii-debug-tl-bar')
                            ->style(
                                [
                                    'left' => $row->cssLeft . '%',
                                    'width' => $row->cssWidth . '%',
                                ],
                            ),
                    ),
            )
            ->title($row->tooltip);
    }

    /**
     * Renders the span rows as an accessible list.
     *
     * @param list<TimelineSpanRow> $rows Positioned timeline spans.
     *
     * @return Div List element holding the span rows.
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

    /**
     * Shortens a category to its class short name, falling back to the full category.
     *
     * @param string $category Span category.
     *
     * @return string Short category name, or the placeholder glyph when the category is empty.
     */
    private static function shortCategoryName(string $category): string
    {
        if ($category === '') {
            return '—';
        }

        $shortName = Fqcn::shortName(explode('::', $category, 2)[0]);

        return $shortName === '' ? $category : $shortName;
    }
}

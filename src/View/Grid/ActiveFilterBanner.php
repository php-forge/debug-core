<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\Grid;

use Closure;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;

use function array_keys;
use function count;

/**
 * Renders the active-filter banner above a panel grid.
 */
final class ActiveFilterBanner
{
    /**
     * Returns the rendered banner HTML, or an empty string when no filters are active.
     *
     * @param array<string, string> $activeFilters Attribute-to-value map of the currently applied filters.
     * @param Closure(list<string>): string $removeUrl Builds the link that drops the given attributes from the URL.
     */
    public static function render(array $activeFilters, Closure $removeUrl): string
    {
        if ($activeFilters === []) {
            return '';
        }

        $count = count($activeFilters);

        $pills = '';

        foreach ($activeFilters as $attr => $val) {
            $attribute = Span::tag()
                ->class('yii-debug-active-filter-attr')
                ->content($attr)
                ->render();
            $separator = Span::tag()
                ->class('yii-debug-active-filter-sep')
                ->content(':')
                ->render();
            $value = Span::tag()
                ->class('yii-debug-active-filter-value')
                ->content($val)
                ->render();
            $remove = Span::tag()
                ->class('yii-debug-active-filter-x')
                ->addAttribute('aria-hidden', 'true')
                ->content('×')
                ->render();

            $pillContent = "{$attribute}{$separator}{$value}{$remove}";

            $pills .= A::tag()
                ->class('yii-debug-active-filter-pill')
                ->addAriaAttribute('label', "Remove {$attr}: {$val} filter")
                ->addAttribute('title', 'Remove this filter')
                ->href($removeUrl([$attr]))
                ->html($pillContent)
                ->render();
        }

        $label = Span::tag()
            ->class('yii-debug-active-filters-label')
            ->content($count . ' filter' . ($count === 1 ? '' : 's') . ' active')
            ->render();

        $list = Span::tag()->class('yii-debug-active-filters-list')->html($pills)->render();

        $clearAll = A::tag()
            ->class('yii-debug-active-filters-clear')
            ->addAriaAttribute('label', 'Clear all active filters')
            ->addAttribute('title', 'Clear all filters and show every row')
            ->href($removeUrl(array_keys($activeFilters)))
            ->content('Clear all')
            ->render();

        $content = "{$label}{$list}{$clearAll}";

        return Div::tag()
            ->class('yii-debug-active-filters')
            ->addAttribute('role', 'group')
            ->addAriaAttribute('label', 'Active filters')
            ->html($content)
            ->render();
    }
}

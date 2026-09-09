<?php

declare(strict_types=1);

namespace PHPForge\Debug\Data;

use UIAwesome\Html\Form\{Option, Select};
use UIAwesome\Html\Phrasing\{Label, Span};

use function is_numeric;
use function min;
use function strcasecmp;

/**
 * Resolves the `per-page` grid page size and renders the shared page-size selector.
 */
final class PageSize
{
    /**
     * Selector value that disables pagination.
     */
    public const string ALL = 'all';
    /**
     * Default page size applied when no `per-page` parameter is supplied or the value is invalid.
     */
    public const int DEFAULT = 50;
    /**
     * Hard cap on the number of rows per page.
     */
    public const int MAX = 1000;
    /**
     * Selector options in display order; the literal `all` disables pagination.
     */
    public const array OPTIONS = [
        '10',
        '25',
        '50',
        '100',
        self::ALL,
    ];

    /**
     * Returns the `per-page` selector state, canonicalizing `all` and falling back to the default.
     *
     * @param string|null $raw Raw `per-page` query-parameter value, or `null` when absent.
     * @param int $default Page size used when no value is supplied.
     *
     * @return string Canonical selector value: {@see ALL}, the raw value, or the default as a string.
     */
    public static function current(string|null $raw, int $default = self::DEFAULT): string
    {
        if ($raw === null) {
            return (string) $default;
        }

        return strcasecmp($raw, self::ALL) === 0 ? self::ALL : $raw;
    }

    /**
     * Resolves the raw `per-page` value into an effective page size.
     *
     * @param string|null $raw Raw `per-page` query-parameter value, or `null` when absent.
     * @param positive-int $default Page size used when no value is supplied or the value is invalid.
     *
     * @return positive-int|null Effective page size capped at {@see MAX}, or `null` when `all` disables pagination.
     */
    public static function resolve(string|null $raw, int $default = self::DEFAULT): int|null
    {
        if ($raw !== null && strcasecmp($raw, self::ALL) === 0) {
            return null;
        }

        $size = $raw !== null && is_numeric($raw) ? (int) $raw : $default;

        if ($size <= 0) {
            $size = $default;
        }

        return min($size, self::MAX);
    }

    /**
     * Renders the page-size selector for the `per-page` value found in the query parameters.
     *
     * @param array<array-key, mixed> $queryParams Query parameters already normalized by the panel.
     *
     * @return string Rendered selector markup.
     */
    public static function selectorFor(array $queryParams): string
    {
        return self::selectorHtml(self::current(QueryInput::scalar($queryParams, 'per-page')));
    }

    /**
     * Renders the inline page-size selector shown in the grid summary header.
     *
     * @param string $current Currently selected raw value (one of {@see OPTIONS} for a highlighted option).
     *
     * @return string Rendered selector markup.
     */
    private static function selectorHtml(string $current): string
    {
        $select = Select::tag()
            ->addDataAttribute('yii-debug-pagesize', true)
            ->class('yii-debug-grid-pagesize-select')
            ->name('per-page');

        foreach (self::OPTIONS as $row) {
            $select = $select->option(
                Option::tag()
                    ->value($row)
                    ->content($row === self::ALL ? 'All' : $row)
                    ->selected($row === $current),
            );
        }

        return Label::tag()
            ->class('yii-debug-grid-pagesize')
            ->html(
                Span::tag()
                    ->class('yii-debug-grid-pagesize-label')
                    ->content('Rows'),
                $select,
            )
            ->render();
    }
}

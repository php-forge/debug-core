<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Table\{Table as HtmlTable, Tbody, Th, Thead, Tr};

/**
 * Builds the shared debugger table shell: an optional column-header row above the body rows, inside a scroll wrapper.
 */
final class Table
{
    /**
     * Builds the `<table>` element, prepending a `<thead>` row of `<th scope="col">` cells when labels are supplied.
     *
     * Exposed on its own so sections that decorate the shell (extra attributes, a fixed layout) still share one
     * header and body contract.
     *
     * @param list<string> $headers Column labels, or `[]` for a table without a header row.
     * @param list<Tr> $rows Body rows in display order.
     * @param string $class Table CSS classes.
     *
     * @return HtmlTable Table element.
     */
    public static function build(array $headers, array $rows, string $class = 'yii-debug-table'): HtmlTable
    {
        $table = HtmlTable::tag()->class($class);

        if ($headers === []) {
            return $table->html(Tbody::tag()->html(...$rows));
        }

        $cells = [];

        foreach ($headers as $header) {
            $cells[] = Th::tag()
                ->scope('col')
                ->content($header);
        }

        return $table->html(
            Thead::tag()->html(Tr::tag()->html(...$cells)),
            Tbody::tag()->html(...$rows),
        );
    }

    /**
     * Renders the table shell inside its scroll wrapper.
     *
     * @param list<string> $headers Column labels, or `[]` for a table without a header row.
     * @param list<Tr> $rows Body rows in display order.
     * @param string $tableClass Table CSS classes.
     * @param string $wrapClass Scroll-wrapper CSS classes.
     *
     * @return string Wrapped table markup.
     */
    public static function render(
        array $headers,
        array $rows,
        string $tableClass = 'yii-debug-table',
        string $wrapClass = 'yii-debug-table-wrap',
    ): string {
        return Div::tag()
            ->class($wrapClass)
            ->html(self::build($headers, $rows, $tableClass))
            ->render();
    }
}

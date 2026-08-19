<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use PHPForge\Debug\Helper\Dump;
use UIAwesome\Html\Flow\{Div, P, Pre};
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\Phrasing\Em;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};

use function array_keys;
use function array_values;
use function is_scalar;

/**
 * Renders a database EXPLAIN plan with the shared debugger table contract.
 */
final class DbExplainRenderer
{
    /**
     * Renders the explained SQL statement and its driver-specific result rows.
     *
     * @param string $query SQL statement passed to EXPLAIN.
     * @param array<array-key, array<array-key, mixed>> $results Driver result rows.
     */
    public static function render(string $query, array $results): string
    {
        return self::renderPlan($query, $results, null);
    }

    /**
     * Renders a failed EXPLAIN attempt without escaping the shared panel contract.
     */
    public static function renderError(string $query, string $message): string
    {
        return self::renderPlan($query, [], $message);
    }

    /**
     * @param array<array-key, array<array-key, mixed>> $results Driver result rows.
     */
    private static function renderPlan(string $query, array $results, string|null $error): string
    {
        $resultList = array_values($results);

        $columns = $resultList === [] ? [] : array_keys($resultList[0]);

        $children = [
            H1::tag()
                ->class('yii-debug-explain-title')
                ->content('EXPLAIN'),
        ];

        if ($query !== '') {
            $children[] = Pre::tag()
                ->class('yii-debug-explain-query')
                ->html(SqlHighlighter::highlight($query));
        }

        if ($error !== null) {
            $children[] = P::tag()
                ->class('yii-debug-explain-empty')
                ->content("EXPLAIN failed: {$error}");
        } elseif ($results === []) {
            $children[] = P::tag()
                ->class('yii-debug-explain-empty')
                ->content('EXPLAIN returned no rows.');
        } else {
            $headerCells = [];

            foreach ($columns as $column) {
                $headerCells[] = Th::tag()->scope('col')->content((string) $column);
            }

            $bodyRows = [];

            foreach ($results as $row) {
                $cells = [];

                foreach ($columns as $column) {
                    $value = $row[$column] ?? null;
                    $cells[] = $value === null
                        ? Td::tag()->html(Em::tag()->content('NULL'))
                        : Td::tag()->content(is_scalar($value) ? (string) $value : Dump::export($value));
                }

                $bodyRows[] = Tr::tag()->html(...$cells);
            }

            $children[] = Div::tag()
                ->class('yii-debug-explain-scroll')
                ->html(
                    Table::tag()
                        ->class('yii-debug-table yii-debug-explain-table')
                        ->html(
                            Thead::tag()->html(Tr::tag()->html(...$headerCells)),
                            Tbody::tag()->html(...$bodyRows),
                        ),
                );
        }

        return Div::tag()
            ->class('yii-debug-explain')
            ->html(...$children)
            ->render();
    }
}

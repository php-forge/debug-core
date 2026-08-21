<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use Closure;
use PHPForge\Debug\Helper\Vocabulary;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;

use function array_map;
use function date;
use function implode;
use function in_array;
use function sprintf;
use function strtoupper;

/**
 * Renders the typed cells of the queries grid for the DB debug panel.
 */
final class DbQueryRenderer
{
    /**
     * Returns whether the given query type produces a useful EXPLAIN plan.
     *
     * Only DML statements that touch tables (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `WITH`) are
     * accepted; metadata, session-control, and transaction-control statements either error or return noise, so they
     * are filtered out.
     *
     * @param string $type SQL command verb (case-insensitive).
     */
    public static function canBeExplained(string $type): bool
    {
        return in_array(
            strtoupper($type),
            ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH'],
            true,
        );
    }

    /**
     * Renders the statement duration formatted as `N.N ms`.
     */
    public static function renderDurationCell(QueryRow $row): string
    {
        return sprintf('%.1f ms', $row->duration);
    }

    /**
     * Renders the SQL statement column with its optional backtrace list and EXPLAIN toggle.
     *
     * The caller supplies an URL builder (typically `static fn(int $seq) => Url::to(['db-explain', 'seq' => $seq, ...])`)
     * so the renderer stays free of routing concerns and easy to test in isolation.
     *
     * @param QueryRow $row Typed query record.
     * @param Closure(array<string, mixed>): string $traceLine Renders one backtrace frame as a link line.
     * @param bool $hasExplain `true` when the active driver supports EXPLAIN for the row's statement type.
     * @param callable(int): string $explainUrlBuilder Builds the EXPLAIN URL for the given query sequence index.
     */
    public static function renderQueryCell(
        QueryRow $row,
        Closure $traceLine,
        bool $hasExplain,
        callable $explainUrlBuilder,
    ): string {
        $children = [
            Div::tag()
                ->class('yii-debug-db-sql')
                ->html(SqlHighlighter::highlight($row->query)),
        ];

        if ($row->trace !== []) {
            $items = array_map(
                static fn(array $frame): Li => Li::tag()->html(($traceLine)($frame)),
                $row->trace,
            );

            $children[] = Ul::tag()
                ->class('yii-debug-trace')
                ->html(...$items);
        }

        if ($hasExplain && self::canBeExplained($row->type)) {
            $explainTargetId = "yii-debug-db-explain-{$row->seq}";

            $children[] = Div::tag()
                ->class('yii-debug-db-explain')
                ->html(
                    A::tag()
                        ->addAriaAttribute('controls', $explainTargetId)
                        ->addAriaAttribute('expanded', 'false')
                        ->addAriaAttribute('label', 'Toggle EXPLAIN output')
                        ->class('yii-debug-db-explain-toggle')
                        ->href($explainUrlBuilder($row->seq))
                        ->html(
                            Span::tag()
                                ->addAriaAttribute('hidden', 'true')
                                ->class('yii-debug-db-explain-chevron')
                                ->content('›'),
                            Span::tag()
                                ->class('yii-debug-db-explain-label')
                                ->content('Explain'),
                        )
                        ->role('button'),
                    Div::tag()
                        ->class('yii-debug-db-explain-text')
                        ->id($explainTargetId),
                );
        }

        return implode('', $children);
    }

    /**
     * Renders the rows-affected cell, falling back to an en dash (`–`) when the driver did not report the count.
     */
    public static function renderRowsCell(QueryRow $row): string
    {
        if ($row->rows === null) {
            return '–';
        }

        return "{$row->rows} " . ($row->rows === 1 ? 'row' : 'rows');
    }

    /**
     * Renders the capture time as `H:i:s.mmm`, derived from the row's millisecond timestamp.
     */
    public static function renderTimeCell(QueryRow $row): string
    {
        $timeInSeconds = $row->timestamp / 1000;

        $millisecondsDiff = (int) (($timeInSeconds - (int) $timeInSeconds) * 1000);

        return date('H:i:s.', (int) $timeInSeconds) . sprintf('%03d', $millisecondsDiff);
    }

    /**
     * Renders the statement-type pill (`SELECT`, `INSERT`, `UPDATE`, ...), tinted by its vocabulary verb.
     */
    public static function renderTypeCell(QueryRow $row): string
    {
        $variant = Vocabulary::sqlVerb($row->type);

        return Span::tag()
            ->class("yii-debug-db-type yii-debug-verb-{$variant}")
            ->content($row->type)
            ->render();
    }
}

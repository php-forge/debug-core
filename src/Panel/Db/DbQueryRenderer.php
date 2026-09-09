<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use Closure;
use PHPForge\Debug\Helper\{Format, Vocabulary};
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\{Span, Strong};

use function array_map;
use function implode;
use function number_format;
use function sprintf;
use function trim;

/**
 * Renders the typed cells of the queries grid for the DB debug panel.
 */
final class DbQueryRenderer
{
    /**
     * Renders the statement duration formatted as `N.N ms`.
     */
    public static function renderDurationCell(QueryRow $row): string
    {
        return sprintf('%.1f ms', $row->getDuration());
    }

    /**
     * Renders an actionable summary linking each potential N+1 group to its first query.
     *
     * @param list<NPlusOneFinding> $findings
     * @param string $context Optional plain-text scope appended to the visible heading and accessible label.
     */
    public static function renderNPlusOneSummary(array $findings, string $context = ''): string
    {
        if ($findings === []) {
            return '';
        }

        $context = trim($context);

        $context = $context === '' ? '' : " {$context}";
        $items = [];

        foreach ($findings as $finding) {
            $groupId = $finding->id();
            $items[] = Li::tag()
                ->html(
                    A::tag()
                        ->addDataAttribute('yii-debug-n1-filter', $groupId)
                        ->class('yii-debug-db-n1-link')
                        ->href("#{$groupId}")
                        ->title($finding->representativeQuery)
                        ->html(
                            Strong::tag()->content("{$finding->count}×"),
                            Span::tag()
                                ->class('yii-debug-db-n1-fingerprint')
                                ->content($finding->representativeQuery),
                            Span::tag()->content(number_format($finding->totalDuration, 1) . ' ms'),
                        ),
                );
        }

        return Div::tag()
            ->addAriaAttribute('label', "Potential N+1 query groups{$context}")
            ->class('yii-debug-db-n1-summary')
            ->html(
                Div::tag()
                    ->class('yii-debug-db-n1-heading')
                    ->html(
                        Strong::tag()->content("Potential N+1 queries{$context}"),
                        A::tag()
                            ->addAttribute('hidden', true)
                            ->addDataAttribute('yii-debug-n1-clear', true)
                            ->class('yii-debug-db-n1-clear')
                            ->content('Show all queries')
                            ->href('#'),
                    ),
                Ul::tag()
                    ->class('yii-debug-db-n1-list')
                    ->html(...$items),
                Span::tag()
                    ->addAriaAttribute('atomic', 'true')
                    ->addAriaAttribute('live', 'polite')
                    ->addDataAttribute('yii-debug-n1-status', true)
                    ->class('yii-debug-sr-only'),
            )
            ->role('region')
            ->render();
    }

    /**
     * Renders the SQL statement column with its optional collapsed backtrace and EXPLAIN toggle.
     *
     * The caller supplies a URL builder so the renderer stays free of routing concerns and easy to test in isolation
     * (typically `static fn(int $seq) => Url::to(['db-explain', 'seq' => $seq, ...])`).
     *
     * @param QueryRow $row Typed query record.
     * @param Closure(array<string, mixed>): string $traceLine Renders one backtrace frame as a link line.
     * @param bool $hasExplain `true` when the active driver supports EXPLAIN; the row must also be EXPLAIN-eligible.
     * @param callable(int): string $explainUrlBuilder Builds the EXPLAIN URL for the given query sequence index.
     */
    public static function renderQueryCell(
        QueryRow $row,
        Closure $traceLine,
        bool $hasExplain,
        callable $explainUrlBuilder,
        NPlusOneFinding|null $nPlusOneFinding = null,
    ): string {
        $sql = Div::tag()
            ->class('yii-debug-db-sql')
            ->html(SqlHighlighter::highlight($row->getQuery()));

        $children = [$sql];

        if ($nPlusOneFinding !== null) {
            $groupId = $nPlusOneFinding->id();
            $sql = $sql->addDataAttribute('yii-debug-n1-group', $groupId);

            if ($row->getSequence() === $nPlusOneFinding->firstSequence) {
                $sql = $sql->id($groupId);
            }

            $children = [
                $sql,
                A::tag()
                    ->addAriaAttribute('label', "Review {$nPlusOneFinding->count} similar queries")
                    ->addDataAttribute('yii-debug-n1-filter', $groupId)
                    ->class('yii-debug-db-n1-row-link')
                    ->content("Potential N+1 · {$nPlusOneFinding->count} similar")
                    ->href("#{$groupId}"),
            ];
        }

        if ($row->getTrace() !== []) {
            $items = array_map(
                static fn(array $frame): Li => Li::tag()->html(($traceLine)($frame)),
                $row->getTrace(),
            );

            $children[] = Details::tag()
                ->class('yii-debug-db-trace')
                ->html(
                    Summary::tag()
                        ->class('yii-debug-db-trace-toggle')
                        ->html(
                            Span::tag()
                                ->addAriaAttribute('hidden', 'true')
                                ->class('yii-debug-db-trace-chevron')
                                ->content('›'),
                            Span::tag()->content(DbMessage::TRACE),
                        ),
                    Ul::tag()
                        ->class('yii-debug-trace')
                        ->html(...$items),
                );
        }

        if ($hasExplain && $row->isExplainable()) {
            $explainTargetId = "yii-debug-db-explain-{$row->getSequence()}";

            $children[] = Div::tag()
                ->class('yii-debug-db-explain')
                ->html(
                    A::tag()
                        ->addAriaAttribute('controls', $explainTargetId)
                        ->addAriaAttribute('expanded', 'false')
                        ->addAriaAttribute('label', 'Toggle EXPLAIN output')
                        ->class('yii-debug-db-explain-toggle')
                        ->href($explainUrlBuilder($row->getSequence()))
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
        if ($row->getRows() === null) {
            return '–';
        }

        return "{$row->getRows()} " . ($row->getRows() === 1 ? 'row' : 'rows');
    }

    /**
     * Renders the capture time as `H:i:s.mmm`, derived from the row's millisecond timestamp.
     */
    public static function renderTimeCell(QueryRow $row): string
    {
        return Format::timeOfDay((int) $row->getTimestamp());
    }

    /**
     * Renders the statement-type pill (`SELECT`, `INSERT`, `UPDATE`, ...), tinted by its vocabulary verb.
     */
    public static function renderTypeCell(QueryRow $row): string
    {
        $variant = Vocabulary::sqlVerb($row->getType());

        return Span::tag()
            ->class("yii-debug-db-type yii-debug-verb-{$variant}")
            ->content($row->getType())
            ->render();
    }
}

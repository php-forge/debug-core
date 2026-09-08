<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;

use function number_format;
use function sprintf;

/**
 * Renders request-wide Database totals independently of adapter grid widgets.
 */
final class DbSummaryRenderer
{
    public static function render(DbSummary $summary, string|null $pageSize = null): string
    {
        $separator = Span::tag()
            ->class('yii-debug-grid-summary-sep')
            ->content('·');

        $items = [
            Span::tag()->html(Strong::tag()->content((string) $summary->count), DbMessage::QUERY_COUNT_SUFFIX->value),
            $separator,
            Span::tag()
                ->html(
                    Strong::tag()->content(number_format($summary->duration, 3)),
                    DbMessage::TOTAL_SUFFIX->value,
                ),
        ];

        if ($summary->duplicates > 0) {
            $items[] = $separator;

            $items[] = Span::tag()
                ->class('yii-debug-grid-summary-stat-warn')
                ->html(
                    Strong::tag()->content((string) $summary->duplicates),
                    DbMessage::DUPLICATE_SUFFIX->value,
                );
        }

        if ($pageSize !== null) {
            $items[] = $pageSize;
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$items)
            ->render();
    }

    /**
     * Builds the toolbar chip title, reporting the executed query count or the active warning sentences.
     *
     * Warnings replace the count entirely: the critical-count sentence comes first, the excessive-caller sentence
     * second, and both are joined by a newline when they apply together.
     *
     * @param DbSummary $summary Request-wide Database metrics.
     * @param int|null $criticalQueryThreshold Query count above which the request is critical, or `null` to disable.
     * @param int|null $excessiveCallerThreshold Statements per call site that flag it, or `null` to disable.
     */
    public static function toolbarTitle(
        DbSummary $summary,
        int|null $criticalQueryThreshold,
        int|null $excessiveCallerThreshold,
    ): string {
        $warning = '';

        if ($criticalQueryThreshold !== null && $summary->isCritical($criticalQueryThreshold)) {
            $warning = sprintf(DbMessage::TOOLBAR_CRITICAL->value, $criticalQueryThreshold);
        }

        $excessiveCallerCount = $summary->excessiveCallerCount($excessiveCallerThreshold);

        if ($excessiveCallerCount > 0) {
            $callers = $excessiveCallerCount === 1 ? DbMessage::TOOLBAR_CALLERS_ONE : DbMessage::TOOLBAR_CALLERS_MANY;

            $separator = $warning !== '' ? "\n" : '';

            $warning .= $separator . sprintf($callers->value, $excessiveCallerCount);
        }

        return $warning !== '' ? $warning : sprintf(DbMessage::TOOLBAR_EXECUTED->value, $summary->count);
    }
}

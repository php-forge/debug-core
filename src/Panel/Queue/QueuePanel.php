<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Queue;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};
use PHPForge\Debug\Helper\Format;
use PHPForge\Debug\Presenter\BadgeInline;

use function date;
use function implode;
use function in_array;
use function sprintf;

/**
 * Presents the queue lifecycle events captured during the request as a table with one detail group per event.
 *
 * The per-job detail route belongs to the adapter, so it supplies one URL per event with {@see self::jobUrls()}.
 */
final class QueuePanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Queue navigation entry.
     */
    protected const string ICON = QueueMessage::ID->value;
    /**
     * @var string Stable identifier associating the panel with the captured queue payload.
     */
    protected const string ID = QueueMessage::ID->value;
    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = QueueMessage::TITLE->value;

    /**
     * @var array<string, array{label: QueueMessage, tone: Tone}> Badge describing each captured lifecycle phase.
     */
    private const array STATUS = [
        JobRecord::TYPE_PUSH => ['label' => QueueMessage::STATUS_QUEUED, 'tone' => Tone::INFO],
        JobRecord::TYPE_EXEC => ['label' => QueueMessage::STATUS_DONE, 'tone' => Tone::SUCCESS],
        JobRecord::TYPE_ERROR => ['label' => QueueMessage::STATUS_FAILED, 'tone' => Tone::DANGER],
    ];

    /**
     * @var array<int, string> Adapter-owned per-event detail URLs, keyed by event position.
     */
    private array $jobUrls = [];

    /**
     * Returns a new instance with the adapter-owned per-event detail URLs.
     *
     * @param array<int, string> $urls Detail URLs keyed by the event position in capture order.
     *
     * @return self New instance carrying the requested detail URLs.
     */
    public function jobUrls(array $urls): self
    {
        $new = clone $this;
        $new->jobUrls = $urls;

        return $new;
    }

    /**
     * Builds the panel view from the decoded queue capture.
     *
     * @param array<string, mixed> $data Decoded panel payload with a `records` key holding the captured events.
     *
     * @return PanelView Lifecycle table, async hint, and per-event detail groups.
     */
    public function present(array $data): PanelView
    {
        $records = QueueSnapshot::fromArray($data, '$.queue')->entries();
        $summary = QueueSummary::fromRecords($records);

        $total = $summary->totalEvents();
        $errors = $summary->totalErrors();

        $view = PanelView::create()
            ->summary(
                $total === 1 ? QueueMessage::EVENT_SUFFIX->value : QueueMessage::EVENTS_SUFFIX->value,
                $total,
            )
            ->summary(QueueMessage::QUEUED_SUFFIX->value, $summary->totalPushed())
            ->summary(QueueMessage::DONE_SUFFIX->value, $summary->totalExecuted())
            ->toolbar(QueueMessage::TOOLBAR->value, $total);

        if ($errors > 0) {
            $view = $view->summary(QueueMessage::FAILED_SUFFIX->value, $errors);
        }

        if ($total === 0) {
            return $view->emptyState(
                QueueMessage::EMPTY_HEADLINE->value,
                QueueMessage::EMPTY_EXPLANATION->value,
                [
                    QueueMessage::EMPTY_HOOKS->value,
                    PanelView::code(QueueMessage::HOOK_AFTER_PUSH->value),
                    ', ',
                    PanelView::code(QueueMessage::HOOK_AFTER_EXEC->value),
                    ', or ',
                    PanelView::code(QueueMessage::HOOK_AFTER_ERROR->value),
                    '.',
                ],
            );
        }

        $async = self::asyncDrivers($records);

        if ($async !== []) {
            $view = $view->callout(
                Tone::INFO,
                PanelView::strong(QueueMessage::ASYNC_TITLE->value . implode(', ', $async) . '.'),
                QueueMessage::ASYNC_WORKER->value,
                PanelView::strong(QueueMessage::CLI->value),
                QueueMessage::ASYNC_SNAPSHOTS->value,
            );
        }

        $view = $view
            ->heading(QueueMessage::LIFECYCLE->value, true)
            ->table(
                [
                    QueueMessage::NUMBER->value,
                    QueueMessage::STATUS->value,
                    QueueMessage::JOB->value,
                    QueueMessage::COMPONENT->value,
                    QueueMessage::DRIVER->value,
                    QueueMessage::TIME->value,
                    QueueMessage::ATTEMPT->value,
                    QueueMessage::DURATION->value,
                ],
                $this->rows($records),
                true,
                [
                    0 => ColumnStyle::NUMBER,
                    1 => ColumnStyle::PILL,
                    2 => ColumnStyle::IDENTIFIER,
                    3 => ColumnStyle::IDENTIFIER,
                    4 => ColumnStyle::IDENTIFIER,
                    5 => ColumnStyle::IDENTIFIER,
                    6 => ColumnStyle::NUMBER,
                    7 => ColumnStyle::NUMBER,
                ],
            );

        foreach ($records as $index => $record) {
            $view = $view
                ->heading(
                    sprintf(QueueMessage::RECORD_HEADING->value, $index + 1, self::jobClass($record)),
                    true,
                )
                ->group(
                    sprintf(QueueMessage::EVENT_GROUP->value, $index + 1),
                    $this->detail($record, $index),
                );
        }

        return $view;
    }

    /**
     * Returns the distinct out-of-process driver names, in first-seen order.
     *
     * @param list<JobRecord> $records Captured events in chronological order.
     *
     * @return list<string> Async driver names, deduplicated while preserving order.
     */
    private static function asyncDrivers(array $records): array
    {
        $drivers = [];

        foreach ($records as $record) {
            if (
                $record->isAsync
                && $record->driverName !== ''
                && in_array($record->driverName, $drivers, true) === false
            ) {
                $drivers[] = $record->driverName;
            }
        }

        return $drivers;
    }

    /**
     * Builds the detail group of one event: envelope overview, payload, and the captured error.
     *
     * @param JobRecord $record Captured event to describe.
     * @param int $index Event position in capture order.
     *
     * @return PanelView Child view holding only the detail blocks of the event.
     */
    private function detail(JobRecord $record, int $index): PanelView
    {
        $fields = [
            QueueMessage::JOB->value => PanelView::code(self::jobClass($record)),
            QueueMessage::STATUS->value => self::status($record),
            QueueMessage::COMPONENT->value => self::orPlaceholder($record->componentId),
            QueueMessage::DRIVER->value => self::orPlaceholder($record->driverName),
            QueueMessage::DRIVER_CLASS->value => $record->driverClass === ''
                ? QueueMessage::PLACEHOLDER->value
                : PanelView::code($record->driverClass),
            QueueMessage::EXECUTION->value => $record->isAsync
                ? QueueMessage::WORKER_PROCESS->value
                : QueueMessage::IN_PROCESS->value,
            QueueMessage::JOB_ID->value => self::orPlaceholder($record->jobId),
            QueueMessage::PUSHED_AT->value => date(QueueMessage::DATE_FORMAT->value, (int) $record->time),
            QueueMessage::TTR->value => self::seconds($record->ttr),
            QueueMessage::DELAY->value => self::seconds($record->delay),
            QueueMessage::PRIORITY->value => $record->priority ?? QueueMessage::PLACEHOLDER->value,
            QueueMessage::ATTEMPT->value => $record->attempt ?? QueueMessage::PLACEHOLDER->value,
            QueueMessage::DURATION->value => self::duration($record->duration),
        ];

        $url = $this->jobUrls[$index] ?? null;

        if ($url !== null) {
            $fields[QueueMessage::DETAILS->value] = PanelView::link(QueueMessage::JOB_LINK->value, $url);
        }

        $view = PanelView::create()->overview($fields, true);

        if ($record->error !== '') {
            $view = $view->callout(Tone::DANGER, $record->error);
        }

        return $record->payloadFields === []
            ? $view->paragraph(QueueMessage::NO_PAYLOAD->value)
            : $view->overview([QueueMessage::PAYLOAD->value => PanelView::value($record->payloadFields)]);
    }

    /**
     * Formats an execution time, falling back to the placeholder when the phase reports none.
     *
     * @param float|null $duration Execution time in seconds, or `null` when the phase reports none.
     *
     * @return string Formatted duration, or the placeholder.
     */
    private static function duration(float|null $duration): string
    {
        return $duration === null ? QueueMessage::PLACEHOLDER->value : Format::milliseconds($duration, 1);
    }

    /**
     * Returns the job class, falling back to the placeholder when the event carried none.
     *
     * @param JobRecord $record Captured event to describe.
     *
     * @return string Job class name, or the placeholder.
     */
    private static function jobClass(JobRecord $record): string
    {
        return $record->jobClass === '' ? QueueMessage::PLACEHOLDER->value : $record->jobClass;
    }

    /**
     * Returns the value, or the placeholder when the capture left it empty.
     *
     * @param string $value Captured value.
     *
     * @return string Captured value, or the placeholder when empty.
     */
    private static function orPlaceholder(string $value): string
    {
        return $value === '' ? QueueMessage::PLACEHOLDER->value : $value;
    }

    /**
     * Builds the lifecycle table rows in capture order.
     *
     * @param list<JobRecord> $records Captured events in chronological order.
     *
     * @return list<list<mixed>> One row per event, matching the declared column order.
     */
    private function rows(array $records): array
    {
        $rows = [];

        foreach ($records as $index => $record) {
            $rows[] = [
                $index + 1,
                self::status($record),
                self::jobClass($record),
                self::orPlaceholder($record->componentId),
                self::orPlaceholder($record->driverName),
                date(QueueMessage::TIME_FORMAT->value, (int) $record->time),
                $record->attempt ?? QueueMessage::PLACEHOLDER->value,
                self::duration($record->duration),
            ];
        }

        return $rows;
    }

    /**
     * Formats a second-based override, falling back to the placeholder when the driver default applies.
     *
     * @param int|null $value Override in seconds, or `null` when the driver default applies.
     *
     * @return string Formatted override, or the placeholder.
     */
    private static function seconds(int|null $value): string
    {
        return $value === null ? QueueMessage::PLACEHOLDER->value : "{$value}s";
    }

    /**
     * Builds the badge describing the captured lifecycle phase.
     *
     * Hydration rejects any other phase, so the fallback only satisfies the type checker.
     *
     * @param JobRecord $record Captured event to describe.
     *
     * @return BadgeInline Badge carrying the phase label and tone.
     */
    private static function status(JobRecord $record): BadgeInline
    {
        $status = self::STATUS[$record->eventType] ?? self::STATUS[JobRecord::TYPE_PUSH];

        return PanelView::badge($status['label']->value, $status['tone']);
    }
}

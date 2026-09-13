<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Queue;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};
use PHPForge\Debug\Helper\Format;

use function date;
use function implode;
use function in_array;
use function sprintf;

/**
 * Presents the queue lifecycle events captured during the request as a table with one detail group per event.
 *
 * The per-job detail route belongs to the adapter, so it supplies one URL per event with {@see self::jobUrls()}.
 *
 * @phpstan-import-type BadgeInline from PanelView
 */
final class QueuePanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Queue navigation entry.
     */
    protected const string ICON = 'queue';

    /**
     * @var string Stable identifier associating the panel with the captured queue payload.
     */
    protected const string ID = 'queue';

    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = 'Queue';

    /**
     * @var string Placeholder shown wherever the capture left a field empty.
     */
    private const string PLACEHOLDER = '—';

    /**
     * @var array<string, array{label: string, tone: Tone}> Badge describing each captured lifecycle phase.
     */
    private const array STATUS = [
        JobRecord::TYPE_PUSH => ['label' => 'Queued', 'tone' => Tone::INFO],
        JobRecord::TYPE_EXEC => ['label' => 'Done', 'tone' => Tone::SUCCESS],
        JobRecord::TYPE_ERROR => ['label' => 'Failed', 'tone' => Tone::DANGER],
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
            ->active($total > 0)
            ->summary($total === 1 ? ' event' : ' events', $total)
            ->summary(' queued', $summary->totalPushed())
            ->summary(' done', $summary->totalExecuted())
            ->toolbar('Jobs', $total);

        if ($errors > 0) {
            $view = $view->summary(' failed', $errors);
        }

        if ($total === 0) {
            return $view->emptyState(
                'No queue activity in this request',
                'This request pushed no job and ran none, so the lifecycle log is empty.',
                [
                    'Events appear here when a queue component emits ',
                    PanelView::code('afterPush'),
                    ', ',
                    PanelView::code('afterExec'),
                    ', or ',
                    PanelView::code('afterError'),
                    '.',
                ],
            );
        }

        $async = self::asyncDrivers($records);

        if ($async !== []) {
            $view = $view->callout(
                Tone::INFO,
                PanelView::strong('Async driver: ' . implode(', ', $async) . '.'),
                ' Push events show here, but jobs run in a separate worker process; see the History sidebar for ',
                PanelView::strong('CLI'),
                ' debug snapshots that capture the matching exec and error events.',
            );
        }

        $view = $view
            ->heading('Lifecycle events', true)
            ->table(
                ['#', 'Status', 'Job', 'Component', 'Driver', 'Time', 'Attempt', 'Duration'],
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
                ->heading(sprintf('%d. %s', $index + 1, self::jobClass($record)), true)
                ->group(sprintf('Event %d', $index + 1), $this->detail($record, $index));
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
            if ($record->isAsync && $record->driverName !== '' && in_array($record->driverName, $drivers, true) === false) {
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
            'Job' => PanelView::code(self::jobClass($record)),
            'Status' => self::status($record),
            'Component' => self::orPlaceholder($record->componentId),
            'Driver' => self::orPlaceholder($record->driverName),
            'Driver class' => $record->driverClass === ''
                ? self::PLACEHOLDER
                : PanelView::code($record->driverClass),
            'Execution' => $record->isAsync ? 'Worker process' : 'In process',
            'Job id' => self::orPlaceholder($record->jobId),
            'Pushed at' => date('M j, Y · H:i:s', (int) $record->time),
            'TTR' => self::seconds($record->ttr),
            'Delay' => self::seconds($record->delay),
            'Priority' => $record->priority === null ? self::PLACEHOLDER : $record->priority,
            'Attempt' => $record->attempt === null ? self::PLACEHOLDER : $record->attempt,
            'Duration' => self::duration($record->duration),
        ];

        $url = $this->jobUrls[$index] ?? null;

        if ($url !== null) {
            $fields['Details'] = PanelView::link(
                'Open job detail',
                $url,
            );
        }

        $view = PanelView::create()->overview($fields, true);

        if ($record->error !== '') {
            $view = $view->callout(Tone::DANGER, $record->error);
        }

        return $record->payloadFields === []
            ? $view->paragraph('The event carried no job payload.')
            : $view->overview(['Payload' => PanelView::value($record->payloadFields)]);
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
        return $duration === null ? self::PLACEHOLDER : Format::milliseconds($duration, 1);
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
        return $record->jobClass === '' ? self::PLACEHOLDER : $record->jobClass;
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
        return $value === '' ? self::PLACEHOLDER : $value;
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
                date('H:i:s', (int) $record->time),
                $record->attempt === null ? self::PLACEHOLDER : $record->attempt,
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
        return $value === null ? self::PLACEHOLDER : "{$value}s";
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
    private static function status(JobRecord $record): array
    {
        $status = self::STATUS[$record->eventType] ?? self::STATUS[JobRecord::TYPE_PUSH];

        return PanelView::badge(
            $status['label'],
            $status['tone'],
        );
    }
}

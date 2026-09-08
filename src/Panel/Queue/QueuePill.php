<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Queue;

use UIAwesome\Html\Phrasing\Span;

/**
 * Builds the driver and lifecycle-status pills shared by the Queue card and grid views.
 */
final class QueuePill
{
    /**
     * Builds the driver pill, tagging out-of-process drivers with the `is-async` modifier and keeping the driver
     * class in a `title` tooltip.
     *
     * @param JobRecord $record Typed queue event record.
     *
     * @return Span Driver pill element.
     */
    public static function driver(JobRecord $record): Span
    {
        $modifier = $record->isAsync ? 'is-async' : 'is-sync';

        return Span::tag()
            ->class("yii-debug-queue-driver yii-debug-queue-driver-{$modifier}")
            ->title($record->driverClass !== '' ? $record->driverClass : 'Unknown driver')
            ->content($record->driverName);
    }

    /**
     * Builds the lifecycle-status pill from {@see JobRecord::EVENT_VARIANTS}, falling back to the queued state for
     * unknown event types.
     *
     * @param JobRecord $record Typed queue event record.
     *
     * @return Span Status pill element.
     */
    public static function status(JobRecord $record): Span
    {
        $variant = JobRecord::EVENT_VARIANTS[$record->eventType]['variant'] ?? 'queued';
        $label = JobRecord::EVENT_VARIANTS[$record->eventType]['label'] ?? 'Queued';

        return Span::tag()
            ->class("yii-debug-queue-status yii-debug-queue-status-{$variant}")
            ->content($label);
    }
}

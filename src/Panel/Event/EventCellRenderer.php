<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use PHPForge\Debug\Helper\Fqcn;
use UIAwesome\Html\Phrasing\Span;

use function date;
use function sprintf;

/**
 * Renders the typed cells of the events grid for the Event debug panel.
 */
final class EventCellRenderer
{
    /**
     * Renders the event class FQCN as a muted namespace prefix plus a bold short name (`—` when empty).
     */
    public static function renderClassCell(EventRow $row): string
    {
        return Fqcn::renderLabel($row->class);
    }

    /**
     * Renders the sender FQCN as a muted namespace prefix plus a bold short name (`—` for static events).
     */
    public static function renderSenderCell(EventRow $row): string
    {
        return Fqcn::renderLabel($row->senderClass);
    }

    /**
     * Renders a muted `static` badge for class-level events, or `—` for object-level ones.
     */
    public static function renderStaticCell(EventRow $row): string
    {
        if ($row->isStatic !== '1') {
            return '—';
        }

        return Span::tag()
            ->class('yii-debug-badge yii-debug-badge-muted')
            ->content('static')
            ->render();
    }

    /**
     * Renders the capture time as `H:i:s.mmm`, derived from the row's second-precision timestamp.
     */
    public static function renderTimeCell(EventRow $row): string
    {
        $seconds = (int) $row->time;
        $millis = (int) (($row->time - $seconds) * 1000);

        return date('H:i:s.', $seconds) . sprintf('%03d', $millis);
    }
}

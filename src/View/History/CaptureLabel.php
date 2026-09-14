<?php

declare(strict_types=1);

namespace PHPForge\Debug\View\History;

use PHPForge\Debug\Storage\RequestSummary;

use function date;
use function mb_strimwidth;

/**
 * Formats unescaped capture-selection labels without shortening their unique identifiers.
 */
final class CaptureLabel
{
    /**
     * Builds the label identifying one capture in a selection control.
     *
     * Joins the time, HTTP method, URL, and tag with a middot. The URL is trimmed to 72 columns, while the tag is
     * never shortened, so two captures of the same request stay distinguishable. Missing time or method fall back to
     * a placeholder instead of collapsing the label.
     *
     * The result is unescaped, so the caller encodes it.
     *
     * @param RequestSummary $summary Capture the label describes.
     *
     * @return string Unescaped label for a capture-selection control.
     */
    public static function fromSummary(RequestSummary $summary): string
    {
        $time = $summary->time > 0 ? date('H:i:s', (int) $summary->time) : 'time unavailable';

        $method = $summary->method !== '' ? $summary->method : 'UNKNOWN';

        $url = mb_strimwidth($summary->url, 0, 72, '...');

        return "{$time} · {$method} · {$url} · {$summary->tag}";
    }
}

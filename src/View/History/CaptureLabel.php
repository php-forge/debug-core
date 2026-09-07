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
    public static function fromSummary(RequestSummary $summary): string
    {
        $time = $summary->time > 0 ? date('H:i:s', (int) $summary->time) : 'time unavailable';

        $method = $summary->method !== '' ? $summary->method : 'UNKNOWN';

        $url = mb_strimwidth($summary->url, 0, 72, '...');

        return "{$time} · {$method} · {$url} · {$summary->tag}";
    }
}

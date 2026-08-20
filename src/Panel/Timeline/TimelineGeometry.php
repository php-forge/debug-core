<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Timeline;

use PHPForge\Debug\Panel\Profile\ProfileRow;

use function floor;
use function log10;
use function max;
use function range;

/**
 * Computes framework-neutral timeline ruler positions and profile-span geometry.
 */
final class TimelineGeometry
{
    /**
     * Returns adaptive ruler ticks keyed by milliseconds and valued by percentage offsets.
     *
     * @return array<int, float> Ruler offsets.
     */
    public static function rulers(float $duration, int $line = 6): array
    {
        if ($line < 1 || $duration <= 0.0) {
            return [];
        }

        $rough = $duration / $line;

        $magnitude = 10 ** max(0, (int) floor(log10($rough)));

        $normalized = $rough / $magnitude;

        $step = match (true) {
            $normalized <= 1.0 => $magnitude,
            $normalized <= 2.0 => 2 * $magnitude,
            $normalized <= 5.0 => 5 * $magnitude,
            default => 10 * $magnitude,
        };

        $ticks = [0 => 0.0];
        $limit = $duration - $step / 4;

        $tickCount = (int) floor($limit / $step);
        $millisecondsList = $tickCount > 0 ? range($step, $tickCount * $step, $step) : [];

        foreach ($millisecondsList as $milliseconds) {
            $ticks[$milliseconds] = $milliseconds / $duration * 100;
        }

        return $ticks;
    }
    /**
     * Converts captured profile rows into positioned timeline spans.
     *
     * @param list<ProfileRow> $rows Profile rows in capture order.
     *
     * @return list<TimelineSpanRow> Positioned spans in capture order.
     */
    public static function spans(array $rows, float $start, float $duration): array
    {
        if ($duration <= 0.0) {
            return [];
        }

        $spans = [];

        foreach ($rows as $row) {
            $spans[] = TimelineSpanRow::from(
                $row,
                ($row->timestamp - $start) / $duration * 100,
                $row->duration / $duration * 100,
            );
        }

        return $spans;
    }
}

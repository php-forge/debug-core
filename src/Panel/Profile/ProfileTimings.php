<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Profile;

use PHPForge\Debug\Helper\{Coerce, LogLevel};

use function array_values;
use function count;
use function json_encode;
use function ksort;
use function md5;

/**
 * Pairs profile begin/end log tuples into per-block timings.
 */
final class ProfileTimings
{
    /**
     * Calculates the per-block timings from profile-level log tuples.
     *
     * Each tuple is `[token, level, category, timestamp, traces, memory]`; a begin marker is matched with the next
     * end marker carrying the same token, producing one timing entry ordered by the begin position.
     *
     * Usage example:
     *
     * ```php
     * $timings = \PHPForge\Debug\Panel\Profile\ProfileTimings::calculate($tuples);
     * ```
     *
     * @param array<int, array<int|string, mixed>> $messages Profile log tuples in capture order.
     *
     * @return list<array{
     *   info: mixed,
     *   category: mixed,
     *   timestamp: float,
     *   trace: mixed,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int
     * }> Timings ordered by their begin marker.
     */
    public static function calculate(array $messages): array
    {
        $timings = [];
        $stack = [];

        foreach ($messages as $index => $log) {
            $level = Coerce::intOrNull($log[1] ?? null);
            $hash = md5(Coerce::string(json_encode($log[0] ?? null)));

            if ($level === LogLevel::PROFILE_BEGIN) {
                $log['index'] = $index;
                $stack[$hash] = $log;

                continue;
            }

            if ($level !== LogLevel::PROFILE_END || !isset($stack[$hash])) {
                continue;
            }

            $begin = $stack[$hash];
            $beginIndex = Coerce::intOrNull($begin['index']) ?? 0;
            $beginTimestamp = Coerce::floatOrNull($begin[3] ?? null) ?? 0.0;
            $memory = Coerce::intOrNull($log[5] ?? null) ?? 0;

            $timings[$beginIndex] = [
                'info' => $begin[0] ?? null,
                'category' => $begin[2] ?? null,
                'timestamp' => $beginTimestamp,
                'trace' => $begin[4] ?? [],
                'level' => count($stack) - 1,
                'duration' => (Coerce::floatOrNull($log[3] ?? null) ?? 0.0) - $beginTimestamp,
                'memory' => $memory,
                'memoryDiff' => $memory - (Coerce::intOrNull($begin[5] ?? null) ?? 0),
            ];

            unset($stack[$hash]);
        }

        ksort($timings);

        return array_values($timings);
    }
}

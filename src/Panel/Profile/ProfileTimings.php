<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Profile;

use PHPForge\Debug\Helper\LogLevel;

use function array_pop;
use function array_values;
use function ksort;

/**
 * Pairs profile begin/end log tuples into per-block timings.
 *
 * @phpstan-import-type LogMessage from \PHPForge\Debug\Panel\Log\LogSnapshot
 * @phpstan-type ProfileTiming array{
 *   info: string,
 *   category: string,
 *   timestamp: float,
 *   trace: list<array<string, mixed>>,
 *   level: int,
 *   duration: float,
 *   memory: int,
 *   memoryDiff: int
 * }
 */
final class ProfileTimings
{
    /**
     * Calculates the per-block timings from profile-level log tuples.
     *
     * Each tuple is `[token, level, category, timestamp, traces, memory]`; a begin marker is matched with the next end
     * marker carrying the same token, producing one timing entry ordered by the begin position.
     *
     * @param list<LogMessage> $messages Profile log tuples in capture order.
     *
     * @return list<ProfileTiming> Timings ordered by their begin marker.
     */
    public static function calculate(array $messages): array
    {
        $timings = [];
        /** @var array<array-key, list<array{message: LogMessage, index: int, level: int}>> $stack */
        $stack = [];
        $nestedLevel = 0;

        foreach ($messages as $index => $log) {
            $level = $log[1];
            $tokenKey = $log[0];

            if ($level === LogLevel::PROFILE_BEGIN) {
                $stack[$tokenKey][] = [
                    'message' => $log,
                    'index' => $index,
                    'level' => $nestedLevel++,
                ];

                continue;
            }

            if ($level !== LogLevel::PROFILE_END || ($stack[$tokenKey] ?? []) === []) {
                continue;
            }

            $begin = array_pop($stack[$tokenKey]);
            --$nestedLevel;

            if ($stack[$tokenKey] === []) {
                unset($stack[$tokenKey]);
            }

            $beginMessage = $begin['message'];
            $beginIndex = $begin['index'];
            $beginTimestamp = $beginMessage[3];
            $memory = $log[5] ?? 0;

            $timings[$beginIndex] = [
                'info' => $beginMessage[0],
                'category' => $beginMessage[2],
                'timestamp' => $beginTimestamp,
                'trace' => $beginMessage[4],
                'level' => $begin['level'],
                'duration' => $log[3] - $beginTimestamp,
                'memory' => $memory,
                'memoryDiff' => $memory - ($beginMessage[5] ?? 0),
            ];
        }

        ksort($timings);

        return array_values($timings);
    }
}

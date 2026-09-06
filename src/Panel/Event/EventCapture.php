<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use PHPForge\Debug\Capture\CapturePolicy;

use function array_slice;
use function count;
use function dirname;
use function in_array;
use function is_string;
use function mb_strcut;
use function min;
use function str_replace;
use function strlen;
use function strstr;

/**
 * Bounds and sanitizes explicitly selected event diagnostics without inspecting arbitrary objects.
 */
final class EventCapture
{
    /**
     * @param array<string, string> $fields Adapter-selected fields only.
     *
     * @return array<string, string>
     */
    public static function context(array $fields): array
    {
        $policy = new CapturePolicy();

        $result = [];

        foreach (array_slice($fields, 0, 16, true) as $key => $value) {
            $result[mb_strcut($key, 0, 128)] = self::text(
                $policy->isSensitiveKey($key) ? '[redacted]' : $policy->redactText($value),
            );
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $frames Backtrace acquired with `DEBUG_BACKTRACE_IGNORE_ARGS`.
     * @param int $limit Maximum frames; hard-limited to sixteen.
     * @param list<string> $skipFiles Adapter instrumentation files to omit.
     *
     * @return list<string>
     */
    public static function trace(array $frames, int $limit, array $skipFiles = []): array
    {
        $result = [];
        $skipFiles[] = __FILE__;

        $skipFiles[] = dirname(__DIR__, 2) . '/Instrumentation/InstrumentationGuard.php';

        foreach ($frames as $frame) {
            if (count($result) >= min(16, $limit)) {
                break;
            }

            $file = $frame['file'] ?? null;

            if (!is_string($file) || in_array($file, $skipFiles, true)) {
                continue;
            }

            $line = $frame['line'] ?? null;
            $result[] = self::text($file . (\is_int($line) ? ":{$line}" : ''));
        }

        return $result;
    }

    private static function text(string $value): string
    {
        // Anonymous class names may contain a NUL-delimited declaration path.
        $prefix = strstr($value, "\0", true);

        $value = $prefix === false ? $value : $prefix;

        $value = str_replace("\0", '', $value);

        return strlen($value) > 2048 ? mb_strcut($value, 0, 2036) . ' [truncated]' : $value;
    }
}

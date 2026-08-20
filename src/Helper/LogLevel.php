<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

/**
 * Defines the log-level wire values persisted inside debug snapshots.
 */
final class LogLevel
{
    /**
     * Error message level.
     */
    public const int ERROR = 0x01;
    /**
     * Informational message level.
     */
    public const int INFO = 0x04;
    /**
     * Profiling message level.
     */
    public const int PROFILE = 0x40;
    /**
     * Profiling begin marker level.
     */
    public const int PROFILE_BEGIN = 0x50;
    /**
     * Profiling end marker level.
     */
    public const int PROFILE_END = 0x60;
    /**
     * Tracing message level.
     */
    public const int TRACE = 0x08;
    /**
     * Warning message level.
     */
    public const int WARNING = 0x02;

    /**
     * Returns the lowercase display name of a level, matching the Yii logger naming.
     *
     * @param int $level Log-level wire value.
     *
     * @return string Display name; `unknown` for unrecognized values.
     */
    public static function name(int $level): string
    {
        return match ($level) {
            self::ERROR => 'error',
            self::WARNING => 'warning',
            self::INFO => 'info',
            self::TRACE => 'trace',
            self::PROFILE => 'profile',
            self::PROFILE_BEGIN => 'profile begin',
            self::PROFILE_END => 'profile end',
            default => 'unknown',
        };
    }
}

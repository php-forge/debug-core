<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function strtoupper;

/**
 * Maps HTTP methods, status codes, and SQL statement types to the shared semantic hue vocabulary.
 */
final class Vocabulary
{
    /**
     * Returns the level suffix (`error`, `warning`, `info`, `trace`, `profile`, or `other`) for a {@see LogLevel} wire
     * value. Profile begin/end markers share the `profile` hue.
     *
     * @param int $level Log-level wire value.
     *
     * @return string Semantic log-level suffix.
     */
    public static function logLevel(int $level): string
    {
        return match ($level) {
            LogLevel::ERROR => 'error',
            LogLevel::WARNING => 'warning',
            LogLevel::INFO => 'info',
            LogLevel::TRACE => 'trace',
            LogLevel::PROFILE, LogLevel::PROFILE_BEGIN, LogLevel::PROFILE_END => 'profile',
            default => 'other',
        };
    }
    /**
     * Returns the verb suffix for an SQL statement type.
     *
     * @param string $type SQL statement type.
     *
     * @return string Semantic verb suffix.
     */
    public static function sqlVerb(string $type): string
    {
        return match (strtoupper($type)) {
            'SELECT', 'SHOW', 'EXPLAIN', 'DESCRIBE', 'PRAGMA' => 'get',
            'INSERT' => 'post',
            'UPDATE', 'REPLACE', 'UPSERT' => 'put',
            'DELETE', 'DROP', 'TRUNCATE' => 'delete',
            default => 'other',
        };
    }

    /**
     * Returns the status-class suffix for an HTTP status code.
     *
     * @param int $code HTTP status code.
     *
     * @return string Status-class suffix.
     */
    public static function statusClass(int $code): string
    {
        return match (true) {
            $code >= 500 => '5xx',
            $code >= 400 => '4xx',
            $code >= 300 => '3xx',
            $code >= 200 => '2xx',
            default => 'none',
        };
    }

    /**
     * Returns the semantic verb suffix for an HTTP method.
     *
     * @param string $method HTTP method.
     *
     * @return string Semantic verb suffix.
     */
    public static function verb(string $method): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD' => 'get',
            'POST' => 'post',
            'PUT', 'PATCH' => 'put',
            'DELETE' => 'delete',
            default => 'other',
        };
    }
}

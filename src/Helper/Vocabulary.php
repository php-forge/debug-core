<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use function strtoupper;

/**
 * Maps HTTP methods, status codes, and SQL statement types to the shared semantic hue vocabulary.
 *
 * The client-side mirrors in `resources/src/core/history-cursor.js` and `resources/src/toolbar/element.js` must stay
 * synchronized with these maps.
 */
final class Vocabulary
{
    /**
     * Returns the verb suffix for an SQL statement type.
     *
     * Usage example:
     * ```php
     * $verb = \PHPForge\Debug\Helper\Vocabulary::sqlVerb('SELECT');
     * ```
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
     * Usage example:
     * ```php
     * $class = \PHPForge\Debug\Helper\Vocabulary::statusClass(404);
     * ```
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
     * Usage example:
     * ```php
     * $verb = \PHPForge\Debug\Helper\Vocabulary::verb('PATCH');
     * ```
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

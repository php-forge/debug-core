<?php

declare(strict_types=1);

namespace PHPForge\Debug\Theme;

use function is_string;
use function strtolower;

/**
 * Resolves the effective debugger theme from the request's cookie and query parameters.
 */
final class ThemeResolver
{
    /**
     * Cookie written by the client-side theme toggle.
     */
    public const string COOKIE = 'yii-debug-toolbar-theme';

    /**
     * Query parameter carrying the link-time theme snapshot.
     */
    public const string QUERY_PARAM = 'yii_debug_theme';

    /**
     * Returns the effective theme (`'light'` or `'dark'`).
     *
     * @param array<array-key, mixed> $cookieParams Request cookies.
     * @param array<array-key, mixed> $queryParams Parsed query parameters.
     */
    public static function resolve(array $cookieParams, array $queryParams): string
    {
        $raw = $cookieParams[self::COOKIE] ?? $queryParams[self::QUERY_PARAM] ?? null;

        return is_string($raw) && strtolower($raw) === 'dark' ? 'dark' : 'light';
    }
}

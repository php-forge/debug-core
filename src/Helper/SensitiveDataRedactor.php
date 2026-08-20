<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use SensitiveParameter;

use function array_fill_keys;
use function array_map;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_string;
use function strtolower;

/**
 * Replaces values whose array keys match an explicitly configured sensitive-key list.
 */
final class SensitiveDataRedactor
{
    /**
     * Common credential and session fields that must never be persisted by default.
     *
     * @var list<string>
     */
    public const array DEFAULT_KEYS = [
        'access_token',
        'accessToken',
        'api_key',
        'apiKey',
        'authorization',
        'auth_key',
        'authKey',
        'client_secret',
        'clientSecret',
        'cookie',
        'csrf_token',
        'csrfToken',
        'http_authorization',
        'http_cookie',
        'password',
        'password_hash',
        'password_reset_token',
        'passwd',
        'php_auth_pw',
        'proxy-authorization',
        'redirect_http_authorization',
        'refresh_token',
        'refreshToken',
        'secret',
        'session_id',
        'set-cookie',
        'token',
        'verification_token',
        'x-api-key',
        'x-auth-token',
        'x-csrf-token',
    ];
    public const string PLACEHOLDER = '[redacted]';
    public const string TRUNCATED = '[truncated]';

    private const int MAX_DEPTH = 10;
    private const int MAX_NODES = 10000;

    /**
     * Returns whether a key exactly matches the configured sensitive-key list, ignoring case.
     *
     * @param list<string> $sensitiveKeys Exact key names to inspect.
     */
    public static function isSensitiveKey(string $key, array $sensitiveKeys = self::DEFAULT_KEYS): bool
    {
        return isset(array_fill_keys(array_map(strtolower(...), $sensitiveKeys), true)[strtolower($key)]);
    }

    /**
     * Redacts configured keys case-insensitively throughout a bounded nested array.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $value Value tree to sanitize.
     * @param list<string> $sensitiveKeys Exact key names to redact.
     *
     * @return array<TKey, mixed> Sanitized tree with keys and non-sensitive values preserved.
     */
    public static function redact(#[SensitiveParameter] array $value, array $sensitiveKeys = self::DEFAULT_KEYS): array
    {
        $keys = array_fill_keys(array_map(strtolower(...), $sensitiveKeys), true);

        $nodes = 0;

        return self::walk($value, $keys, 0, $nodes);
    }

    /**
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $value
     * @param array<string, true> $sensitiveKeys
     *
     * @return array<TKey, mixed>
     */
    private static function walk(
        #[SensitiveParameter]
        array $value,
        array $sensitiveKeys,
        int $depth,
        int &$nodes,
    ): array {
        $redacted = [];

        foreach ($value as $key => $item) {
            ++$nodes;

            if ($nodes > self::MAX_NODES) {
                $redacted[$key] = self::TRUNCATED;

                break;
            }

            if (is_string($key) && isset($sensitiveKeys[strtolower($key)])) {
                $redacted[$key] = self::PLACEHOLDER;

                continue;
            }

            if (is_object($item)) {
                $item = get_object_vars($item);
            }

            if (is_array($item) && $depth >= self::MAX_DEPTH) {
                $redacted[$key] = self::TRUNCATED;

                continue;
            }

            $redacted[$key] = is_array($item)
                ? self::walk($item, $sensitiveKeys, $depth + 1, $nodes)
                : $item;
        }

        return $redacted;
    }
}

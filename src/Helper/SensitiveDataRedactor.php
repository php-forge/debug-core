<?php

declare(strict_types=1);

namespace PHPForge\Debug\Helper;

use InvalidArgumentException;
use SensitiveParameter;

use function array_fill_keys;
use function array_map;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_string;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * Replaces values whose array keys match configured exact names, prefixes, or PCRE patterns.
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
        'aws_secret_access_key',
        'client_secret',
        'clientSecret',
        'cookie',
        'csrf_token',
        'csrfToken',
        'database_url',
        'db_password',
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
    /**
     * Segment-aware defaults for environment-style credential keys.
     *
     * Separators are required around credential words so safe keys such as `tokenizer` and `passwordless` remain
     * visible. Passing an explicit empty pattern list opts out.
     *
     * @var list<string>
     */
    public const array DEFAULT_PATTERNS = [
        '~(?:^|[_\-.])(?:password|passwd|secret|token|api[_-]?key|private[_-]?key|credential)(?:$|[_\-.])~i',
    ];
    public const string PLACEHOLDER = '[redacted]';
    public const string TRUNCATED = '[truncated]';

    private const int MAX_DEPTH = 10;
    private const int MAX_NODES = 10000;

    /**
     * Returns whether a key matches a configured exact name, literal prefix, or PCRE pattern.
     *
     * @param list<string> $sensitiveKeys Exact key names to inspect.
     * @param list<string> $sensitiveKeyPrefixes Literal key prefixes to inspect case-insensitively.
     * @param list<string>|null $sensitiveKeyPatterns PCRE patterns applied to the complete original key. `null` uses
     * defaults only with the default exact-key list; `[]` explicitly disables patterns.
     */
    public static function isSensitiveKey(
        string $key,
        array $sensitiveKeys = self::DEFAULT_KEYS,
        array $sensitiveKeyPrefixes = [],
        array|null $sensitiveKeyPatterns = null,
    ): bool {
        $prefixes = self::prefixes($sensitiveKeyPrefixes);
        $patterns = self::patterns($sensitiveKeys, $sensitiveKeyPatterns);

        self::validatePatterns($patterns);

        return self::matches(
            $key,
            self::keyMap($sensitiveKeys),
            $prefixes,
            $patterns,
        );
    }

    /**
     * Redacts configured keys case-insensitively throughout a bounded nested array.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $value Value tree to sanitize.
     * @param list<string> $sensitiveKeys Exact key names to redact.
     * @param list<string> $sensitiveKeyPrefixes Literal key prefixes to redact case-insensitively.
     * @param list<string>|null $sensitiveKeyPatterns PCRE patterns applied to complete original keys. `null` uses
     * defaults only with the default exact-key list; `[]` explicitly disables patterns.
     *
     * @return array<TKey, mixed> Sanitized tree with keys and non-sensitive values preserved.
     */
    public static function redact(
        #[SensitiveParameter]
        array $value,
        array $sensitiveKeys = self::DEFAULT_KEYS,
        array $sensitiveKeyPrefixes = [],
        array|null $sensitiveKeyPatterns = null,
    ): array {
        $nodes = 0;
        $prefixes = self::prefixes($sensitiveKeyPrefixes);
        $patterns = self::patterns($sensitiveKeys, $sensitiveKeyPatterns);

        self::validatePatterns($patterns);

        return self::walk(
            $value,
            self::keyMap($sensitiveKeys),
            $prefixes,
            $patterns,
            0,
            $nodes,
        );
    }

    /**
     * Normalizes configured keys into a case-insensitive lookup map.
     *
     * @param list<string> $sensitiveKeys Exact key names to normalize.
     *
     * @return array<string, true> Normalized key lookup.
     */
    private static function keyMap(array $sensitiveKeys): array
    {
        return array_fill_keys(array_map(strtolower(...), $sensitiveKeys), true);
    }

    /**
     * Returns whether the key matches any normalized redaction rule.
     *
     * @param array<string, true> $sensitiveKeys
     * @param list<string> $sensitiveKeyPrefixes
     * @param list<string> $sensitiveKeyPatterns
     */
    private static function matches(
        string $key,
        array $sensitiveKeys,
        array $sensitiveKeyPrefixes,
        array $sensitiveKeyPatterns,
    ): bool {
        $normalizedKey = strtolower($key);

        if (isset($sensitiveKeys[$normalizedKey])) {
            return true;
        }

        foreach ($sensitiveKeyPrefixes as $prefix) {
            if (str_starts_with($normalizedKey, $prefix)) {
                return true;
            }
        }

        foreach ($sensitiveKeyPatterns as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Preserves exact-list override behavior while enabling safer defaults for the default policy.
     *
     * @param list<string> $keys
     * @param list<string>|null $patterns
     *
     * @return list<string>
     */
    private static function patterns(array $keys, array|null $patterns): array
    {
        return $patterns ?? ($keys === self::DEFAULT_KEYS ? self::DEFAULT_PATTERNS : []);
    }

    /**
     * Normalizes literal key prefixes and rejects an empty match-all prefix.
     *
     * @param list<string> $prefixes
     *
     * @return list<string>
     */
    private static function prefixes(array $prefixes): array
    {
        foreach ($prefixes as $prefix) {
            if ($prefix === '') {
                throw new InvalidArgumentException('Sensitive key prefixes must not be empty.');
            }
        }

        return array_map(strtolower(...), $prefixes);
    }

    /**
     * Rejects invalid PCRE configuration before walking a potentially large value tree.
     *
     * @param list<string> $patterns
     */
    private static function validatePatterns(array $patterns): void
    {
        foreach ($patterns as $pattern) {
            if ($pattern === '' || @preg_match($pattern, '') === false) {
                throw new InvalidArgumentException(
                    sprintf('Sensitive key pattern "%s" is not a valid PCRE pattern.', $pattern),
                );
            }
        }
    }

    /**
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $value
     * @param array<string, true> $sensitiveKeys
     * @param list<string> $sensitiveKeyPrefixes
     * @param list<string> $sensitiveKeyPatterns
     *
     * @return array<TKey, mixed>
     */
    private static function walk(
        #[SensitiveParameter]
        array $value,
        array $sensitiveKeys,
        array $sensitiveKeyPrefixes,
        array $sensitiveKeyPatterns,
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

            if (
                is_string($key)
                && self::matches($key, $sensitiveKeys, $sensitiveKeyPrefixes, $sensitiveKeyPatterns)
            ) {
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
                ? self::walk(
                    $item,
                    $sensitiveKeys,
                    $sensitiveKeyPrefixes,
                    $sensitiveKeyPatterns,
                    $depth + 1,
                    $nodes,
                )
                : $item;
        }

        return $redacted;
    }
}

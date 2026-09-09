<?php

declare(strict_types=1);

namespace PHPForge\Debug\Capture;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use SensitiveParameter;

use function array_reverse;
use function get_object_vars;
use function http_build_query;
use function is_array;
use function is_object;
use function parse_str;
use function preg_match_all;
use function strcspn;
use function strlen;
use function strpos;
use function substr;
use function substr_replace;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * Applies the default redaction and size limits before debug data reaches persistent storage.
 */
final readonly class CapturePolicy
{
    /**
     * Raw body bytes retained by persistent capture when no explicit limit is configured.
     */
    public const int DEFAULT_MAX_BODY_BYTES = 65536;

    /**
     * PCRE patterns applied to complete original keys.
     *
     * @var list<string>
     */
    private array $sensitiveKeyPatterns;

    /**
     * @param list<string> $sensitiveKeys Exact, case-insensitive keys to redact recursively.
     * @param int $maxBodyBytes Maximum raw request or response body bytes to retain; must be positive.
     * @param list<string> $sensitiveKeyPrefixes Literal, case-insensitive key prefixes to redact recursively.
     * @param list<string>|null $sensitiveKeyPatterns PCRE patterns applied to complete original keys. `null` uses
     * segment-aware defaults only with the default exact-key list; `[]` explicitly disables pattern matching.
     */
    public function __construct(
        private array $sensitiveKeys = SensitiveDataRedactor::DEFAULT_KEYS,
        private int $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES,
        private array $sensitiveKeyPrefixes = [],
        array|null $sensitiveKeyPatterns = null,
    ) {
        if ($this->maxBodyBytes < 1) {
            throw new InvalidArgumentException(
                Message::BODY_SIZE_INVALID->getMessage(),
            );
        }

        $this->sensitiveKeyPatterns = SensitiveDataRedactor::patterns($this->sensitiveKeys, $sensitiveKeyPatterns);

        // One probe rejects an empty prefix or an invalid pattern at configuration time instead of on first capture.
        $this->isSensitiveKey('');
    }

    /**
     * Returns whether a key is denied by this policy.
     *
     * @param string $key Original key to check case-insensitively.
     *
     * @return bool `true` when the key matches an exact name, a prefix, or a pattern rule; `false` otherwise.
     */
    public function isSensitiveKey(string $key): bool
    {
        return SensitiveDataRedactor::isSensitiveKey(
            $key,
            $this->sensitiveKeys,
            $this->sensitiveKeyPrefixes,
            $this->sensitiveKeyPatterns,
        );
    }

    /**
     * Returns the maximum number of body bytes that may reach persistent capture.
     *
     * @return int Configured body byte limit.
     */
    public function maxBodyBytes(): int
    {
        return $this->maxBodyBytes;
    }

    /**
     * Redacts sensitive keys throughout a bounded value tree.
     *
     * @template TKey of array-key
     *
     * @param array<TKey, mixed> $value Value tree to sanitize.
     *
     * @return array<TKey, mixed> Sanitized value tree.
     */
    public function redact(#[SensitiveParameter] array $value): array
    {
        return SensitiveDataRedactor::redact(
            $value,
            $this->sensitiveKeys,
            $this->sensitiveKeyPrefixes,
            $this->sensitiveKeyPatterns,
        );
    }

    /**
     * Redacts a decoded body, suppressing its raw representation whenever redaction was required and truncating it at
     * the configured byte boundary otherwise.
     *
     * @param string $raw Raw body exactly as received.
     * @param mixed $decoded Decoded body, or `null` when the body could not be decoded.
     *
     * @return array{decoded: mixed, raw: string} Sanitized decoded body and its bounded raw representation.
     */
    public function redactBody(#[SensitiveParameter] string $raw, #[SensitiveParameter] mixed $decoded): array
    {
        $sanitized = match (true) {
            is_array($decoded) => $this->redact($decoded),
            is_object($decoded) => $this->redact(get_object_vars($decoded)),
            default => $decoded,
        };

        if ($sanitized !== $decoded) {
            return ['decoded' => $sanitized, 'raw' => SensitiveDataRedactor::PLACEHOLDER];
        }

        $body = $this->redactText($raw);

        return [
            'decoded' => $sanitized,
            'raw' => strlen($body) > $this->maxBodyBytes
                ? substr($body, 0, $this->maxBodyBytes) . SensitiveDataRedactor::TRUNCATED
                : $body,
        ];
    }

    /**
     * Redacts common `key=value` and `key: value` secret fragments in diagnostic text.
     *
     * @param string $text Diagnostic text to sanitize.
     *
     * @return string Text with every sensitive assignment value replaced.
     */
    public function redactText(#[SensitiveParameter] string $text): string
    {
        $matches = [];

        preg_match_all(
            '~(?<![[:alnum:]_])(["\']?)([[:alnum:]_.-]+)\\1\s*[:=]\s*~i',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        // Later assignments are rewritten first so earlier match offsets stay valid.
        $assignments = array_reverse($matches);

        foreach ($assignments as $match) {
            [$assignment, $assignmentOffset] = $match[0];
            [$key] = $match[2];

            if ($this->isSensitiveKey($key) === false) {
                continue;
            }

            $valueStart = $assignmentOffset + strlen($assignment);
            $valueLength = strcspn($text, ",;&\r\n", $valueStart);

            $text = substr_replace(
                $text,
                SensitiveDataRedactor::PLACEHOLDER,
                $valueStart,
                $valueLength,
            );
        }

        return $text;
    }

    /**
     * Redacts sensitive values in a URL query string without changing the URL outside its query component.
     *
     * @param string $url URL to sanitize.
     *
     * @return string URL with every sensitive query value replaced.
     */
    public function redactUrl(#[SensitiveParameter] string $url): string
    {
        $fragmentPosition = strpos($url, '#');

        $fragment = $fragmentPosition === false ? '' : substr($url, $fragmentPosition);
        $withoutFragment = $fragmentPosition === false ? $url : substr($url, 0, $fragmentPosition);

        $queryPosition = strpos($withoutFragment, '?');

        if ($queryPosition === false) {
            return $url;
        }

        $query = [];

        parse_str(substr($withoutFragment, $queryPosition + 1), $query);

        return substr($withoutFragment, 0, $queryPosition + 1)
            . http_build_query($this->redact($query))
            . $fragment;
    }

    /**
     * Returns a new instance whose exact-key list also covers the given keys.
     *
     * The already resolved pattern rules are carried over, so extending the default key list keeps the segment-aware
     * defaults active instead of silently dropping them.
     *
     * @param list<string> $sensitiveKeys Extra exact, case-insensitive keys to redact recursively.
     *
     * @return self New instance denying both the configured and the additional keys.
     */
    public function withAdditionalSensitiveKeys(array $sensitiveKeys): self
    {
        return new self(
            [...$this->sensitiveKeys, ...$sensitiveKeys],
            $this->maxBodyBytes,
            $this->sensitiveKeyPrefixes,
            $this->sensitiveKeyPatterns,
        );
    }
}

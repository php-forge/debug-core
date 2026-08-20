<?php

declare(strict_types=1);

namespace PHPForge\Debug\Capture;

use InvalidArgumentException;
use PHPForge\Debug\Helper\SensitiveDataRedactor;
use SensitiveParameter;

use function array_map;
use function get_object_vars;
use function http_build_query;
use function implode;
use function is_array;
use function is_object;
use function parse_str;
use function preg_quote;
use function preg_replace_callback;
use function strlen;
use function strpos;
use function substr;

/**
 * Applies the default redaction and size limits before debug data reaches persistent storage.
 */
final readonly class CapturePolicy
{
    /**
     * @param list<string> $sensitiveKeys Exact, case-insensitive keys to redact recursively.
     * @param int $maxBodyBytes Maximum raw request or response body bytes to retain; must be positive.
     */
    public function __construct(
        private array $sensitiveKeys = SensitiveDataRedactor::DEFAULT_KEYS,
        private int $maxBodyBytes = 65536,
    ) {
        if ($this->maxBodyBytes < 1) {
            throw new InvalidArgumentException(
                'The maximum body size must be greater than zero.',
            );
        }
    }

    /**
     * Returns whether a key is denied by this policy.
     */
    public function isSensitiveKey(string $key): bool
    {
        return SensitiveDataRedactor::isSensitiveKey($key, $this->sensitiveKeys);
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
        );
    }

    /**
     * Redacts a decoded body and suppresses its raw representation whenever redaction was required.
     *
     * @return array{decoded: mixed, raw: string}
     */
    public function redactBody(#[SensitiveParameter] string $raw, #[SensitiveParameter] mixed $decoded): array
    {
        $sanitized = match (true) {
            is_array($decoded) => $this->redact($decoded),
            is_object($decoded) => $this->redact(get_object_vars($decoded)),
            default => $decoded,
        };

        return [
            'decoded' => $sanitized,
            'raw' => $sanitized !== $decoded
                ? SensitiveDataRedactor::PLACEHOLDER
                : $this->truncateBody($raw),
        ];
    }

    /**
     * Redacts common `key=value` and `key: value` secret fragments in diagnostic text.
     */
    public function redactText(#[SensitiveParameter] string $text): string
    {
        if ($this->sensitiveKeys === []) {
            return $text;
        }

        $keys = array_map(
            static fn(string $key): string => preg_quote($key, '~'),
            $this->sensitiveKeys,
        );
        $pattern = '~(?<![[:alnum:]_])(["\']?(?:' . implode('|', $keys) . ')["\']?)(\s*[:=]\s*)[^,;&\r\n]+~i';

        return preg_replace_callback(
            $pattern,
            static fn(array $match): string => ($match[1] ?? '')
                . ($match[2] ?? '')
                . SensitiveDataRedactor::PLACEHOLDER,
            $text,
        ) ?? $text;
    }

    /**
     * Redacts sensitive values in a URL query string without changing the URL outside its query component.
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
     * Truncates an opaque body at the configured byte boundary.
     */
    private function truncateBody(#[SensitiveParameter] string $body): string
    {
        $body = $this->redactText($body);

        return strlen($body) > $this->maxBodyBytes
            ? substr($body, 0, $this->maxBodyBytes) . SensitiveDataRedactor::TRUNCATED
            : $body;
    }
}

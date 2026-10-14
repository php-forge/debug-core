<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use DateTimeInterface;
use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Storage\Json;
use SensitiveParameter;
use Stringable;
use Throwable;
use UnitEnum;

use function array_is_list;
use function count;
use function get_debug_type;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function mb_strcut;
use function strlen;
use function var_export;

use const DATE_ATOM;

/**
 * Normalizes the PSR-3 context of a log message into a bounded, redacted, JSON-safe value tree.
 *
 * Objects are reduced to readable strings and never inspected, so the tree always survives the strict JSON encoding of
 * the capture.
 */
final class LogContext
{
    /**
     * Placeholder replacing an array nested deeper than {@see self::MAX_DEPTH}.
     */
    private const string DEPTH_MARKER = '[...]';
    /**
     * Maximum nesting level of arrays, the top-level context included; deeper arrays collapse to `[...]`.
     */
    private const int MAX_DEPTH = 8;
    /**
     * Maximum entries kept per nested array; the rest collapse into one `[+N more]` entry.
     */
    private const int MAX_ITEMS = 100;
    /**
     * Maximum bytes kept per key.
     */
    private const int MAX_KEY_BYTES = 128;
    /**
     * Maximum top-level entries kept; the rest collapse into one `[+N more]` entry.
     */
    private const int MAX_KEYS = 32;
    /**
     * Maximum bytes kept per string value, the `[truncated]` marker included.
     */
    private const int MAX_STRING_BYTES = 2048;
    /**
     * Key of the entry that counts the entries cut from an oversized map.
     */
    private const string MORE_KEY = '...';
    /**
     * Placeholder replacing the value of a key the capture policy denies.
     */
    private const string REDACTED = '[redacted]';
    /**
     * Marker appended to a string cut at {@see self::MAX_STRING_BYTES}.
     */
    private const string TRUNCATED = ' [truncated]';

    /**
     * Normalizes a log context into a bounded JSON-safe tree.
     *
     * Keys become valid UTF-8 strings cut at {@see self::MAX_KEY_BYTES} bytes (PHP still stores numeric keys as
     * integers); values whose key the policy denies become `[redacted]` at any depth, and string values pass through
     * {@see CapturePolicy::redactText()}.
     *
     * @param array<array-key, mixed> $context Context passed to the logger call.
     * @param CapturePolicy $policy Host policy that decides which keys and text fragments are redacted.
     * @param list<string> $excludeKeys Top-level keys dropped before normalization, such as logger-injected fields
     * already shown as columns.
     *
     * @return array<array-key, mixed> Bounded tree of `null`, `bool`, `int`, finite `float`, `string`, and arrays.
     */
    public static function normalize(
        #[SensitiveParameter]
        array $context,
        CapturePolicy $policy,
        array $excludeKeys = [],
    ): array {
        foreach ($excludeKeys as $key) {
            unset($context[$key]);
        }

        return self::entries($context, $policy, self::MAX_KEYS, 1);
    }

    /**
     * Normalizes the entries of one array level, collapsing the entries beyond the limit into one count entry.
     *
     * @param array<array-key, mixed> $items Entries of the level.
     * @param CapturePolicy $policy Host redaction policy.
     * @param int $limit Maximum entries kept on this level.
     * @param int $level One-based nesting level of `$items`.
     *
     * @return array<array-key, mixed> Normalized entries of the level.
     */
    private static function entries(array $items, CapturePolicy $policy, int $limit, int $level): array
    {
        $isList = array_is_list($items);

        $result = [];

        foreach ($items as $key => $value) {
            if (count($result) === $limit) {
                $more = '[+' . (count($items) - $limit) . ' more]';

                if ($isList) {
                    $result[] = $more;
                } else {
                    $result[self::MORE_KEY] = $more;
                }

                break;
            }

            $name = (string) $key;

            $result[mb_strcut(Json::safeString($name), 0, self::MAX_KEY_BYTES)] = $policy->isSensitiveKey($name)
                ? self::REDACTED
                : self::value($value, $policy, $level);
        }

        return $result;
    }

    /**
     * Returns the readable string of an object that the tree keeps as a leaf.
     *
     * @param object $value Object to describe.
     *
     * @return string `Class: message in file:line` for throwables, the string form of {@see Stringable} objects,
     * `DATE_ATOM` for dates, `Class::Case` for enums, and `{Class}` for every other object.
     */
    private static function objectLabel(object $value): string
    {
        $type = get_debug_type($value);

        if ($value instanceof Throwable) {
            return "{$type}: {$value->getMessage()} in {$value->getFile()}:{$value->getLine()}";
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof UnitEnum) {
            return "{$type}::{$value->name}";
        }

        if ($value instanceof Stringable) {
            try {
                return (string) $value;
            } catch (Throwable) {
                // Fall back to the class name when the userland string conversion fails.
            }
        }

        return "{{$type}}";
    }

    /**
     * Returns a JSON-safe string leaf, redacted by the policy and cut at {@see self::MAX_STRING_BYTES} bytes.
     *
     * @param string $value Raw string leaf.
     * @param CapturePolicy $policy Host redaction policy.
     *
     * @return string Bounded valid UTF-8 text.
     */
    private static function text(#[SensitiveParameter] string $value, CapturePolicy $policy): string
    {
        $value = $policy->redactText(Json::safeString($value));

        return strlen($value) > self::MAX_STRING_BYTES
            ? mb_strcut($value, 0, self::MAX_STRING_BYTES - strlen(self::TRUNCATED)) . self::TRUNCATED
            : $value;
    }

    /**
     * Normalizes one context value.
     *
     * @param mixed $value Raw context value.
     * @param CapturePolicy $policy Host redaction policy.
     * @param int $level One-based nesting level of the array holding `$value`.
     *
     * @return mixed JSON-safe value.
     */
    private static function value(#[SensitiveParameter] mixed $value, CapturePolicy $policy, int $level): mixed
    {
        return match (true) {
            $value === null, is_bool($value), is_int($value) => $value,
            is_float($value) => is_finite($value) ? $value : var_export($value, true),
            is_string($value) => self::text($value, $policy),
            is_array($value) => $level === self::MAX_DEPTH
                ? self::DEPTH_MARKER
                : self::entries($value, $policy, self::MAX_ITEMS, $level + 1),
            is_object($value) => self::text(self::objectLabel($value), $policy),
            default => '{' . get_debug_type($value) . '}',
        };
    }
}

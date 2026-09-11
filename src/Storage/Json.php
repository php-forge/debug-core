<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use function base64_encode;
use function mb_check_encoding;
use function sprintf;

/**
 * Converts strings to JSON-safe debug storage representations.
 */
final class Json
{
    private const int PAYLOAD_DEPTH = 500;

    /**
     * Prevents instantiation of this static helper.
     */
    private function __construct() {}

    /**
     * Freezes a provider payload as JSON values before it enters the request envelope.
     *
     * Strict encoding rejects invalid UTF-8, non-finite numbers, resources, cycles, and excessive depth.
     * Decoding freezes nested serialization results so application callbacks never run again during storage.
     * The depth limit reserves room for the request envelope. No redaction or truncation is applied here.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function payload(array $payload): array
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION, self::PAYLOAD_DEPTH);
        return Payload::object(json_decode($json, true, self::PAYLOAD_DEPTH, JSON_THROW_ON_ERROR))->all();
    }

    /**
     * Returns valid UTF-8, representing binary text as base64.
     *
     * @param string $value String to normalize.
     *
     * @return string JSON-safe string.
     */
    public static function safeString(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8')
            ? $value
            : sprintf('(binary: base64 %s)', base64_encode($value));
    }
}

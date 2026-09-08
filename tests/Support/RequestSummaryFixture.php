<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\Storage\RequestSummary;

/**
 * Builds canonical request metadata, and its decoded manifest payload, for the storage and history tests.
 */
final class RequestSummaryFixture
{
    /**
     * Creates request metadata, overriding only the manifest fields a test cares about.
     *
     * @param array<string, mixed> $overrides Manifest fields replacing the defaults.
     *
     * @return RequestSummary Request metadata hydrated from the defaults and the given overrides.
     */
    public static function create(array $overrides = []): RequestSummary
    {
        return RequestSummary::fromArray(self::payload($overrides));
    }

    /**
     * Returns representative decoded request metadata, overriding only the fields a test cares about.
     *
     * @param array<string, mixed> $overrides Manifest fields replacing the defaults.
     *
     * @return array<string, mixed> Decoded request metadata built from the defaults and the given overrides.
     */
    public static function payload(array $overrides = []): array
    {
        return [
            'tag' => 'tag-1',
            'url' => 'https://example.test/',
            'ajax' => false,
            'method' => 'GET',
            'ip' => '127.0.0.1',
            'time' => 1_700_000_000.0,
            'statusCode' => 200,
            'sqlCount' => 0,
            'excessiveCallersCount' => 0,
            'mailCount' => 0,
            'mailFiles' => [],
            'processingTime' => null,
            'peakMemory' => null,
            ...$overrides,
        ];
    }
}

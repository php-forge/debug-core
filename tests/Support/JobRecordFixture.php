<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Support;

use PHPForge\Debug\Panel\Queue\JobRecord;

/**
 * Builds queue lifecycle events for the Queue panel renderer tests.
 */
final class JobRecordFixture
{
    /**
     * Creates a queue lifecycle event, overriding only the fields a test cares about.
     *
     * @param array<string, mixed> $payloadFields Captured job payload fields.
     *
     * @return JobRecord Queue lifecycle event built from the defaults and the given overrides.
     */
    public static function create(
        string $eventType = 'push',
        string $componentId = 'queue',
        string $driverName = 'Sync',
        string $driverClass = 'yii\\queue\\sync\\Queue',
        bool $isAsync = false,
        string $jobClass = 'app\\jobs\\HelloJob',
        array $payloadFields = [],
        float $time = 0.0,
        string $jobId = '',
        int|null $ttr = null,
        int|null $delay = null,
        int|null $priority = null,
        int|null $attempt = null,
        float|null $duration = null,
        string $error = '',
    ): JobRecord {
        return new JobRecord(
            eventType: $eventType,
            componentId: $componentId,
            driverName: $driverName,
            driverClass: $driverClass,
            isAsync: $isAsync,
            jobClass: $jobClass,
            payloadFields: $payloadFields,
            time: $time,
            jobId: $jobId,
            ttr: $ttr,
            delay: $delay,
            priority: $priority,
            attempt: $attempt,
            duration: $duration,
            error: $error,
        );
    }
}

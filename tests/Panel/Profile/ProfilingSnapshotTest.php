<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Profile;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\MemorySample;
use PHPForge\Debug\Panel\Profile\ProfilingSnapshot;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ProfilingSnapshot} normalizing completed profiler messages into shared rows and memory samples.
 */
#[Group('panel')]
#[Group('profile')]
final class ProfilingSnapshotTest extends TestCase
{
    public function testCaptureCompletedKeepsZeroMemoryDiffWhenOnlyEndMemoryIsKnown(): void
    {
        $snapshot = ProfilingSnapshot::captureCompleted(
            0,
            0.0,
            [
                [
                    'token' => 'block',
                    'context' => [
                        'beginTime' => 100.0,
                        'duration' => 0.01,
                        'endMemory' => 512,
                    ],
                ],
            ],
        );

        $entry = $snapshot->entries()[0] ?? self::fail('Expected one profile row.');

        self::assertSame(
            0,
            $entry->memoryDiff,
            'Without both endpoints the diff must stay zero.',
        );
        self::assertSame(
            512,
            $entry->memory,
            'End memory must still surface on the row.',
        );
    }

    public function testCaptureCompletedNormalizesRowsAndSortsMemorySamples(): void
    {
        $snapshot = ProfilingSnapshot::captureCompleted(
            4_096,
            0.2,
            [
                [
                    'token' => 'SELECT 1',
                    'category' => 'message category',
                    'context' => [
                        'category' => 'Yiisoft\\Db\\Command::query',
                        'beginTime' => 100.05,
                        'time' => 999.0,
                        'endTime' => 100.06,
                        'duration' => 0.01,
                        'beginMemory' => 2_048,
                        'endMemory' => 3_072,
                        'memory' => 999,
                        'nestedLevel' => 1,
                        'trace' => [['file' => '/app/index.php', 'line' => 12]],
                    ],
                ],
                'malformed',
                ['token' => 'missing context'],
                [
                    'token' => 'missing duration',
                    'context' => ['beginTime' => 100.0],
                ],
                [
                    'token' => 'GET /',
                    'context' => [
                        'category' => 'Yii3\\Application::handle',
                        'beginTime' => 100.0,
                        'endTime' => 100.2,
                        'duration' => 0.2,
                        'beginMemory' => 1_024,
                        'endMemory' => 4_096,
                        'memoryDiff' => 3_072,
                        'nestedLevel' => -1,
                    ],
                ],
                [
                    'token' => 'defaults',
                    'category' => 'fallback category',
                    'context' => [
                        'beginTime' => 101.0,
                        'duration' => 0.01,
                    ],
                ],
            ],
        );

        self::assertSame(
            [
                'memory' => 4_096,
                'time' => 0.2,
                'entries' => [
                    [
                        'timestamp' => 100_050.0,
                        'duration' => 10.0,
                        'category' => 'Yiisoft\\Db\\Command::query',
                        'info' => 'SELECT 1',
                        'level' => 1,
                        'seq' => 0,
                        'memory' => 3_072,
                        'memoryDiff' => 1_024,
                        'trace' => [['file' => '/app/index.php', 'line' => 12]],
                    ],
                    [
                        'timestamp' => 100_000.0,
                        'duration' => 200.0,
                        'category' => 'Yii3\\Application::handle',
                        'info' => 'GET /',
                        'level' => 0,
                        'seq' => 1,
                        'memory' => 4_096,
                        'memoryDiff' => 3_072,
                        'trace' => [],
                    ],
                    [
                        'timestamp' => 101_000.0,
                        'duration' => 10.0,
                        'category' => 'fallback category',
                        'info' => 'defaults',
                        'level' => 0,
                        'seq' => 2,
                        'memory' => 0,
                        'memoryDiff' => 0,
                        'trace' => [],
                    ],
                ],
                'samples' => [
                    ['time' => 100_000.0, 'memory' => 1_024],
                    ['time' => 100_050.0, 'memory' => 2_048],
                    ['time' => 100_060.0, 'memory' => 3_072],
                    ['time' => 100_200.0, 'memory' => 4_096],
                ],
            ],
            $snapshot->jsonSerialize(),
            'Completed profiler messages must preserve timing, nesting, memory, trace, and capture order exactly.',
        );
    }

    public function testCaptureCompletedPrefersExplicitMemoryDiffWithoutMemoryEndpoints(): void
    {
        $snapshot = ProfilingSnapshot::captureCompleted(
            0,
            0.0,
            [
                [
                    'token' => 'block',
                    'context' => [
                        'beginTime' => 100.0,
                        'duration' => 0.01,
                        'memoryDiff' => 123,
                    ],
                ],
            ],
        );

        $entry = $snapshot->entries()[0] ?? self::fail('Expected one profile row.');

        self::assertSame(
            123,
            $entry->memoryDiff,
            'The captured diff must win over the endpoint fallback.',
        );
    }

    public function testCaptureCompletedSkipsEndSampleWhenEndMemoryIsUnknown(): void
    {
        $snapshot = ProfilingSnapshot::captureCompleted(
            0,
            0.0,
            [
                [
                    'token' => 'block',
                    'context' => [
                        'beginTime' => 1.0,
                        'duration' => 0.5,
                        'endTime' => 1.5,
                    ],
                ],
            ],
        );

        self::assertSame(
            [],
            $snapshot->samples(),
            'An end time without end memory must not produce a sample.',
        );
    }

    public function testCaptureCompletedSkipsMessagesWithoutUsableTiming(): void
    {
        $snapshot = ProfilingSnapshot::captureCompleted(
            0,
            0.0,
            [
                ['context' => ['beginTime' => 1.0]],
                ['context' => ['duration' => 0.1]],
                ['context' => 'invalid'],
            ],
        );

        self::assertSame(
            [],
            $snapshot->entries(),
            'Incomplete messages must not produce profile rows.',
        );
        self::assertSame(
            [],
            $snapshot->samples(),
            'Incomplete messages without memory must not produce samples.',
        );
    }

    public function testCapturePairsLoggerMessagesAndHydratesTheResult(): void
    {
        $captured = ProfilingSnapshot::capture(
            4_096,
            0.1,
            [
                ['missing', LogLevel::PROFILE_END, 'application', 0.9, [], 50],
                ['outer', LogLevel::PROFILE_BEGIN, 'application', 1.0, [['file' => '/app/index.php']], 100],
                ['noise', LogLevel::INFO, 'application', 1.01, [], 110],
                ['inner', LogLevel::PROFILE_BEGIN, 'database', 1.02, [], 120],
                ['inner', LogLevel::PROFILE_END, 'database', 1.05, [], 140],
                ['outer', LogLevel::PROFILE_END, 'application', 1.1, [], 200],
            ],
        );

        $payload = $captured->jsonSerialize();

        $snapshot = ProfilingSnapshot::fromArray($payload, '$.panels.profiling');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Profiling payload must round-trip exactly.',
        );
        self::assertSame(
            [
                [
                    'timestamp' => 1_000.0,
                    'duration' => 100.00000000000009,
                    'category' => 'application',
                    'info' => 'outer',
                    'level' => 0,
                    'seq' => 0,
                    'memory' => 200,
                    'memoryDiff' => 100,
                    'trace' => [['file' => '/app/index.php']],
                ],
                [
                    'timestamp' => 1_020.0,
                    'duration' => 30.00000000000003,
                    'category' => 'database',
                    'info' => 'inner',
                    'level' => 1,
                    'seq' => 1,
                    'memory' => 140,
                    'memoryDiff' => 20,
                    'trace' => [],
                ],
            ],
            array_map(static fn($row): array => $row->jsonSerialize(), $snapshot->entries()),
            'Profile begin/end pairs must retain ordering, nesting, timing, memory, and traces.',
        );
        self::assertSame(
            [
                ['time' => 900.0, 'memory' => 50],
                ['time' => 1_000.0, 'memory' => 100],
                ['time' => 1_010.0, 'memory' => 110],
                ['time' => 1_020.0, 'memory' => 120],
                ['time' => 1_050.0, 'memory' => 140],
                ['time' => 1_100.0, 'memory' => 200],
            ],
            array_map(
                static fn(MemorySample $sample): array => ['time' => $sample->time, 'memory' => $sample->memory],
                $captured->samples(),
            ),
            'Logger tuple timestamps and memory values must use the canonical indexes and millisecond conversion.',
        );
        self::assertSame(
            array_map(
                static fn(MemorySample $sample): array => ['time' => $sample->time, 'memory' => $sample->memory],
                $captured->samples(),
            ),
            array_map(
                static fn(MemorySample $sample): array => ['time' => $sample->time, 'memory' => $sample->memory],
                $snapshot->samples(),
            ),
            'Memory samples must survive hydration.',
        );
    }
}

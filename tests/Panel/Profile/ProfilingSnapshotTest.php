<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Profile;

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
    public function testCaptureCompletedNormalizesRowsAndSortsMemorySamples(): void
    {
        $snapshot = ProfilingSnapshot::captureCompleted(
            4_096,
            0.2,
            [
                [
                    'token' => 'SELECT 1',
                    'context' => [
                        'category' => 'Yiisoft\\Db\\Command::query',
                        'beginTime' => 100.05,
                        'endTime' => 100.06,
                        'duration' => 0.01,
                        'beginMemory' => 2_048,
                        'endMemory' => 3_072,
                        'nestedLevel' => 1,
                        'trace' => [['file' => '/app/index.php', 'line' => 12]],
                    ],
                ],
                'malformed',
                ['token' => 'missing context'],
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
                        'nestedLevel' => 0,
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

        self::assertSame([], $snapshot->entries(), 'Incomplete messages must not produce profile rows.');
        self::assertSame([], $snapshot->samples(), 'Incomplete messages without memory must not produce samples.');
    }
}

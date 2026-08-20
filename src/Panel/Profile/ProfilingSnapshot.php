<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Profile;

use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Panel\MemorySample;
use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;
use function count;
use function is_array;
use function max;
use function usort;

/**
 * Canonical profiling snapshot holding the request metrics, the resolved profile blocks, and the memory samples that
 * feed the timeline chart.
 *
 * @phpstan-import-type LogMessage from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
final readonly class ProfilingSnapshot implements PanelSnapshot
{
    /**
     * @param list<ProfileRow> $entries
     * @param list<MemorySample> $samples
     */
    public function __construct(
        public int $memory,
        public float $time,
        private array $entries,
        private array $samples,
    ) {}

    /**
     * Resolves the logger's begin/end pairs into typed blocks and collects the per-message memory samples.
     *
     * @param list<LogMessage> $messages Profile tuples in capture order.
     */
    public static function capture(int $memory, float $time, array $messages): self
    {
        $samples = [];

        foreach ($messages as $message) {
            $sampleMemory = $message[5] ?? null;

            if ($sampleMemory !== null) {
                $samples[] = new MemorySample($message[3] * 1000, $sampleMemory);
            }
        }

        $entries = [];

        foreach (ProfileTimings::calculate($messages) as $timing) {
            $entries[] = ProfileRow::fromTiming($timing, count($entries));
        }

        return new self(
            $memory,
            $time,
            $entries,
            $samples,
        );
    }

    /**
     * Normalizes completed profiler messages carrying token, category, timing, nesting, memory, and trace context.
     *
     * @param array<int|string, mixed> $messages Completed profiler messages.
     */
    public static function captureCompleted(int $memory, float $time, array $messages): self
    {
        $entries = [];
        $samples = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $context = $message['context'] ?? null;

            if (!is_array($context)) {
                continue;
            }

            $beginTime = Coerce::floatOrNull($context['beginTime'] ?? $context['time'] ?? null);
            $endTime = Coerce::floatOrNull($context['endTime'] ?? null);
            $duration = Coerce::floatOrNull($context['duration'] ?? null);

            if ($beginTime === null || $duration === null) {
                continue;
            }

            $beginMemory = Coerce::intOrNull($context['beginMemory'] ?? null);
            $endMemory = Coerce::intOrNull($context['endMemory'] ?? $context['memory'] ?? null);
            $memoryDiff = Coerce::intOrNull($context['memoryDiff'] ?? null)
                ?? (($beginMemory !== null && $endMemory !== null) ? $endMemory - $beginMemory : 0);
            $level = Coerce::intOrNull($context['nestedLevel'] ?? null);

            $entries[] = ProfileRow::fromTiming(
                [
                    'timestamp' => $beginTime,
                    'duration' => $duration,
                    'category' => Coerce::stringOrNull($context['category'] ?? $message['category'] ?? null) ?? '',
                    'info' => Coerce::stringOrNull($message['token'] ?? null) ?? '',
                    'level' => $level === null ? 0 : max(0, $level),
                    'memory' => $endMemory ?? 0,
                    'memoryDiff' => $memoryDiff,
                    'trace' => Coerce::traceFrames($context['trace'] ?? []),
                ],
                count($entries),
            );

            if ($beginMemory !== null) {
                $samples[] = new MemorySample($beginTime * 1000, $beginMemory);
            }

            if ($endTime !== null && $endMemory !== null) {
                $samples[] = new MemorySample($endTime * 1000, $endMemory);
            }
        }

        usort($samples, static fn(MemorySample $a, MemorySample $b): int => $a->time <=> $b->time);

        return new self($memory, $time, $entries, $samples);
    }

    /**
     * @return list<ProfileRow> Resolved profile blocks in capture order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'memory',
                    'time',
                    'entries',
                    'samples',
                ],
            );

        $entries = [];

        foreach ($payload->list('entries') as $index => $entry) {
            $entries[] = ProfileRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        $samples = [];

        foreach ($payload->list('samples') as $index => $sample) {
            $samplePayload = Payload::object($sample, "{$path}.samples[{$index}]")
                ->shape(
                    [
                        'time',
                        'memory',
                    ],
                );
            $samples[] = new MemorySample($samplePayload->number('time'), $samplePayload->int('memory'));
        }

        return new self($payload->int('memory'), $payload->number('time'), $entries, $samples);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'memory' => $this->memory,
            'time' => $this->time,
            'entries' => array_map(static fn(ProfileRow $row): array => $row->jsonSerialize(), $this->entries),
            'samples' => array_map(
                static fn(MemorySample $sample): array => ['time' => $sample->time, 'memory' => $sample->memory],
                $this->samples,
            ),
        ];
    }

    /**
     * @return list<MemorySample> Memory readings recorded alongside each captured profile message.
     */
    public function samples(): array
    {
        return $this->samples;
    }
}

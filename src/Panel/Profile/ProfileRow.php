<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Profile;

use PHPForge\Debug\Storage\{PanelRow, Payload};

use function max;

/**
 * Typed profile block derived once from the Yii logger timings and persisted in that form.
 *
 * @phpstan-import-type ProfileTiming from ProfileTimings
 */
final readonly class ProfileRow implements PanelRow
{
    public function __construct(
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $timestamp,
        /**
         * Block execution time in milliseconds.
         */
        public float $duration,
        /**
         * Profile category attached to the `Yii::beginProfile()` token.
         */
        public string $category,
        /**
         * Profile token / informational label captured at `Yii::beginProfile()`.
         */
        public string $info,
        /**
         * Nesting level of the block, used to render an indentation arrow per level.
         */
        public int $level,
        /**
         * Zero-based sequence index assigned in capture order.
         */
        public int $seq,
        /**
         * Peak memory in bytes recorded when the block closed.
         */
        public int $memory,
        /**
         * Memory delta in bytes between the block's begin and end markers.
         */
        public int $memoryDiff,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured at the `Yii::beginProfile()` call site.
         */
        public array $trace,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'timestamp',
                    'duration',
                    'category',
                    'info',
                    'level',
                    'seq',
                    'memory',
                    'memoryDiff',
                    'trace',
                ],
            );

        return new self(
            timestamp: $payload->number('timestamp'),
            duration: $payload->number('duration'),
            category: $payload->string('category'),
            info: $payload->string('info'),
            level: $payload->int('level'),
            seq: $payload->int('seq'),
            memory: $payload->int('memory'),
            memoryDiff: $payload->int('memoryDiff'),
            trace: $payload->rows('trace'),
        );
    }

    /**
     * Converts one timing returned by {@see ProfileTimings::calculate()} into a typed row.
     *
     * @param ProfileTiming $timing Profile timing entry.
     * @param int $seq Zero-based sequence index to assign.
     */
    public static function fromTiming(array $timing, int $seq): self
    {
        return new self(
            timestamp: $timing['timestamp'] * 1000,
            duration: $timing['duration'] * 1000,
            category: $timing['category'],
            info: $timing['info'],
            level: $timing['level'],
            seq: $seq,
            memory: $timing['memory'],
            memoryDiff: $timing['memoryDiff'],
            trace: $timing['trace'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'duration' => $this->duration,
            'category' => $this->category,
            'info' => $this->info,
            'level' => $this->level,
            'seq' => $this->seq,
            'memory' => $this->memory,
            'memoryDiff' => $this->memoryDiff,
            'trace' => $this->trace,
        ];
    }

    /**
     * Returns the longest block duration in milliseconds, or `0.0` when there are no rows.
     *
     * @param list<self> $rows Captured profile rows.
     */
    public static function maxDuration(array $rows): float
    {
        $maximum = 0.0;

        foreach ($rows as $row) {
            $maximum = max($maximum, $row->duration);
        }

        return $maximum;
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Dump;

use PHPForge\Debug\Storage\{PanelRow, Payload};

/**
 * Typed dump row narrowed once from the Yii logger tuple and persisted in that form.
 *
 * @phpstan-import-type LogMessage from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
final readonly class DumpRow implements PanelRow
{
    public function __construct(
        /**
         * Highlighted dump payload as produced by the Dump collector `varDump()` pipeline.
         */
        public string $message,
        /**
         * Logger level constant ({@see \\PHPForge\\Debug\\Helper\\LogLevel}).
         */
        public int $level,
        /**
         * Log category attached to the dump call.
         */
        public string $category,
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $time,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured at the dump call site.
         */
        public array $trace,
    ) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'message',
                    'level',
                    'category',
                    'time',
                    'trace',
                ],
            );

        return new self(
            message: $payload->string('message'),
            level: $payload->int('level'),
            category: $payload->string('category'),
            time: $payload->number('time'),
            trace: $payload->rows('trace'),
        );
    }

    /**
     * Converts one canonical logger tuple into a typed row.
     *
     * @param LogMessage $message Logger tuple `[message, level, category, timestamp, traces]`.
     */
    public static function fromLoggerTuple(array $message): self
    {
        return new self(
            message: $message[0],
            level: $message[1],
            category: $message[2],
            time: $message[3] * 1000,
            trace: $message[4],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'category' => $this->category,
            'time' => $this->time,
            'trace' => $this->trace,
        ];
    }
}

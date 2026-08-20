<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Log;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;
use function count;

/**
 * Canonical Log panel snapshot holding the captured rows in their typed form.
 *
 * @phpstan-type TraceFrame array<string, mixed>
 * @phpstan-type LogMessage array{
 *   0: string,
 *   1: int,
 *   2: string,
 *   3: float,
 *   4: list<TraceFrame>,
 *   5?: int
 * }
 */
final readonly class LogSnapshot implements PanelSnapshot
{
    /**
     * @param list<LogRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * Converts canonical logger tuples into typed rows, deriving the previous/next links and the inter-row deltas.
     *
     * @param list<LogMessage> $messages Logger tuples in capture order.
     */
    public static function capture(array $messages): self
    {
        $entries = [];

        $count = count($messages);

        $previousId = null;
        $previousTime = null;

        foreach ($messages as $index => $message) {
            $id = $index + 1;

            $timestamp = $message[3];

            $previousTime ??= $timestamp;

            $entries[] = LogRow::fromLoggerTuple(
                $message,
                $id,
                $previousTime,
                $previousId,
                $id < $count ? $id + 1 : null,
            );

            $previousId = $id;
            $previousTime = $timestamp;
        }

        return new self($entries);
    }

    /**
     * @return list<LogRow> Captured rows in capture order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(['entries']);

        $entries = [];

        foreach ($payload->list('entries') as $index => $entry) {
            $entries[] = LogRow::fromArray($entry, "{$path}.entries[{$index}]");
        }

        return new self($entries);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(LogRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}

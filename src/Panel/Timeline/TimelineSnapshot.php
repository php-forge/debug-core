<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Timeline;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

/**
 * Canonical timing and peak-memory snapshot for the Timeline panel.
 */
final readonly class TimelineSnapshot implements PanelSnapshot
{
    /**
     * @param float $start The start time of the timeline snapshot.
     * @param float $end The end time of the timeline snapshot.
     * @param int $memory The peak memory usage at the time of the snapshot.
     */
    public function __construct(public float $start, public float $end, public int $memory) {}

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['start', 'end', 'memory']);

        return new self($payload->number('start'), $payload->number('end'), $payload->int('memory'));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'memory' => $this->memory,
        ];
    }
}

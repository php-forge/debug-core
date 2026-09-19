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
     * Creates the timing and peak-memory snapshot for the request.
     *
     * @param float $start The start time of the timeline snapshot.
     * @param float $end The end time of the timeline snapshot.
     * @param int $memory The peak memory usage at the time of the snapshot.
     */
    public function __construct(public float $start, public float $end, public int $memory) {}

    /**
     * Hydrates the Timeline panel snapshot from decoded JSON data.
     *
     * @param mixed $data Decoded Timeline panel payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated timing and peak-memory snapshot.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(['start', 'end', 'memory']);

        return new self($payload->number('start'), $payload->number('end'), $payload->int('memory'));
    }

    /**
     * Returns the snapshot for JSON serialization.
     *
     * @return array<string, mixed> Serialized start time, end time, and peak memory.
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

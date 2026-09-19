<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;

/**
 * Canonical Event panel snapshot holding the captured rows in their typed form.
 */
final readonly class EventSnapshot implements PanelSnapshot
{
    /**
     * Creates a snapshot from the captured event rows.
     *
     * @param list<EventRow> $entries Captured rows in fire order.
     */
    public function __construct(private array $entries) {}

    /**
     * Returns the captured event rows.
     *
     * @return list<EventRow> Captured rows in fire order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Hydrates the Event panel snapshot from decoded JSON data.
     *
     * @param mixed $data Decoded Event panel payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated snapshot carrying the typed rows.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        return new self(
            Payload::object($data, $path)
                ->shape(['entries'])
                ->mapList('entries', EventRow::fromArray(...)),
        );
    }

    /**
     * Returns the snapshot for JSON serialization.
     *
     * @return array<string, mixed> Serialized event rows in fire order.
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(EventRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}

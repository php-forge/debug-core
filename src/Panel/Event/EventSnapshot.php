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
     * @param list<EventRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * @return list<EventRow> Captured rows in fire order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public static function fromArray(mixed $data, string $path): self
    {
        return new self(
            Payload::object($data, $path)
                ->shape(['entries'])
                ->mapList('entries', EventRow::fromArray(...)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(EventRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Mail;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;
use function is_array;

/**
 * Canonical Mail panel snapshot holding the captured messages in their typed form.
 */
final readonly class MailSnapshot implements PanelSnapshot
{
    /**
     * @param list<MailEntry> $entries Captured messages in send order.
     */
    public function __construct(private array $entries) {}

    /**
     * Narrows the captured `EVENT_AFTER_SEND` payloads into typed messages.
     *
     * @param array<array-key, mixed> $messages Captured payloads in send order; non-array entries are dropped.
     *
     * @return self Snapshot carrying the typed messages.
     */
    public static function capture(array $messages): self
    {
        $entries = [];

        foreach ($messages as $message) {
            if (is_array($message)) {
                $entries[] = MailEntry::fromCapture($message);
            }
        }

        return new self($entries);
    }

    /**
     * Returns the captured messages.
     *
     * @return list<MailEntry> Captured messages in send order.
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Narrows the persisted mail payload into a typed snapshot.
     *
     * @param mixed $data Persisted payload, expected to be an object carrying an `entries` list.
     * @param string $path JSON path of the payload, used to report a malformed capture.
     *
     * @return self Snapshot carrying the persisted messages.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        return new self(
            Payload::object($data, $path)
                ->shape(['entries'])
                ->mapList('entries', MailEntry::fromArray(...)),
        );
    }

    /**
     * Serializes the snapshot into its persisted payload.
     *
     * @return array<string, mixed> Payload carrying the messages under the `entries` key.
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(MailEntry $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}

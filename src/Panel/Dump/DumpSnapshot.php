<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Dump;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_map;

/**
 * Canonical Dump panel snapshot holding the captured rows in their typed form.
 *
 * @phpstan-import-type LogTuple from \PHPForge\Debug\Panel\Log\LogSnapshot
 */
final readonly class DumpSnapshot implements PanelSnapshot
{
    /**
     * @param list<DumpRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * Converts canonical logger tuples into typed rows.
     *
     * @param list<LogTuple> $messages Logger tuples in capture order.
     */
    public static function capture(array $messages): self
    {
        $entries = [];

        foreach ($messages as $message) {
            $entries[] = DumpRow::fromLoggerTuple($message);
        }

        return new self($entries);
    }

    /**
     * @return list<DumpRow> Captured rows in capture order.
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
                ->mapList('entries', DumpRow::fromArray(...)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(DumpRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}

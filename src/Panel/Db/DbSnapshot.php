<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use PHPForge\Debug\Storage\{PanelSnapshot, Payload};

use function array_count_values;
use function array_map;

/**
 * Canonical database panel snapshot holding the resolved query rows in their typed form.
 */
final readonly class DbSnapshot implements PanelSnapshot
{
    /**
     * @param list<QueryRow> $entries
     */
    public function __construct(private array $entries) {}

    /**
     * Resolves exact SQL duplicates while preserving capture order and the persisted row contract.
     *
     * @param list<QueryRow> $rows
     */
    public static function capture(array $rows): self
    {
        $occurrences = array_count_values(array_map(static fn(QueryRow $row): string => $row->getQuery(), $rows));

        return new self(
            array_map(
                static fn(QueryRow $row): QueryRow => $row->withDuplicate(
                    $occurrences[$row->getQuery()] ?? $row->getDuplicate(),
                ),
                $rows,
            ),
        );
    }

    /**
     * @return list<QueryRow> Executed statements in capture order.
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
                ->mapList('entries', QueryRow::fromArray(...)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'entries' => array_map(static fn(QueryRow $row): array => $row->jsonSerialize(), $this->entries),
        ];
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use PHPForge\Debug\Storage\{PanelRow, Payload};

use function array_key_exists;
use function count;

/**
 * Typed event row recorded by the wildcard listener and persisted in that form.
 */
final class EventRow implements PanelRow
{
    /**
     * Optional diagnostics are assigned only on enriched copies; captured rows remain immutable.
     */
    private EventInspection|null $inspection = null;

    public function __construct(
        /**
         * Capture timestamp in seconds since the Unix epoch.
         */
        public readonly float $time,
        /**
         * Event name (for example, `EVENT_AFTER_REQUEST`).
         */
        public readonly string $name,
        /**
         * Fully qualified class name of the event object.
         */
        public readonly string $class,
        /**
         * `'1'` when the event was triggered statically (no sender), `'0'` otherwise.
         *
         * Stored as a string so the value round-trips through the search model's `boolean` rule.
         */
        public readonly string $isStatic,
        /**
         * Fully qualified class name of the sender, or `''` when the event was triggered statically.
         */
        public readonly string $senderClass,
    ) {}

    /**
     * Returns how many distinct event classes the given rows cover.
     *
     * @param list<self> $rows Captured event rows.
     *
     * @return int Number of distinct event classes across the given rows.
     */
    public static function distinctClassCount(array $rows): int
    {
        $classes = [];

        foreach ($rows as $row) {
            if ($row->class !== '') {
                $classes[$row->class] = null;
            }
        }

        return count($classes);
    }

    /**
     * Hydrates an event row from decoded JSON data.
     *
     * @param mixed $data Decoded event row payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated row, enriched with diagnostics when the payload carried them.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'time',
                    'name',
                    'class',
                    'isStatic',
                    'senderClass',
                ],
                ['inspection'],
            );

        $row = new self(
            time: $payload->number('time'),
            name: $payload->string('name'),
            class: $payload->string('class'),
            isStatic: $payload->string('isStatic'),
            senderClass: $payload->string('senderClass'),
        );

        return array_key_exists('inspection', $payload->all())
            ? $row->withInspection(EventInspection::fromArray($payload->raw('inspection'), "{$path}.inspection"))
            : $row;
    }

    /**
     * Returns the optional diagnostics attached to the row.
     *
     * @return EventInspection|null Attached diagnostics, or `null` when the row was captured without them.
     */
    public function inspection(): EventInspection|null
    {
        return $this->inspection;
    }

    /**
     * Returns the row for JSON serialization.
     *
     * @return array<string, mixed> Serialized row fields, including the diagnostics when the row carries them.
     */
    public function jsonSerialize(): array
    {
        return [
            'time' => $this->time,
            'name' => $this->name,
            'class' => $this->class,
            'isStatic' => $this->isStatic,
            'senderClass' => $this->senderClass,
            ...isset($this->inspection) ? ['inspection' => $this->inspection->jsonSerialize()] : [],
        ];
    }

    /**
     * Returns how many of the given rows were triggered statically.
     *
     * @param list<self> $rows Captured event rows.
     *
     * @return int Number of statically triggered rows.
     */
    public static function staticCount(array $rows): int
    {
        $static = 0;

        foreach ($rows as $row) {
            if ($row->isStatic === '1') {
                $static++;
            }
        }

        return $static;
    }

    /**
     * Returns an enriched copy without changing the constructor or the original captured row.
     *
     * @param EventInspection $inspection Optional diagnostics to attach.
     *
     * @return self Row carrying the diagnostics.
     */
    public function withInspection(EventInspection $inspection): self
    {
        $clone = clone $this;
        $clone->inspection = $inspection;

        return $clone;
    }
}

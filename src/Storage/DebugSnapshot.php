<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;

use function array_map;

/**
 * Represents the versioned envelope persisted for one captured request.
 */
final readonly class DebugSnapshot implements JsonSerializable
{
    public const int VERSION = 4;

    /**
     * Creates an envelope from request metadata, panel payloads, and capture failures.
     *
     * @param RequestSummary $summary Captured request metadata.
     * @param array<string, array<string, mixed>> $panels Serialized panel payloads indexed by panel ID.
     * @param array<string, PanelFailure> $failures Panel failures indexed by panel ID.
     */
    public function __construct(public RequestSummary $summary, public array $panels, public array $failures) {}

    /**
     * Hydrates a versioned request envelope from decoded JSON data.
     *
     * Usage example:
     *
     * ```php
     * $snapshot = \PHPForge\Debug\Storage\DebugSnapshot::fromArray($data);
     * ```
     *
     * @param mixed $data Decoded snapshot envelope.
     *
     * @return self Hydrated request snapshot.
     */
    public static function fromArray(mixed $data): self
    {
        $payload = Payload::object($data)
            ->shape(
                [
                    'version',
                    'summary',
                    'panels',
                    'failures',
                ],
            );

        if ($payload->int('version') !== self::VERSION) {
            throw HydrationException::at(
                '$.version',
                'storage version ' . self::VERSION,
            );
        }

        $panels = [];

        foreach ($payload->map('panels') as $id => $panel) {
            $panels[$id] = Payload::object($panel, "$.panels.{$id}")
                ->all();
        }

        $failures = [];

        foreach ($payload->map('failures') as $id => $failure) {
            $failures[$id] = PanelFailure::fromArray($failure, "$.failures.{$id}");
        }

        return new self(
            RequestSummary::fromArray($payload->raw('summary')),
            $panels,
            $failures,
        );
    }

    /**
     * Returns the request envelope for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $data = $snapshot->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Versioned request envelope.
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => self::VERSION,
            'summary' => $this->summary->jsonSerialize(),
            'panels' => $this->panels,
            'failures' => array_map(
                static fn(PanelFailure $failure): array => $failure->jsonSerialize(),
                $this->failures,
            ),
        ];
    }
}

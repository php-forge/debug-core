<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;

use function array_map;

/**
 * Represents the versioned request-summary index.
 */
final readonly class Manifest implements JsonSerializable
{
    /**
     * Creates a manifest indexed by request tag.
     *
     * @param array<string, RequestSummary> $entries Request summaries indexed by tag.
     */
    public function __construct(public array $entries) {}

    /**
     * Hydrates a versioned manifest from decoded JSON data.
     *
     * Usage example:
     *
     * ```php
     * $manifest = \PHPForge\Debug\Storage\Manifest::fromArray($data);
     * ```
     *
     * @param mixed $data Decoded manifest payload.
     *
     * @return self Hydrated request-summary index.
     */
    public static function fromArray(mixed $data): self
    {
        $payload = Payload::object($data)
            ->shape(
                [
                    'version',
                    'entries',
                ],
            );

        if ($payload->int('version') !== DebugSnapshot::VERSION) {
            throw HydrationException::at(
                '$.version',
                'storage version ' . DebugSnapshot::VERSION,
            );
        }

        $entries = [];

        foreach ($payload->map('entries') as $tag => $entry) {
            $summary = RequestSummary::fromArray($entry, "$.entries.{$tag}");

            if ($summary->tag !== $tag) {
                throw HydrationException::at(
                    "$.entries.{$tag}.tag",
                    "the manifest key '{$tag}'",
                );
            }

            $entries[$tag] = $summary;
        }

        return new self(
            $entries,
        );
    }

    /**
     * Returns the versioned manifest for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $data = (new \PHPForge\Debug\Storage\Manifest([]))->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Versioned manifest payload.
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => DebugSnapshot::VERSION,
            'entries' => array_map(
                static fn(RequestSummary $summary): array => $summary->jsonSerialize(),
                $this->entries,
            ),
        ];
    }
}

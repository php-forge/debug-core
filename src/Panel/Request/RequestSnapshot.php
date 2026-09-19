<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Storage\{DebugArray, HydrationException, PanelSnapshot, Payload};

use function is_int;

/**
 * Canonical Request panel snapshot.
 */
final readonly class RequestSnapshot implements PanelSnapshot
{
    /**
     * Creates a snapshot from the tagged Request payload.
     *
     * @param DebugArray $data Tagged Request payload captured for the request.
     * @param int $statusCode HTTP status code the request completed with.
     */
    private function __construct(private DebugArray $data, public int $statusCode) {}

    /**
     * Captures the Request payload together with the status code it must agree with.
     *
     * @param array<array-key, mixed> $data Raw Request payload captured for the request.
     *
     * @throws HydrationException When the payload carries no integer `statusCode`.
     *
     * @return self Snapshot carrying the tagged payload and its status code.
     */
    public static function capture(array $data): self
    {
        $statusCode = $data['statusCode'] ?? null;

        if (!is_int($statusCode)) {
            throw HydrationException::at('$.panels.request.statusCode', 'an integer');
        }

        return new self(DebugArray::capture($data), $statusCode);
    }

    /**
     * Returns the payload restored to plain PHP values.
     *
     * @return array<array-key, mixed> Payload restored to plain PHP values.
     */
    public function data(): array
    {
        return $this->data->values();
    }

    /**
     * Hydrates the Request panel snapshot from decoded JSON data.
     *
     * @param mixed $data Decoded Request panel payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @throws HydrationException When the stored status code disagrees with the one held in the payload.
     *
     * @return self Hydrated Request panel snapshot.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(['data', 'statusCode']);
        $snapshotData = DebugArray::fromArray($payload->raw('data'), "{$path}.data");

        $statusCode = $payload->int('statusCode');
        $values = $snapshotData->values();

        if (($values['statusCode'] ?? null) !== $statusCode) {
            throw HydrationException::at(
                "{$path}.statusCode",
                'the status code stored in data',
            );
        }

        return new self($snapshotData, $statusCode);
    }

    /**
     * Returns the snapshot for JSON serialization.
     *
     * @return array<string, mixed> Tagged payload and the status code it agrees with.
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data->jsonSerialize(),
            'statusCode' => $this->statusCode,
        ];
    }
}

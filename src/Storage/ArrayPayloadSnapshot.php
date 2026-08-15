<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

/**
 * Provides the capture/hydrate/serialize cycle for panels whose payload stays genuinely dynamic (application
 * configuration, dumped values, and user-configured request globals).
 *
 * The using class returns the JSON key its payload is persisted under from {@see payloadKey()}, and exposes the payload
 * through a domain-named accessor delegating to {@see values()}.
 */
trait ArrayPayloadSnapshot
{
    /**
     * Creates a snapshot from a tagged dynamic payload.
     *
     * @param DebugArray $payload Tagged dynamic payload.
     */
    final public function __construct(private readonly DebugArray $payload) {}

    /**
     * Captures arbitrary array values in a JSON-safe snapshot.
     *
     * Usage example:
     *
     * ```php
     * $prototype = new class(\PHPForge\Debug\Storage\DebugArray::capture([])) {
     *     use \PHPForge\Debug\Storage\ArrayPayloadSnapshot;
     *
     *     protected static function payloadKey(): string
     *     {
     *         return 'data';
     *     }
     * };
     * $snapshot = $prototype::capture(['enabled' => true]);
     * ```
     *
     * @param array<array-key, mixed> $values Raw payload captured for the request.
     *
     * @return self Snapshot containing tagged debug values.
     */
    public static function capture(array $values): self
    {
        return new self(
            DebugArray::capture($values),
        );
    }

    /**
     * Hydrates a dynamic payload from decoded JSON data.
     *
     * Usage example:
     *
     * ```php
     * $prototype = new class(\PHPForge\Debug\Storage\DebugArray::capture([])) {
     *     use \PHPForge\Debug\Storage\ArrayPayloadSnapshot;
     *
     *     protected static function payloadKey(): string
     *     {
     *         return 'data';
     *     }
     * };
     * $data = $prototype::capture(['enabled' => true])->jsonSerialize();
     * $snapshot = $prototype::fromArray($data, '$.panel');
     * ```
     *
     * @param mixed $data Decoded JSON payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated dynamic payload snapshot.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $key = self::payloadKey();

        return new self(
            Payload::object($data, $path)
                ->shape([$key])
                ->debugArray($key),
        );
    }

    /**
     * Returns the tagged payload for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $prototype = new class(\PHPForge\Debug\Storage\DebugArray::capture([])) {
     *     use \PHPForge\Debug\Storage\ArrayPayloadSnapshot;
     *
     *     protected static function payloadKey(): string
     *     {
     *         return 'data';
     *     }
     * };
     * $data = $prototype::capture(['enabled' => true])->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Tagged payload indexed by its persistence key.
     */
    public function jsonSerialize(): array
    {
        return [
            self::payloadKey() => $this->payload->jsonSerialize(),
        ];
    }

    /**
     * Returns the JSON key used to persist the dynamic payload.
     *
     * @return string Persisted payload key.
     */
    abstract protected static function payloadKey(): string;

    /**
     * Returns the payload restored to plain PHP values.
     *
     * @return array<array-key, mixed> Payload restored to plain PHP values.
     */
    private function values(): array
    {
        return $this->payload->values();
    }
}

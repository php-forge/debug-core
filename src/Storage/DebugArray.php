<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;
use SensitiveParameter;

/**
 * Represents a PHP array with arbitrary nested values as JSON-safe debug data.
 */
final readonly class DebugArray implements JsonSerializable
{
    /**
     * Creates an array facade over a tagged debug value.
     *
     * @param DebugValue $value Tagged array value.
     */
    private function __construct(private DebugValue $value) {}

    /**
     * Captures an array as tagged debug data.
     *
     * Usage example:
     *
     * ```php
     * $array = \PHPForge\Debug\Storage\DebugArray::capture(['enabled' => true]);
     * ```
     *
     * @param array<array-key, mixed> $value PHP values to capture.
     *
     * @return self Tagged array facade.
     */
    public static function capture(#[SensitiveParameter] array $value): self
    {
        return new self(DebugValue::capture($value));
    }

    /**
     * Hydrates a tagged debug array from decoded JSON data.
     *
     * Usage example:
     *
     * ```php
     * $data = \PHPForge\Debug\Storage\DebugArray::capture(['enabled' => true])->jsonSerialize();
     * $array = \PHPForge\Debug\Storage\DebugArray::fromArray($data, '$.panel.data');
     * ```
     *
     * @param mixed $value Decoded tagged value.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated array facade.
     */
    public static function fromArray(mixed $value, string $path): self
    {
        $debugValue = DebugValue::fromArray($value, $path);

        if ($debugValue->type !== 'array') {
            throw HydrationException::at(
                $path,
                'a tagged array',
            );
        }

        return new self($debugValue);
    }

    /**
     * Returns the tagged array for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $data = \PHPForge\Debug\Storage\DebugArray::capture(['enabled' => true])->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Tagged array payload.
     */
    public function jsonSerialize(): array
    {
        return $this->value->jsonSerialize();
    }

    /**
     * Returns captured entries as display-safe PHP values.
     *
     * Usage example:
     *
     * ```php
     * $values = \PHPForge\Debug\Storage\DebugArray::capture(['enabled' => true])->values();
     * ```
     *
     * @return array<array-key, mixed> Display-safe PHP values.
     */
    public function values(): array
    {
        return $this->value->toDisplayEntries();
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_values;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;

/**
 * Provides strict reads for DTO factories at the decoded-JSON boundary.
 *
 * It validates types and shapes without accepting numeric strings, casting values, or supplying implicit defaults.
 */
final readonly class Payload
{
    /**
     * Creates a strict reader at a payload path.
     *
     * @param array<string, mixed> $data Decoded JSON object.
     * @param string $path Object path used in hydration errors.
     */
    private function __construct(private array $data, private string $path) {}

    /**
     * Returns every decoded field without conversion.
     *
     * @return array<string, mixed> Decoded object fields.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Returns a required boolean field.
     *
     * @param string $key Required field name.
     *
     * @return bool Boolean field value.
     */
    public function bool(string $key): bool
    {
        $value = $this->value($key);

        if (!is_bool($value)) {
            throw HydrationException::at(
                $this->keyPath($key),
                'a boolean',
            );
        }

        return $value;
    }

    /**
     * Reads a tagged array value, keeping the path of the enclosing payload for error reporting.
     *
     * @param string $key Required field name.
     *
     * @return DebugArray Hydrated tagged array.
     */
    public function debugArray(string $key): DebugArray
    {
        return DebugArray::fromArray($this->value($key), $this->keyPath($key));
    }

    /**
     * Returns a required integer field.
     *
     * @param string $key Required field name.
     *
     * @return int Integer field value.
     */
    public function int(string $key): int
    {
        $value = $this->value($key);

        if (!is_int($value)) {
            throw HydrationException::at(
                $this->keyPath($key),
                'an integer',
            );
        }

        return $value;
    }

    /**
     * Returns a required list field.
     *
     * @param string $key Required field name.
     *
     * @return list<mixed> List field value.
     */
    public function list(string $key): array
    {
        $value = $this->value($key);

        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::at(
                $this->keyPath($key),
                'a list',
            );
        }

        return $value;
    }

    /**
     * Returns a required JSON object as a `string`-keyed array.
     *
     * @param string $key Required field name.
     *
     * @return array<string, mixed> Object field value.
     */
    public function map(string $key): array
    {
        return self::object($this->value($key), $this->keyPath($key))->data;
    }

    /**
     * Returns an integer field or `null`.
     *
     * @param string $key Required field name.
     *
     * @return int|null Integer field value or `null`.
     */
    public function nullableInt(string $key): int|null
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if (!is_int($value)) {
            throw HydrationException::at(
                $this->keyPath($key),
                'an integer or null',
            );
        }

        return $value;
    }

    /**
     * Returns a numeric field as a float or `null`.
     *
     * @param string $key Required field name.
     *
     * @return float|null Numeric field value or `null`.
     */
    public function nullableNumber(string $key): float|null
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if (!is_int($value) && (!is_float($value) || !is_finite($value))) {
            throw HydrationException::at(
                $this->keyPath($key),
                'a number or null',
            );
        }

        return $value;
    }

    /**
     * Returns a string field or `null`.
     *
     * @param string $key Required field name.
     *
     * @return string|null String field value or `null`.
     */
    public function nullableString(string $key): string|null
    {
        $value = $this->value($key);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw HydrationException::at(
                $this->keyPath($key),
                'a string or null',
            );
        }

        return $value;
    }

    /**
     * Returns a required numeric field as a float.
     *
     * @param string $key Required field name.
     *
     * @return float Numeric field value.
     */
    public function number(string $key): float
    {
        $value = $this->value($key);

        if (!is_int($value) && (!is_float($value) || !is_finite($value))) {
            throw HydrationException::at(
                $this->keyPath($key),
                'a number',
            );
        }

        return (float) $value;
    }

    /**
     * Creates a strict reader for a decoded JSON object.
     *
     * @param mixed $value Decoded JSON value.
     * @param string $path Object path used in hydration errors.
     *
     * @return self Strict object reader.
     */
    public static function object(mixed $value, string $path = '$'): self
    {
        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            throw HydrationException::at(
                $path,
                'an object',
            );
        }

        foreach ($value as $key => $_) {
            if (!is_string($key)) {
                throw HydrationException::at(
                    $path,
                    'an object with string keys',
                );
            }
        }

        /** @var array<string, mixed> $value */
        return new self(
            $value,
            $path,
        );
    }

    /**
     * Returns a required field without conversion.
     *
     * @param string $key Required field name.
     *
     * @return mixed Unconverted field value.
     */
    public function raw(string $key): mixed
    {
        return $this->value($key);
    }

    /**
     * Reads a list of JSON objects, validating each element's shape but leaving its values untouched.
     *
     * @param string $key Required field name.
     *
     * @return list<array<string, mixed>> Validated object rows.
     */
    public function rows(string $key): array
    {
        $path = $this->keyPath($key);

        $rows = [];

        foreach ($this->list($key) as $index => $row) {
            $rows[] = self::object($row, "{$path}[{$index}]")->data;
        }

        return $rows;
    }

    /**
     * Validates required, optional, and undeclared fields.
     *
     * @param list<string> $required Required field names.
     * @param list<string> $optional Optional field names.
     *
     * @return self Validated object reader.
     */
    public function shape(array $required, array $optional = []): self
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $this->data)) {
                throw HydrationException::at(
                    "{$this->path}.{$key}",
                    'a required field',
                );
            }
        }

        $unknown = array_diff(array_keys($this->data), [...$required, ...$optional]);

        if ($unknown !== []) {
            $key = array_values($unknown)[0];

            throw HydrationException::at(
                "{$this->path}.{$key}",
                'a declared field',
            );
        }

        return $this;
    }

    /**
     * Returns a required `string` field.
     *
     * @param string $key Required field name.
     *
     * @return string String field value.
     */
    public function string(string $key): string
    {
        $value = $this->value($key);

        if (!is_string($value)) {
            throw HydrationException::at(
                $this->keyPath($key),
                'a string',
            );
        }

        return $value;
    }

    /**
     * Returns the absolute payload path for a field.
     *
     * @param string $key Field name.
     *
     * @return string Absolute field path.
     */
    private function keyPath(string $key): string
    {
        return "{$this->path}.{$key}";
    }

    /**
     * Returns a required field without conversion.
     *
     * @param string $key Required field name.
     *
     * @return mixed Unconverted field value.
     */
    private function value(string $key): mixed
    {
        if (!array_key_exists($key, $this->data)) {
            throw HydrationException::at(
                $this->keyPath($key),
                'a required field',
            );
        }

        return $this->data[$key];
    }
}

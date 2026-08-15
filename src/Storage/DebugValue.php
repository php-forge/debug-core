<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use Closure;
use JsonSerializable;
use SplObjectStorage;
use Stringable;
use Throwable;

use function array_map;
use function base64_decode;
use function base64_encode;
use function get_object_vars;
use function get_resource_type;
use function in_array;
use function is_array;
use function is_bool;
use function is_finite;
use function is_float;
use function is_int;
use function is_nan;
use function is_object;
use function is_resource;
use function is_string;
use function mb_check_encoding;
use function sprintf;

/**
 * Represents an arbitrary PHP value as JSON-safe tagged data.
 *
 * Hydration deliberately recreates this value object rather than executable objects, resources, or closures.
 */
final readonly class DebugValue implements JsonSerializable
{
    private const int MAX_DEPTH = 10;
    private const int MAX_NODES = 10000;

    /**
     * Creates a tagged debug value from normalized fields.
     *
     * @param string $type Tagged value type.
     * @param bool|float|int|string|null $value Captured scalar value or display label.
     * @param list<array{keyType: int|string, key: int|string, value: self}> $entries Captured child values.
     * @param string|null $className Captured object class or `null` for non-object values.
     * @param string|null $resourceType Captured resource type or `null` for non-resource values.
     * @param string|null $reason Truncation or unsupported-value reason, or `null` when not applicable.
     */
    private function __construct(
        public string $type,
        public bool|float|int|string|null $value = null,
        public array $entries = [],
        public string|null $className = null,
        public string|null $resourceType = null,
        public string|null $reason = null,
    ) {}

    /**
     * Captures an arbitrary PHP value as JSON-safe tagged data.
     *
     * Usage example:
     *
     * ```php
     * $value = \PHPForge\Debug\Storage\DebugValue::capture(['enabled' => true]);
     * ```
     *
     * @param mixed $value PHP value to capture.
     *
     * @return self Tagged debug value.
     */
    public static function capture(mixed $value): self
    {
        $objects = new SplObjectStorage();

        $nodes = 0;

        return self::normalize($value, 0, $nodes, $objects);
    }

    /**
     * Hydrates a tagged debug value from decoded JSON data.
     *
     * Usage example:
     *
     * ```php
     * $value = \PHPForge\Debug\Storage\DebugValue::fromArray(['type' => 'int', 'value' => 42]);
     * ```
     *
     * @param mixed $data Decoded tagged value.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated debug value.
     */
    public static function fromArray(mixed $data, string $path = '$'): self
    {
        $payload = Payload::object($data, $path);

        $type = $payload->string('type');

        $payload->shape(
            match ($type) {
                'null' => ['type'],
                'bool', 'int', 'float', 'special-float', 'string' => ['type', 'value'],
                'binary' => ['type', 'encoding', 'data'],
                'array' => ['type', 'entries'],
                'object' => ['type', 'value', 'entries', 'class'],
                'resource' => ['type', 'resourceType'],
                'truncated', 'recursion', 'unsupported' => ['type', 'value', 'reason'],
                default => throw HydrationException::at(
                    "{$path}.type",
                    'a known debug-value type',
                ),
            }
        );

        return match ($type) {
            'null' => new self('null'),
            'bool' => new self('bool', $payload->bool('value')),
            'int' => new self('int', $payload->int('value')),
            'float' => new self('float', $payload->number('value')),
            'special-float' => self::fromSpecialFloat($payload, $path),
            'string' => new self('string', $payload->string('value')),
            'binary' => self::fromBinary($payload, $path),
            'array' => new self('array', entries: self::hydrateEntries($payload, $path)),
            'object' => new self(
                'object',
                value: $payload->nullableString('value'),
                entries: self::hydrateEntries($payload, $path),
                className: $payload->string('class'),
            ),
            'resource' => new self(
                'resource',
                resourceType: $payload->string('resourceType'),
            ),
            'truncated', 'recursion', 'unsupported' => new self(
                $type,
                value: $payload->nullableString('value'),
                reason: $payload->string('reason'),
            ),
        };
    }

    /**
     * Returns the tagged value for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $data = \PHPForge\Debug\Storage\DebugValue::capture(['enabled' => true])->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Tagged debug value payload.
     */
    public function jsonSerialize(): array
    {
        $data = ['type' => $this->type];

        if (in_array($this->type, ['bool', 'int', 'float', 'special-float', 'string'], true)) {
            $data['value'] = $this->value;
        }

        if ($this->type === 'binary') {
            $data['encoding'] = 'base64';

            $data['data'] = base64_encode(is_string($this->value) ? $this->value : '');
        }

        if ($this->type === 'array' || $this->type === 'object') {
            $data['entries'] = array_map(
                static fn(array $entry): array => [
                    'keyType' => $entry['keyType'],
                    'key' => $entry['key'],
                    'value' => $entry['value']->jsonSerialize(),
                ],
                $this->entries,
            );
        }

        if ($this->className !== null) {
            $data['class'] = $this->className;
        }

        if ($this->resourceType !== null) {
            $data['resourceType'] = $this->resourceType;
        }

        if ($this->reason !== null) {
            $data['reason'] = $this->reason;

            $data['value'] = is_string($this->value) ? $this->value : null;
        }

        if ($this->type === 'object') {
            $data['value'] = is_string($this->value) ? $this->value : null;
        }

        return $data;
    }

    /**
     * Returns the entries as a plain PHP array, keeping the key types captured from the original value.
     *
     * Usage example:
     *
     * ```php
     * $entries = \PHPForge\Debug\Storage\DebugValue::capture(['enabled' => true])->toDisplayEntries();
     * ```
     *
     * @return array<int|string, mixed> Display-safe child values retaining captured key types.
     */
    public function toDisplayEntries(): array
    {
        $result = [];

        foreach ($this->entries as $entry) {
            $result[$entry['key']] = $entry['value']->toDisplayValue();
        }

        return $result;
    }

    /**
     * Returns a safe PHP representation suitable for existing dump and table renderers.
     *
     * Usage example:
     *
     * ```php
     * $displayValue = \PHPForge\Debug\Storage\DebugValue::capture(new \stdClass())->toDisplayValue();
     * ```
     *
     * @return mixed Display-safe PHP value.
     */
    public function toDisplayValue(): mixed
    {
        return match ($this->type) {
            'null' => null,
            'bool', 'int', 'float', 'string' => $this->value,
            'special-float', 'binary', 'resource', 'truncated', 'recursion', 'unsupported' => $this->displayLabel(),
            'array' => $this->toDisplayEntries(),
            'object' => ['__class' => $this->className ?? 'object'] + $this->toDisplayEntries(),
            default => '(unsupported)',
        };
    }

    /**
     * Returns the display label for a non-scalar tagged value.
     *
     * @return string Display-safe label.
     */
    private function displayLabel(): string
    {
        return match ($this->type) {
            'binary' => sprintf(
                '(binary: base64 %s)',
                base64_encode(is_string($this->value) ? $this->value : ''),
            ),
            'resource' => sprintf('(resource: %s)', $this->resourceType ?? 'unknown'),
            default => is_string($this->value) && $this->value !== ''
                ? $this->value
                : sprintf('(%s: %s)', $this->type, $this->reason ?? 'unknown'),
        };
    }

    /**
     * Hydrates a base64-encoded binary value.
     *
     * @param Payload $payload Validated tagged value payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated binary value.
     */
    private static function fromBinary(Payload $payload, string $path): self
    {
        if ($payload->string('encoding') !== 'base64') {
            throw HydrationException::at(
                "{$path}.encoding",
                'base64',
            );
        }

        $decoded = base64_decode($payload->string('data'), true);

        if ($decoded === false) {
            throw HydrationException::at(
                "{$path}.data",
                'valid base64 data',
            );
        }

        return new self(
            'binary',
            $decoded,
        );
    }

    /**
     * Hydrates a non-finite floating-point label.
     *
     * @param Payload $payload Validated tagged value payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated non-finite floating-point value.
     */
    private static function fromSpecialFloat(Payload $payload, string $path): self
    {
        $value = $payload->string('value');

        if (!in_array($value, ['NAN', 'INF', '-INF'], true)) {
            throw HydrationException::at(
                "{$path}.value",
                'NAN, INF, or -INF',
            );
        }

        return new self(
            'special-float',
            $value,
        );
    }

    /**
     * Hydrates tagged array or object entries.
     *
     * @param Payload $payload Validated tagged value payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return list<array{keyType: 'int'|'string', key: int|string, value: self}> Hydrated entries.
     */
    private static function hydrateEntries(Payload $payload, string $path): array
    {
        $entries = [];

        foreach ($payload->list('entries') as $index => $rawEntry) {
            $entryPath = "{$path}.entries[{$index}]";

            $entry = Payload::object($rawEntry, $entryPath)
                ->shape(
                    [
                        'keyType',
                        'key',
                        'value',
                    ],
                );
            $keyType = $entry->string('keyType');
            $key = $entry->raw('key');

            if (
                ($keyType !== 'int' && $keyType !== 'string')
                || ($keyType === 'int' && !is_int($key))
                || ($keyType === 'string' && !is_string($key))
            ) {
                throw HydrationException::at(
                    "{$entryPath}.key",
                    'a key matching keyType',
                );
            }

            $entries[] = [
                'keyType' => $keyType,
                'key' => $key,
                'value' => self::fromArray($entry->raw('value'), "{$entryPath}.value"),
            ];
        }

        return $entries;
    }

    /**
     * Normalizes a PHP value while enforcing depth and node limits.
     *
     * @param mixed $value PHP value to normalize.
     * @param int $depth Current nesting depth.
     * @param int $nodes Number of values visited across the capture operation.
     * @param SplObjectStorage<object, mixed> $objects Objects active in the current traversal path.
     *
     * @return self Normalized debug value.
     */
    private static function normalize(
        mixed $value,
        int $depth,
        int &$nodes,
        SplObjectStorage $objects,
    ): self {
        if (++$nodes > self::MAX_NODES) {
            return new self(
                'truncated',
                value: '*SKIPPED over 10000 nodes*',
                reason: 'size',
            );
        }

        if ($depth > self::MAX_DEPTH) {
            return new self(
                'truncated',
                value: '*DEEP NESTED VALUE*',
                reason: 'depth',
            );
        }

        if ($value === null) {
            return new self(
                'null',
            );
        }

        if (is_bool($value)) {
            return new self(
                'bool',
                $value,
            );
        }

        if (is_int($value)) {
            return new self(
                'int',
                $value,
            );
        }

        if (is_float($value)) {
            return is_finite($value)
                ? new self(
                    'float',
                    $value,
                )
                : new self(
                    'special-float',
                    match (true) {
                        is_nan($value) => 'NAN',
                        $value === INF => 'INF',
                        default => '-INF',
                    },
                );
        }

        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8')
                ? new self(
                    'string',
                    $value,
                )
                : new self(
                    'binary',
                    $value,
                );
        }

        if (is_array($value)) {
            $entries = [];

            foreach ($value as $key => $entry) {
                $entries[] = [
                    'keyType' => is_int($key) ? 'int' : 'string',
                    'key' => $key,
                    'value' => self::normalize($entry, $depth + 1, $nodes, $objects),
                ];

                if ($nodes > self::MAX_NODES) {
                    break;
                }
            }

            return new self(
                'array',
                entries: $entries,
            );
        }

        if (is_object($value)) {
            if ($objects->offsetExists($value)) {
                return new self(
                    'recursion',
                    value: $value::class,
                    reason: 'object-cycle',
                );
            }

            $objects->offsetSet($value);
            $entries = [];

            foreach (get_object_vars($value) as $key => $entry) {
                $entries[] = [
                    'keyType' => 'string',
                    'key' => $key,
                    'value' => self::normalize($entry, $depth + 1, $nodes, $objects),
                ];

                if ($nodes > self::MAX_NODES) {
                    break;
                }
            }

            $objects->offsetUnset($value);

            return new self(
                'object',
                value: self::objectLabel($value),
                entries: $entries,
                className: $value::class,
            );
        }

        if (is_resource($value)) {
            return new self(
                'resource',
                resourceType: get_resource_type($value),
            );
        }

        // Closed resources report `false` from is_resource(), so they land here rather than in the branch above.
        return new self(
            'unsupported',
            value: '(unsupported)',
            reason: 'unknown-type',
        );
    }

    /**
     * Returns a safe display label for an object.
     *
     * @param object $value Object to describe.
     *
     * @return string Display-safe object label.
     */
    private static function objectLabel(object $value): string
    {
        if ($value instanceof Closure) {
            return '\\Closure';
        }

        if ($value instanceof Throwable) {
            $class = $value::class;
            $message = $value->getMessage();

            return Json::safeString($class) . ': ' . Json::safeString($message);
        }

        if ($value instanceof Stringable) {
            try {
                return Json::safeString((string) $value);
            } catch (Throwable) {
                // Fall through to the class name when userland string conversion fails.
            }
        }

        return Json::safeString($value::class);
    }
}

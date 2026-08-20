<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use Closure;
use JsonSerializable;
use SensitiveParameter;
use SplObjectStorage;
use Stringable;
use Throwable;

use function array_diff_key;
use function array_is_list;
use function array_key_exists;
use function array_key_first;
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
    /**
     * @var array<string, true>
     */
    private const array ENTRY_SHAPE = [
        'keyType' => true,
        'key' => true,
        'value' => true,
    ];
    private const int MAX_DEPTH = 10;
    private const int MAX_NODES = 10000;

    /**
     * @var array<string, array<string, true>>
     */
    private const array SHAPES = [
        'null' => ['type' => true],
        'bool' => ['type' => true, 'value' => true],
        'int' => ['type' => true, 'value' => true],
        'float' => ['type' => true, 'value' => true],
        'special-float' => ['type' => true, 'value' => true],
        'string' => ['type' => true, 'value' => true],
        'binary' => ['type' => true, 'encoding' => true, 'data' => true],
        'array' => ['type' => true, 'entries' => true],
        'object' => ['type' => true, 'value' => true, 'entries' => true, 'class' => true],
        'resource' => ['type' => true, 'resourceType' => true],
        'truncated' => ['type' => true, 'value' => true, 'reason' => true],
        'recursion' => ['type' => true, 'value' => true, 'reason' => true],
        'unsupported' => ['type' => true, 'value' => true, 'reason' => true],
    ];

    /**
     * Creates a tagged debug value from normalized fields.
     *
     * @param string $type Tagged value type.
     * @param bool|float|int|string|null $value Captured scalar value or display label.
     * @param list<array{keyType: 'int'|'string', key: int|string, value: self}> $entries Captured child values.
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
     * @param mixed $value PHP value to capture.
     *
     * @return self Tagged debug value.
     */
    public static function capture(#[SensitiveParameter] mixed $value): self
    {
        $objects = new SplObjectStorage();

        $nodes = 0;

        return self::normalize($value, 0, $nodes, $objects);
    }

    /**
     * Hydrates a tagged debug value from decoded JSON data.
     *
     * @param mixed $data Decoded tagged value.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated debug value.
     */
    public static function fromArray(mixed $data, string $path = '$'): self
    {
        $nodes = 0;

        return self::hydrate($data, $path, 0, $nodes);
    }

    /**
     * Returns the tagged value for JSON serialization.
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
            $data['entries'] = [];

            foreach ($this->entries as $entry) {
                $data['entries'][] = [
                    'keyType' => $entry['keyType'],
                    'key' => $entry['key'],
                    'value' => $entry['value']->jsonSerialize(),
                ];
            }
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
     * Returns a required boolean without coercion.
     *
     * @param array<string, mixed> $payload Validated payload.
     */
    private static function bool(array $payload, string $key, string $path): bool
    {
        $value = self::required($payload, $key, $path);

        if (!is_bool($value)) {
            throw HydrationException::at("{$path}.{$key}", 'a boolean');
        }

        return $value;
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
     * Returns a decoded entry object with its exact required shape.
     *
     * @return array{keyType: mixed, key: mixed, value: mixed} Validated entry fields.
     */
    private static function entryObject(mixed $value, string $path): array
    {
        $entry = self::object($value, $path);

        self::validateShape($entry, self::ENTRY_SHAPE, $path);

        return [
            'keyType' => self::required($entry, 'keyType', $path),
            'key' => self::required($entry, 'key', $path),
            'value' => self::required($entry, 'value', $path),
        ];
    }

    /**
     * Hydrates a base64-encoded binary value.
     *
     * @param array<string, mixed> $payload Validated tagged value payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated binary value.
     */
    private static function fromBinary(array $payload, string $path): self
    {
        if (self::string($payload, 'encoding', $path) !== 'base64') {
            throw HydrationException::at(
                "{$path}.encoding",
                'base64',
            );
        }

        $decoded = base64_decode(self::string($payload, 'data', $path), true);

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
     * @param array<string, mixed> $payload Validated tagged value payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated non-finite floating-point value.
     */
    private static function fromSpecialFloat(array $payload, string $path): self
    {
        $value = self::string($payload, 'value', $path);

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
     * Hydrates one tagged value while enforcing the same structural budget used during capture.
     *
     * @param mixed $data Decoded tagged value.
     * @param string $path Payload path used in hydration errors.
     * @param int $depth Current nesting depth.
     * @param int $nodes Number of tagged values visited across the hydration operation.
     */
    private static function hydrate(mixed $data, string $path, int $depth, int &$nodes): self
    {
        $type = '';

        $payload = self::taggedObject($data, $path, $type);

        if (++$nodes > self::MAX_NODES + 1) {
            throw HydrationException::at($path, 'at most 10000 captured nodes');
        }

        if ($depth > self::MAX_DEPTH && $type !== 'truncated') {
            throw HydrationException::at($path, 'at most 10 nested levels');
        }

        return match ($type) {
            'null' => new self('null'),
            'bool' => new self('bool', self::bool($payload, 'value', $path)),
            'int' => new self('int', self::int($payload, 'value', $path)),
            'float' => new self('float', self::number($payload, 'value', $path)),
            'special-float' => self::fromSpecialFloat($payload, $path),
            'string' => new self('string', self::string($payload, 'value', $path)),
            'binary' => self::fromBinary($payload, $path),
            'array' => new self('array', entries: self::hydrateEntries($payload, $path, $depth, $nodes)),
            'object' => new self(
                'object',
                value: self::nullableString($payload, 'value', $path),
                entries: self::hydrateEntries($payload, $path, $depth, $nodes),
                className: self::string($payload, 'class', $path),
            ),
            'resource' => new self(
                'resource',
                resourceType: self::string($payload, 'resourceType', $path),
            ),
            'truncated', 'recursion', 'unsupported' => new self(
                $type,
                value: self::nullableString($payload, 'value', $path),
                reason: self::string($payload, 'reason', $path),
            ),
            default => throw HydrationException::at("{$path}.type", 'a known debug-value type'),
        };
    }

    /**
     * Hydrates tagged array or object entries.
     *
     * @param array<string, mixed> $payload Validated tagged value payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @return list<array{keyType: 'int'|'string', key: int|string, value: self}> Hydrated entries.
     */
    private static function hydrateEntries(array $payload, string $path, int $depth, int &$nodes): array
    {
        $entries = [];

        foreach (self::list($payload, 'entries', $path) as $index => $rawEntry) {
            $entryPath = "{$path}.entries[{$index}]";

            $entry = self::entryObject($rawEntry, $entryPath);

            $keyType = $entry['keyType'];
            $key = $entry['key'];

            if (!is_string($keyType)) {
                throw HydrationException::at("{$entryPath}.keyType", 'a string');
            }

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
                'value' => self::hydrate(
                    $entry['value'],
                    "{$entryPath}.value",
                    $depth + 1,
                    $nodes,
                ),
            ];
        }

        return $entries;
    }

    /**
     * Returns a required integer without coercion.
     *
     * @param array<string, mixed> $payload Validated payload.
     */
    private static function int(array $payload, string $key, string $path): int
    {
        $value = self::required($payload, $key, $path);

        if (!is_int($value)) {
            throw HydrationException::at("{$path}.{$key}", 'an integer');
        }

        return $value;
    }

    /**
     * Returns a required sequential list.
     *
     * @param array<string, mixed> $payload Validated payload.
     *
     * @return list<mixed> Validated list.
     */
    private static function list(array $payload, string $key, string $path): array
    {
        $value = self::required($payload, $key, $path);

        if (!is_array($value) || !array_is_list($value)) {
            throw HydrationException::at("{$path}.{$key}", 'a list');
        }

        return $value;
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
                    'key' => is_int($key) ? $key : Json::safeString($key),
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
                    'key' => Json::safeString((string) $key),
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
     * Returns a string or `null` without coercion.
     *
     * @param array<string, mixed> $payload Validated payload.
     */
    private static function nullableString(array $payload, string $key, string $path): string|null
    {
        $value = self::required($payload, $key, $path);

        if ($value !== null && !is_string($value)) {
            throw HydrationException::at("{$path}.{$key}", 'a string or null');
        }

        return $value;
    }

    /**
     * Returns a required finite number as a float.
     *
     * @param array<string, mixed> $payload Validated payload.
     */
    private static function number(array $payload, string $key, string $path): float
    {
        $value = self::required($payload, $key, $path);

        if (!is_int($value) && (!is_float($value) || !is_finite($value))) {
            throw HydrationException::at("{$path}.{$key}", 'a number');
        }

        return (float) $value;
    }

    /**
     * Returns a decoded JSON object with string keys.
     *
     * @return array<string, mixed> Validated object fields.
     */
    private static function object(mixed $value, string $path): array
    {
        if (!is_array($value) || (array_is_list($value) && $value !== [])) {
            throw HydrationException::at($path, 'an object');
        }

        foreach ($value as $key => $_) {
            if (!is_string($key)) {
                throw HydrationException::at($path, 'an object with string keys');
            }
        }

        return $value;
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

    /**
     * Returns a required field without coercion.
     *
     * @param array<string, mixed> $payload Validated payload.
     */
    private static function required(array $payload, string $key, string $path): mixed
    {
        if (!array_key_exists($key, $payload)) {
            throw HydrationException::at("{$path}.{$key}", 'a required field');
        }

        return $payload[$key];
    }

    /**
     * Returns a required string without coercion.
     *
     * @param array<string, mixed> $payload Validated payload.
     */
    private static function string(array $payload, string $key, string $path): string
    {
        $value = self::required($payload, $key, $path);

        if (!is_string($value)) {
            throw HydrationException::at("{$path}.{$key}", 'a string');
        }

        return $value;
    }

    /**
     * Returns a decoded tagged-value object matching its type-specific shape.
     *
     * @param-out string $type Validated tagged-value type.
     *
     * @return array<string, mixed> Validated tagged-value fields.
     */
    private static function taggedObject(mixed $value, string $path, string &$type): array
    {
        $payload = self::object($value, $path);
        $rawType = self::required($payload, 'type', $path);

        if (!is_string($rawType)) {
            throw HydrationException::at("{$path}.type", 'a string');
        }

        $type = $rawType;

        $shape = self::SHAPES[$type] ?? throw HydrationException::at(
            "{$path}.type",
            'a known debug-value type',
        );

        self::validateShape($payload, $shape, $path);

        return $payload;
    }

    /**
     * Validates required and undeclared fields against a cached shape map.
     *
     * @param array<array-key, mixed> $payload Decoded payload.
     * @param array<string, true> $shape Required fields indexed by name.
     */
    private static function validateShape(array $payload, array $shape, string $path): void
    {
        foreach ($shape as $key => $_) {
            if (!array_key_exists($key, $payload)) {
                throw HydrationException::at("{$path}.{$key}", 'a required field');
            }
        }

        $unknown = array_diff_key($payload, $shape);

        if ($unknown !== []) {
            $key = array_key_first($unknown);

            throw HydrationException::at("{$path}.{$key}", 'a declared field');
        }
    }
}

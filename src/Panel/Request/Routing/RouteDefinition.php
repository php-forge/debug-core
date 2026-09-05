<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

use InvalidArgumentException;

use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_values;
use function is_string;

/**
 * Immutable, persistence-safe route metadata shared by framework adapters.
 *
 * A `null` optional value means the adapter cannot expose that concept. An empty methods or hosts list means the route
 * is unrestricted, while an empty middleware list means the route has no middleware.
 */
final class RouteDefinition
{
    private string|null $action = null;

    /**
     * @var list<string>
     */
    private array $hosts = [];
    /**
     * @var list<string>
     */
    private array $methods = [];

    /**
     * @var list<string>|null
     */
    private array|null $middlewares = null;

    private string|null $mode = null;

    private string|null $suffix = null;

    private string|null $target = null;

    private string|null $type = null;

    public function __construct(private string $name = '', private string $pattern = '') {}

    public static function create(string $name = '', string $pattern = ''): self
    {
        return new self($name, $pattern);
    }

    /**
     * Restores a route definition from its strict scalar persistence shape.
     *
     * The six Yii 3 fields are required for compatibility with existing captures. Yii 2-specific fields remain
     * optional so old snapshots keep hydrating without schema migration.
     *
     * @param array<array-key, mixed> $data Persisted route definition.
     */
    public static function fromArray(array $data): self
    {
        $required = ['name', 'pattern', 'methods', 'hosts', 'action', 'middlewares'];
        $optional = ['target', 'suffix', 'mode', 'type'];

        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw self::invalid($key, self::expectedFor($key));
            }
        }

        $unknown = array_values(array_diff(array_keys($data), [...$required, ...$optional]));

        if ($unknown !== []) {
            throw self::invalid((string) $unknown[0], 'a declared field');
        }

        if (!is_string($data['name'])) {
            throw self::invalid('name', 'a string');
        }

        if (!is_string($data['pattern'])) {
            throw self::invalid('pattern', 'a string');
        }

        $methods = self::stringList($data['methods'], 'methods');
        $hosts = self::stringList($data['hosts'], 'hosts');
        $action = self::nullableString($data['action'], 'action');
        $middlewares = $data['middlewares'] === null
            ? null
            : self::stringList($data['middlewares'], 'middlewares');

        return self::create(name: $data['name'], pattern: $data['pattern'])
            ->withMethods($methods)
            ->withHosts($hosts)
            ->withTarget(self::optionalNullableString($data, 'target'))
            ->withAction($action)
            ->withMiddlewares($middlewares)
            ->withSuffix(self::optionalNullableString($data, 'suffix'))
            ->withMode(self::optionalNullableString($data, 'mode'))
            ->withType(self::optionalNullableString($data, 'type'));
    }

    public function getAction(): string|null
    {
        return $this->action;
    }

    /**
     * @return list<string>
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * @return list<string>
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * @return list<string>|null
     */
    public function getMiddlewares(): array|null
    {
        return $this->middlewares;
    }

    public function getMode(): string|null
    {
        return $this->mode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getSuffix(): string|null
    {
        return $this->suffix;
    }

    public function getTarget(): string|null
    {
        return $this->target;
    }

    public function getType(): string|null
    {
        return $this->type;
    }

    /**
     * Returns the compact route persistence shape used in Request snapshots.
     *
     * @return array{
     *     name: string,
     *     pattern: string,
     *     methods: list<string>,
     *     hosts: list<string>,
     *     action: string|null,
     *     middlewares: list<string>|null,
     *     target?: string,
     *     suffix?: string,
     *     mode?: string,
     *     type?: string
     * }
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'pattern' => $this->pattern,
            'methods' => $this->methods,
            'hosts' => $this->hosts,
            'action' => $this->action,
            'middlewares' => $this->middlewares,
        ];

        foreach (['target', 'suffix', 'mode', 'type'] as $property) {
            if ($this->{$property} !== null) {
                $data[$property] = $this->{$property};
            }
        }

        return $data;
    }

    public function withAction(string|null $action): self
    {
        $copy = clone $this;
        $copy->action = $action;

        return $copy;
    }

    /**
     * @param list<string> $hosts
     */
    public function withHosts(array $hosts): self
    {
        $copy = clone $this;
        $copy->hosts = $hosts;

        return $copy;
    }

    /**
     * @param list<string> $methods
     */
    public function withMethods(array $methods): self
    {
        $copy = clone $this;
        $copy->methods = $methods;

        return $copy;
    }

    /**
     * @param list<string>|null $middlewares
     */
    public function withMiddlewares(array|null $middlewares): self
    {
        $copy = clone $this;
        $copy->middlewares = $middlewares;

        return $copy;
    }

    public function withMode(string|null $mode): self
    {
        $copy = clone $this;
        $copy->mode = $mode;

        return $copy;
    }

    public function withSuffix(string|null $suffix): self
    {
        $copy = clone $this;
        $copy->suffix = $suffix;

        return $copy;
    }

    public function withTarget(string|null $target): self
    {
        $copy = clone $this;
        $copy->target = $target;

        return $copy;
    }

    public function withType(string|null $type): self
    {
        $copy = clone $this;
        $copy->type = $type;

        return $copy;
    }

    private static function expectedFor(string $key): string
    {
        return match ($key) {
            'name', 'pattern' => 'a string',
            'methods', 'hosts' => 'a list of strings',
            'action' => 'a string or null',
            'middlewares' => 'a list of strings or null',
            default => 'a declared field',
        };
    }

    private static function invalid(string $key, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(
            "Route definition key '{$key}' must be {$expected}.",
        );
    }

    private static function nullableString(mixed $value, string $key): string|null
    {
        if ($value !== null && !is_string($value)) {
            throw self::invalid($key, 'a string or null');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function optionalNullableString(array $data, string $key): string|null
    {
        return array_key_exists($key, $data) ? self::nullableString($data[$key], $key) : null;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $key): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw self::invalid($key, 'a list of strings');
        }

        foreach ($value as $entry) {
            if (!is_string($entry)) {
                throw self::invalid($key, 'a list of strings');
            }
        }

        return $value;
    }
}

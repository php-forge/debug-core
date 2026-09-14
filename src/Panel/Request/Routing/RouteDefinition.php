<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

use InvalidArgumentException;
use PHPForge\Debug\Exception\Message;

use function array_diff;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_values;
use function is_array;
use function is_string;

/**
 * Immutable, persistence-safe route metadata shared by framework adapters.
 *
 * A `null` optional value means the adapter cannot expose that concept. An empty methods or hosts list means the route
 * is unrestricted, while an empty middleware list means the route has no middleware.
 */
final class RouteDefinition
{
    /**
     * Handler the route dispatches to, or `null` when unsupported.
     */
    private string|null $action = null;
    /**
     * Hosts the route is restricted to; empty when it is unrestricted.
     *
     * @var list<string>
     */
    private array $hosts = [];
    /**
     * HTTP methods the route accepts; empty when it accepts any.
     *
     * @var list<string>
     */
    private array $methods = [];
    /**
     * Middleware labels applied to the route; empty when it has none, or `null` when unsupported.
     *
     * @var list<string>|null
     */
    private array|null $middlewares = null;
    /**
     * Parsing mode the rule runs in, or `null` when unsupported.
     */
    private string|null $mode = null;
    /**
     * URL suffix the rule appends, or `null` when unsupported.
     */
    private string|null $suffix = null;
    /**
     * Route the rule resolves to, or `null` when unsupported.
     */
    private string|null $target = null;
    /**
     * Rule class or category reported by the adapter, or `null` when unsupported.
     */
    private string|null $type = null;

    /**
     * @param string $name Route name as the application declares it.
     * @param string $pattern URL pattern the route matches.
     */
    public function __construct(private string $name = '', private string $pattern = '') {}

    /**
     * Creates a route definition ready for immutable enrichment.
     *
     * @param string $name Route name as the application declares it.
     * @param string $pattern URL pattern the route matches.
     *
     * @return self Definition carrying only the route identity.
     */
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
     *
     * @throws InvalidArgumentException when a required field is missing, a field is declared but unknown, or a value
     * does not match its persisted type.
     *
     * @return self Definition restored from the persisted shape.
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

    /**
     * Returns the handler the route dispatches to.
     *
     * @return string|null Handler the route dispatches to, or `null` when unsupported.
     */
    public function getAction(): string|null
    {
        return $this->action;
    }

    /**
     * Returns the hosts the route is restricted to.
     *
     * @return list<string> Hosts the route is restricted to; empty when it is unrestricted.
     */
    public function getHosts(): array
    {
        return $this->hosts;
    }

    /**
     * Returns the HTTP methods the route accepts.
     *
     * @return list<string> HTTP methods the route accepts; empty when it accepts any.
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Returns the middleware chain applied to the route.
     *
     * @return list<string>|null Middleware labels applied to the route; empty when it has none, or `null`
     * when unsupported.
     */
    public function getMiddlewares(): array|null
    {
        return $this->middlewares;
    }

    /**
     * Returns the parsing mode the rule runs in.
     *
     * @return string|null Parsing mode the rule runs in, or `null` when unsupported.
     */
    public function getMode(): string|null
    {
        return $this->mode;
    }

    /**
     * Returns the route name.
     *
     * @return string Route name as the application declares it.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the URL pattern the route matches.
     *
     * @return string URL pattern the route matches.
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the URL suffix the rule appends.
     *
     * @return string|null URL suffix the rule appends, or `null` when unsupported.
     */
    public function getSuffix(): string|null
    {
        return $this->suffix;
    }

    /**
     * Returns the route the rule resolves to.
     *
     * @return string|null Route the rule resolves to, or `null` when unsupported.
     */
    public function getTarget(): string|null
    {
        return $this->target;
    }

    /**
     * Returns the rule class or category.
     *
     * @return string|null Rule class or category reported by the adapter, or `null` when unsupported.
     */
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
     * } Route fields; the optional ones appear only when the adapter exposed them.
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

    /**
     * Returns a copy carrying another action.
     *
     * @param string|null $action Handler the route dispatches to, or `null` when unsupported.
     *
     * @return self Definition with the action applied.
     */
    public function withAction(string|null $action): self
    {
        $clone = clone $this;
        $clone->action = $action;

        return $clone;
    }

    /**
     * Returns a copy restricted to another set of hosts.
     *
     * @param list<string> $hosts Hosts the route is restricted to; empty to leave it unrestricted.
     *
     * @return self Definition with the hosts applied.
     */
    public function withHosts(array $hosts): self
    {
        $clone = clone $this;
        $clone->hosts = $hosts;

        return $clone;
    }

    /**
     * Returns a copy accepting another set of HTTP methods.
     *
     * @param list<string> $methods HTTP methods the route accepts; empty to accept any.
     *
     * @return self Definition with the methods applied.
     */
    public function withMethods(array $methods): self
    {
        $clone = clone $this;
        $clone->methods = $methods;

        return $clone;
    }

    /**
     * Returns a copy carrying another middleware chain.
     *
     * @param list<string>|null $middlewares Middleware labels applied to the route; empty when it has none,
     * or `null` when unsupported.
     *
     * @return self Definition with the middlewares applied.
     */
    public function withMiddlewares(array|null $middlewares): self
    {
        $clone = clone $this;
        $clone->middlewares = $middlewares;

        return $clone;
    }

    /**
     * Returns a copy carrying another mode.
     *
     * @param string|null $mode Parsing mode the rule runs in, or `null` when unsupported.
     *
     * @return self Definition with the mode applied.
     */
    public function withMode(string|null $mode): self
    {
        $clone = clone $this;
        $clone->mode = $mode;

        return $clone;
    }

    /**
     * Returns a copy carrying another suffix.
     *
     * @param string|null $suffix URL suffix the rule appends, or `null` when unsupported.
     *
     * @return self Definition with the suffix applied.
     */
    public function withSuffix(string|null $suffix): self
    {
        $clone = clone $this;
        $clone->suffix = $suffix;

        return $clone;
    }

    /**
     * Returns a copy carrying another target.
     *
     * @param string|null $target Route the rule resolves to, or `null` when unsupported.
     *
     * @return self Definition with the target applied.
     */
    public function withTarget(string|null $target): self
    {
        $clone = clone $this;
        $clone->target = $target;

        return $clone;
    }

    /**
     * Returns a copy carrying another type.
     *
     * @param string|null $type Rule class or category reported by the adapter, or `null` when unsupported.
     *
     * @return self Definition with the type applied.
     */
    public function withType(string|null $type): self
    {
        $clone = clone $this;
        $clone->type = $type;

        return $clone;
    }

    /**
     * Names the persisted type a field must carry, for the failure message.
     *
     * @param string $key Field the value belongs to, named in the failure message.
     *
     * @return string Human-readable description of the expected type.
     */
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

    /**
     * Builds the failure raised when a persisted field is missing, unknown, or of the wrong type.
     *
     * @param string $key Field the value belongs to, named in the failure message.
     * @param string $expected Description of the type the field must carry.
     *
     * @return InvalidArgumentException Failure naming the field and the expected type.
     */
    private static function invalid(string $key, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(
            Message::ROUTE_DEFINITION_INVALID->getMessage($key, $expected),
        );
    }

    /**
     * Narrows a persisted value to a nullable string.
     *
     * @param mixed $value Persisted value of unknown type.
     * @param string $key Field the value belongs to, named in the failure message.
     *
     * @throws InvalidArgumentException when the value is neither a `string` nor `null`.
     *
     * @return string|null Value as persisted.
     */
    private static function nullableString(mixed $value, string $key): string|null
    {
        if ($value !== null && !is_string($value)) {
            throw self::invalid($key, 'a string or null');
        }

        return $value;
    }

    /**
     * Narrows an optional persisted field to a nullable string, treating an absent field as `null`.
     *
     * @param array<array-key, mixed> $data Persisted route definition.
     * @param string $key Field the value belongs to, named in the failure message.
     *
     * @throws InvalidArgumentException when the field is present and is neither a `string` nor `null`.
     *
     * @return string|null Value as persisted, or `null` when the field is absent.
     */
    private static function optionalNullableString(array $data, string $key): string|null
    {
        return array_key_exists($key, $data) ? self::nullableString($data[$key], $key) : null;
    }

    /**
     * Narrows a persisted value to a list of strings.
     *
     * @param mixed $value Persisted value of unknown type.
     * @param string $key Field the value belongs to, named in the failure message.
     *
     * @throws InvalidArgumentException when the value is not a list, or any entry is not a `string`.
     *
     * @return list<string> Value as persisted.
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

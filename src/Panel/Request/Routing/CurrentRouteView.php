<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * Immutable current-route diagnostics for the composed Request view.
 */
final class CurrentRouteView
{
    private string|null $action = null;

    private RouteDefinition|null $definition = null;

    private string|null $error = null;

    private string|null $message = null;

    /**
     * @var array<array-key, mixed>
     */
    private array $parameters = [];

    /**
     * @var list<RouteTraceRow>
     */
    private array $trace = [];

    public function __construct(
        private string $route = '',
    ) {}

    public static function create(string $route = ''): self
    {
        return new self($route);
    }

    public function getAction(): string|null
    {
        return $this->action;
    }

    public function getDefinition(): RouteDefinition|null
    {
        return $this->definition;
    }

    public function getError(): string|null
    {
        return $this->error;
    }

    public function getMessage(): string|null
    {
        return $this->message;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return list<RouteTraceRow>
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    public function withAction(string|null $action): self
    {
        $copy = clone $this;
        $copy->action = $action;

        return $copy;
    }

    public function withDefinition(RouteDefinition|null $definition): self
    {
        $copy = clone $this;
        $copy->definition = $definition;

        return $copy;
    }

    public function withError(string|null $error): self
    {
        $copy = clone $this;
        $copy->error = $error;

        return $copy;
    }

    public function withMessage(string|null $message): self
    {
        $copy = clone $this;
        $copy->message = $message;

        return $copy;
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    public function withParameters(array $parameters): self
    {
        $copy = clone $this;
        $copy->parameters = $parameters;

        return $copy;
    }

    /**
     * @param list<RouteTraceRow> $trace
     */
    public function withTrace(array $trace): self
    {
        $copy = clone $this;
        $copy->trace = $trace;

        return $copy;
    }
}

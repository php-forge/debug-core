<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * Immutable current-route diagnostics for the composed Request view.
 */
final class CurrentRouteView
{
    /**
     * Handler the request dispatched to, or `null` when the adapter exposes none.
     */
    private string|null $action = null;

    /**
     * Route definition the request matched, or `null` when the adapter exposes none.
     */
    private RouteDefinition|null $definition = null;

    /**
     * Routing failure reported while resolving the request, or `null` when resolution succeeded.
     */
    private string|null $error = null;

    /**
     * Resolution summary shown above the routing trace, or `null` when the adapter captured none.
     */
    private string|null $message = null;

    /**
     * Parameters bound to the resolved route.
     *
     * @var array<array-key, mixed>
     */
    private array $parameters = [];

    /**
     * Routing rules inspected while resolving the request.
     *
     * @var list<RouteTraceRow>
     */
    private array $trace = [];

    /**
     * @param string $route Route the request resolved to, as the application declares it.
     */
    public function __construct(
        private string $route = '',
    ) {}

    /**
     * Creates current-route diagnostics ready for immutable enrichment.
     *
     * @param string $route Route the request resolved to, as the application declares it.
     *
     * @return self View carrying only the resolved route.
     */
    public static function create(string $route = ''): self
    {
        return new self($route);
    }

    /**
     * Returns the handler the request dispatched to.
     *
     * @return string|null Handler the request dispatched to, or `null` when the adapter exposes none.
     */
    public function getAction(): string|null
    {
        return $this->action;
    }

    /**
     * Returns the route definition the request matched.
     *
     * @return RouteDefinition|null Matched route definition, or `null` when the adapter exposes none.
     */
    public function getDefinition(): RouteDefinition|null
    {
        return $this->definition;
    }

    /**
     * Returns the routing failure reported while resolving the request.
     *
     * @return string|null Routing failure message, or `null` when resolution succeeded.
     */
    public function getError(): string|null
    {
        return $this->error;
    }

    /**
     * Returns the resolution summary shown above the routing trace.
     *
     * @return string|null Resolution summary, or `null` when the adapter captured none.
     */
    public function getMessage(): string|null
    {
        return $this->message;
    }

    /**
     * Returns the parameters bound to the resolved route.
     *
     * @return array<array-key, mixed> Parameters bound to the resolved route.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Returns the route the request resolved to.
     *
     * @return string Route the request resolved to, as the application declares it.
     */
    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * Returns the routing rules inspected while resolving the request.
     *
     * @return list<RouteTraceRow> Routing rules inspected while resolving the request.
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * Returns a copy carrying another dispatched handler.
     *
     * @param string|null $action Handler the request dispatched to, or `null` when the adapter exposes none.
     *
     * @return self View with the action applied.
     */
    public function withAction(string|null $action): self
    {
        $clone = clone $this;
        $clone->action = $action;

        return $clone;
    }

    /**
     * Returns a copy carrying another route definition.
     *
     * @param RouteDefinition|null $definition Route definition the request matched, or `null` when unsupported.
     *
     * @return self View with the definition applied.
     */
    public function withDefinition(RouteDefinition|null $definition): self
    {
        $clone = clone $this;
        $clone->definition = $definition;

        return $clone;
    }

    /**
     * Returns a copy carrying another routing failure.
     *
     * @param string|null $error Routing failure reported while resolving the request, or `null` to clear it.
     *
     * @return self View with the error applied.
     */
    public function withError(string|null $error): self
    {
        $clone = clone $this;
        $clone->error = $error;

        return $clone;
    }

    /**
     * Returns a copy carrying another resolution summary.
     *
     * @param string|null $message Resolution summary shown above the routing trace, or `null` to clear it.
     *
     * @return self View with the message applied.
     */
    public function withMessage(string|null $message): self
    {
        $clone = clone $this;
        $clone->message = $message;

        return $clone;
    }

    /**
     * Returns a copy carrying another set of route parameters.
     *
     * @param array<array-key, mixed> $parameters Parameters bound to the resolved route.
     *
     * @return self View with the parameters applied.
     */
    public function withParameters(array $parameters): self
    {
        $clone = clone $this;
        $clone->parameters = $parameters;

        return $clone;
    }

    /**
     * Returns a copy carrying another routing trace.
     *
     * @param list<RouteTraceRow> $trace Routing rules inspected while resolving the request.
     *
     * @return self View with the trace applied.
     */
    public function withTrace(array $trace): self
    {
        $clone = clone $this;
        $clone->trace = $trace;

        return $clone;
    }
}

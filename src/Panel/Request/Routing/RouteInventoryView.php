<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * Immutable route inventory and provenance displayed by the Request panel.
 */
final class RouteInventoryView
{
    /**
     * Route badges derived from the inventory.
     *
     * @var list<RouteBadge>
     */
    private array $badges = [];
    /**
     * Error message generated while building the inventory, or `null` when no error occurred.
     */
    private string|null $error = null;
    /**
     * Whether the inventory reflects live configuration (`true`) or a stored capture (`false`).
     */
    private bool $live = true;
    /**
     * Source label describing where the inventory data came from.
     */
    private string $source = 'Current application configuration';

    public function __construct(
        /**
         * @var list<RouteDefinition>
         */
        private array $routes,
    ) {}

    /**
     * @param list<RouteDefinition> $routes
     */
    public static function create(array $routes): self
    {
        return new self($routes);
    }

    /**
     * @return list<RouteBadge>
     */
    public function getBadges(): array
    {
        return $this->badges;
    }

    public function getError(): string|null
    {
        return $this->error;
    }

    /**
     * @return list<RouteDefinition>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    /**
     * @param list<RouteBadge> $badges
     */
    public function withBadges(array $badges): self
    {
        $clone = clone $this;
        $clone->badges = $badges;

        return $clone;
    }

    public function withError(string|null $error): self
    {
        $clone = clone $this;
        $clone->error = $error;

        return $clone;
    }

    public function withLive(bool $live): self
    {
        $clone = clone $this;
        $clone->live = $live;

        return $clone;
    }

    public function withSource(string $source): self
    {
        $clone = clone $this;
        $clone->source = $source;

        return $clone;
    }
}

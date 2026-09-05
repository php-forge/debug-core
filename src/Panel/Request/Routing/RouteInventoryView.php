<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request\Routing;

/**
 * Immutable route inventory and provenance displayed by the Request panel.
 */
final class RouteInventoryView
{
    /**
     * @var list<RouteBadge>
     */
    private array $badges = [];

    private string|null $error = null;

    private bool $live = true;

    private string $source = 'Current application configuration';

    /**
     * @param list<RouteDefinition> $routes
     */
    public function __construct(private array $routes) {}

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
        $copy = clone $this;
        $copy->badges = $badges;

        return $copy;
    }

    public function withError(string|null $error): self
    {
        $copy = clone $this;
        $copy->error = $error;

        return $copy;
    }

    public function withLive(bool $live): self
    {
        $copy = clone $this;
        $copy->live = $live;

        return $copy;
    }

    public function withSource(string $source): self
    {
        $copy = clone $this;
        $copy->source = $source;

        return $copy;
    }
}

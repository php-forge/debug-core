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

    /**
     * @param list<RouteDefinition> $routes Route definitions the application declares.
     */
    public function __construct(
        private array $routes,
    ) {}

    /**
     * Creates a route inventory ready for immutable enrichment.
     *
     * @param list<RouteDefinition> $routes Route definitions the application declares.
     *
     * @return self Inventory carrying only the route definitions.
     */
    public static function create(array $routes): self
    {
        return new self($routes);
    }

    /**
     * Returns the badges derived from the inventory.
     *
     * @return list<RouteBadge> Route badges derived from the inventory.
     */
    public function getBadges(): array
    {
        return $this->badges;
    }

    /**
     * Returns the error generated while building the inventory.
     *
     * @return string|null Error message, or `null` when no error occurred.
     */
    public function getError(): string|null
    {
        return $this->error;
    }

    /**
     * Returns the route definitions the inventory holds.
     *
     * @return list<RouteDefinition> Route definitions the application declares.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Returns the label describing where the inventory data came from.
     *
     * @return string Source label describing where the inventory data came from.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Determines whether the inventory reflects live configuration.
     *
     * @return bool `true` when the inventory reflects live configuration, `false` for a stored capture.
     */
    public function isLive(): bool
    {
        return $this->live;
    }

    /**
     * Returns a copy carrying another set of badges.
     *
     * @param list<RouteBadge> $badges Route badges derived from the inventory.
     *
     * @return self Inventory with the badges applied.
     */
    public function withBadges(array $badges): self
    {
        $clone = clone $this;
        $clone->badges = $badges;

        return $clone;
    }

    /**
     * Returns a copy carrying another inventory error.
     *
     * @param string|null $error Error message generated while building the inventory, or `null` to clear it.
     *
     * @return self Inventory with the error applied.
     */
    public function withError(string|null $error): self
    {
        $clone = clone $this;
        $clone->error = $error;

        return $clone;
    }

    /**
     * Returns a copy flagged as live configuration or as a stored capture.
     *
     * @param bool $live `true` when the inventory reflects live configuration, `false` for a stored capture.
     *
     * @return self Inventory with the live flag applied.
     */
    public function withLive(bool $live): self
    {
        $clone = clone $this;
        $clone->live = $live;

        return $clone;
    }

    /**
     * Returns a copy carrying another source label.
     *
     * @param string $source Source label describing where the inventory data came from.
     *
     * @return self Inventory with the source applied.
     */
    public function withSource(string $source): self
    {
        $clone = clone $this;
        $clone->source = $source;

        return $clone;
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\Vocabulary;
use PHPForge\Debug\Toolbar\ToolbarItem;

use function trim;

/**
 * Builds the shared Request toolbar metrics in route-then-status order.
 */
final class RequestToolbarItemFactory
{
    /**
     * @return list<ToolbarItem> Request toolbar metrics.
     */
    public static function create(string $route, int $statusCode, string $statusText = ''): array
    {
        $items = [];

        if ($route !== '') {
            $items[] = new ToolbarItem(
                value: $route,
                status: 'default',
                title: "Resolved route: {$route}",
                id: 'route',
            );
        }

        $statusClass = Vocabulary::statusClass($statusCode);

        $items[] = new ToolbarItem(
            value: (string) $statusCode,
            status: $statusClass === 'none' ? 'default' : "status-{$statusClass}",
            title: trim("Status code: {$statusCode} {$statusText}"),
            id: 'status',
        );

        return $items;
    }
}

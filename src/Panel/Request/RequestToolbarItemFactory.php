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
            $items[] = ToolbarItem::create($route)
                ->withStatus('default')
                ->withTitle("Resolved route: {$route}")
                ->withId('route');
        }

        $statusClass = Vocabulary::statusClass($statusCode);

        $items[] = ToolbarItem::create((string) $statusCode)
            ->withStatus($statusClass === 'none' ? 'default' : "status-{$statusClass}")
            ->withTitle(trim("Status code: {$statusCode} {$statusText}"))
            ->withId('status');

        return $items;
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Router;

use PHPForge\Debug\Panel\Router\{RouterCurrentView, RouterSnapshot};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RouterCurrentView} covering the snapshot-to-view projection and the empty fallback.
 *
 * @since 0.1
 */
#[Group('panel')]
#[Group('router')]
final class RouterCurrentViewTest extends TestCase
{
    public function testFromSnapshotProjectsTheHydratedSnapshot(): void
    {
        $snapshot = RouterSnapshot::fromArray(
            [
                'action' => 'app/controllers/SiteController::actionIndex',
                'route' => 'site/index',
                'message' => 'Route requested: site/index',
                'entries' => [
                    ['rule' => '/site', 'parent' => '', 'match' => true],
                ],
            ],
            '$.panels.router',
        );

        $view = RouterCurrentView::fromSnapshot($snapshot);

        self::assertSame('app/controllers/SiteController::actionIndex', $view->action, 'Action must project.');
        self::assertSame('site/index', $view->route, 'Route must project.');
        self::assertSame('Route requested: site/index', $view->message, 'Message must project.');
        self::assertSame(1, $view->count, 'Log count must reflect the entries.');
        self::assertTrue($view->hasMatch, 'A matching entry must flag the view.');
        self::assertCount(1, $view->logs, 'Log rows must project.');
    }

    public function testFromSnapshotReturnsEmptyViewForNull(): void
    {
        $view = RouterCurrentView::fromSnapshot(null);

        self::assertSame('', $view->action, 'Empty view must carry no action.');
        self::assertSame('', $view->route, 'Empty view must carry no route.');
        self::assertNull($view->message, 'Empty view must carry no message.');
        self::assertSame(0, $view->count, 'Empty view must count zero rules.');
        self::assertFalse($view->hasMatch, 'Empty view must report no match.');
        self::assertSame([], $view->logs, 'Empty view must carry no logs.');
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\RequestHero;
use PHPForge\Debug\Panel\Request\Routing\{
    CurrentRouteView,
    RequestRoutingView,
    RouteBadge,
    RouteDefinition,
    RouteInventoryView,
    RouteTraceRow,
};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for immutable routing view-model defaults and composition.
 */
#[Group('panel')]
#[Group('request')]
#[Group('routing')]
final class RequestRoutingViewModelsTest extends TestCase
{
    public function testConstructorsStayCompactAndModelStateIsPrivate(): void
    {
        foreach ([
            RequestHero::class => 2,
            RouteDefinition::class => 2,
            CurrentRouteView::class => 1,
            RouteInventoryView::class => 1,
        ] as $class => $count) {
            $reflection = new ReflectionClass($class);

            self::assertSame(
                $count,
                $reflection->getConstructor()?->getNumberOfParameters(),
                "{$class} must keep optional metadata outside its constructor.",
            );

            self::assertSame(
                $count,
                $reflection->getMethod('create')->getNumberOfParameters(),
                "{$class} factories must keep the same compact identity signature.",
            );

            foreach ($reflection->getProperties() as $property) {
                self::assertTrue(
                    $property->isPrivate(),
                    "{$class} must not expose directly writable model state.",
                );
            }
        }
    }
    public function testCurrentRouteDefaultsRepresentUnavailableDiagnostics(): void
    {
        $current = CurrentRouteView::create();
        $trace = new RouteTraceRow('fallback');
        $inventory = RouteInventoryView::create([]);

        self::assertEquals(
            $current,
            CurrentRouteView::create(route: '')
                ->withAction(null)
                ->withParameters([])
                ->withDefinition(null)
                ->withMessage(null)
                ->withTrace([])
                ->withError(null),
            'Current-route defaults must remain explicit and deterministic.',
        );
        self::assertFalse(
            $trace->matched,
            'A trace row must remain unmatched until an adapter reports a match.',
        );
        self::assertTrue(
            $inventory->isLive(),
            'Route inventories must describe live configuration by default.',
        );
    }

    public function testCurrentRouteFluentOptionsCanResetWithoutChangingTheOriginal(): void
    {
        $definition = RouteDefinition::create('orders', '/orders');
        $trace = new RouteTraceRow('/orders');
        $current = CurrentRouteView::create('orders')
            ->withAction('OrderAction')
            ->withParameters(['id' => 7])
            ->withDefinition($definition)
            ->withMessage('Matched.')
            ->withTrace([$trace])
            ->withError('Captured failure.');
        $reset = $current
            ->withAction(null)
            ->withParameters([])
            ->withDefinition(null)
            ->withMessage(null)
            ->withTrace([])
            ->withError(null);
        $parameters = $current->getParameters();
        $parameters['id'] = 8;
        $rows = $current->getTrace();
        $rows[] = new RouteTraceRow('fallback');

        self::assertSame(
            'orders',
            $current->getRoute(),
            'Fluent diagnostics must preserve route identity.',
        );
        self::assertSame(
            'OrderAction',
            $current->getAction(),
            'Resetting a copy must preserve the original action.',
        );
        self::assertSame(
            ['id' => 7],
            $current->getParameters(),
            'Parameters must not expose writable array state.',
        );
        self::assertSame(
            $definition,
            $current->getDefinition(),
            'Later options must retain the immutable definition.',
        );
        self::assertSame(
            'Matched.',
            $current->getMessage(),
            'Resetting a copy must preserve the original message.',
        );
        self::assertSame(
            [$trace],
            $current->getTrace(),
            'Trace rows must not expose writable array state.',
        );
        self::assertSame(
            'Captured failure.',
            $current->getError(),
            'Resetting a copy must preserve original errors.',
        );
        self::assertEquals(
            CurrentRouteView::create('orders'),
            $reset,
            'Every optional diagnostic must support clearing.',
        );
    }

    public function testCurrentRouteOptionsReturnIndependentCopies(): void
    {
        $current = CurrentRouteView::create('orders');
        $definition = RouteDefinition::create('orders', '/orders');

        $trace = new RouteTraceRow('/orders');

        foreach ([
            $current->withAction('OrderAction'),
            $current->withParameters(['id' => 7]),
            $current->withDefinition($definition),
            $current->withMessage('Matched.'),
            $current->withTrace([$trace]),
            $current->withError('Captured failure.'),
        ] as $copy) {
            self::assertNotSame(
                $current,
                $copy,
                'Every current-route option must return a separate diagnostics object.',
            );
        }

        self::assertEquals(
            CurrentRouteView::create('orders'),
            $current,
            'Optional diagnostics must not mutate the original.',
        );
    }

    public function testFactoriesPreserveConstructorSemantics(): void
    {
        foreach (
            [
                [new RequestHero('POST', '/orders'), RequestHero::create(method: 'POST', url: '/orders')],
                [new CurrentRouteView(), CurrentRouteView::create()],
                [new CurrentRouteView('orders'), CurrentRouteView::create(route: 'orders')],
                [new RouteDefinition(), RouteDefinition::create()],
                [new RouteDefinition('orders', '/orders'), RouteDefinition::create(name: 'orders', pattern: '/orders')],
                [new RouteInventoryView([]), RouteInventoryView::create(routes: [])],
            ] as [$constructed, $created]) {
            self::assertEquals(
                $constructed,
                $created,
                'Factories must preserve constructor values and defaults.',
            );
            self::assertNotSame(
                $constructed,
                $created,
                'Factories must return fresh model instances.',
            );
        }
    }

    public function testInventoryFluentOptionsPreserveRoutesAndAllowResets(): void
    {
        $definition = RouteDefinition::create('home', '/');

        $routes = [$definition];

        $badges = [new RouteBadge('Pretty URLs enabled')];

        $inventory = RouteInventoryView::create($routes)
            ->withBadges($badges)
            ->withSource('Captured configuration')
            ->withLive(false)
            ->withError('Inventory failure.');
        $reset = $inventory
            ->withBadges([])
            ->withSource('Current application configuration')
            ->withLive(true)
            ->withError(null);

        $routes[] = RouteDefinition::create('other', '/other');

        $badges[] = new RouteBadge('Strict parsing enabled');

        $returnedRoutes = $inventory->getRoutes();
        $returnedBadges = $inventory->getBadges();

        $returnedRoutes[] = RouteDefinition::create('more', '/more');

        $returnedBadges[] = new RouteBadge('Another badge');

        self::assertSame(
            [$definition],
            $inventory->getRoutes(),
            'Route lists must remain isolated from external edits.',
        );
        self::assertSame(
            [$badges[0]],
            $inventory->getBadges(),
            'Badge lists must remain isolated from external edits.',
        );
        self::assertSame(
            'Captured configuration',
            $inventory->getSource(),
            'Later options must preserve provenance.',
        );
        self::assertFalse(
            $inventory->isLive(),
            'A non-live inventory must preserve its explicit false value.',
        );
        self::assertSame(
            'Inventory failure.',
            $inventory->getError(),
            'Resetting a copy must preserve the original error.',
        );
        self::assertEquals(
            RouteInventoryView::create([$definition]),
            $reset,
            'Inventory metadata must support resetting.',
        );
    }

    public function testInventoryOptionsReturnIndependentCopies(): void
    {
        $route = RouteDefinition::create('home', '/');
        $inventory = RouteInventoryView::create([$route]);

        foreach (
            [
                $inventory->withBadges([new RouteBadge('Pretty URLs enabled')]),
                $inventory->withSource('Captured configuration'),
                $inventory->withLive(false),
                $inventory->withError('Inventory failure.'),
            ] as $copy) {
            self::assertNotSame(
                $inventory,
                $copy,
                'Every inventory option must return a separate inventory.',
            );
        }

        self::assertEquals(
            RouteInventoryView::create([$route]),
            $inventory,
            'Options must not mutate the original inventory.',
        );
    }

    public function testRoutingViewComposesDefinitionTraceBadgesAndInventory(): void
    {
        $definition = RouteDefinition::create(name: 'home', pattern: '/')
            ->withMethods(['GET'])
            ->withMiddlewares([]);

        $trace = new RouteTraceRow(rule: 'home', parent: 'group', matched: true);
        $badge = new RouteBadge(label: 'Pretty URLs enabled', variant: 'success');

        $current = CurrentRouteView::create(route: 'home')
            ->withAction('App\\HomeAction')
            ->withParameters(['id' => 7])
            ->withDefinition($definition)
            ->withMessage('Matched home.')
            ->withTrace([$trace]);
        $inventory = RouteInventoryView::create(routes: [$definition])
            ->withBadges([$badge])
            ->withLive(false);

        $view = new RequestRoutingView($current, $inventory);

        self::assertInstanceOf(
            RouteInventoryView::class,
            $view->inventory,
            'The composed view must retain its route inventory.',
        );

        self::assertSame(
            [$trace],
            $view->current->getTrace(),
            'Route trace rows must remain in resolver order.',
        );
        self::assertSame(
            [$badge],
            $view->inventory->getBadges(),
            'Route badges must reach the composed inventory.',
        );
        self::assertSame(
            'Current application configuration',
            $view->inventory->getSource(),
            'The inventory must expose a useful default provenance label.',
        );
    }
}

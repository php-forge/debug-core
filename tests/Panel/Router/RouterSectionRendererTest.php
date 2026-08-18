<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Router;

use PHPForge\Debug\Panel\Router\{
    ActionRouteRow,
    CurrentRouteLogRow,
    RouterCurrentView,
    RouterRuleRow,
    RouterSectionRenderer,
};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RouterSectionRenderer} covering the flags strip, the three tab panels, the log table, and the
 * empty states.
 *
 * @since 0.1
 */
#[Group('panel')]
#[Group('router')]
final class RouterSectionRendererTest extends TestCase
{
    public function testRenderTabsComposesFlagsTabsAndSummary(): void
    {
        $current = new RouterCurrentView(
            action: 'site/index',
            count: 2,
            hasMatch: true,
            logs: [
                new CurrentRouteLogRow(rule: '/admin', parent: '', match: false),
                new CurrentRouteLogRow(rule: '/site', parent: '', match: true),
            ],
            message: 'Route requested: site/index',
            route: 'site/index',
        );

        $html = RouterSectionRenderer::renderTabs(
            $current,
            [new RouterRuleRow('home', '/', 'GET', '', '', 'App\Web\HomePage')],
            [new ActionRouteRow('App\Web\HomePage', 'home', '/', 0)],
            [['label' => 'FastRoute Matcher', 'variant' => 'success']],
        );

        self::assertStringContainsString('yii-debug-grid-summary', $html, 'Flags strip must render.');
        self::assertStringContainsString('yii-debug-badge-success', $html, 'Badge variant must tint the chip.');
        self::assertStringContainsString('FastRoute Matcher', $html, 'Badge label must surface.');
        self::assertStringContainsString('Current Route', $html, 'Current Route tab must render.');
        self::assertStringContainsString('Router Rules', $html, 'Router Rules tab must render.');
        self::assertStringContainsString('Action Routes', $html, 'Action Routes tab must render.');
        self::assertStringContainsString('Resolved route', $html, 'Route summary must render.');
        self::assertStringContainsString('Tested 2 rules before match.', $html, 'Rule-count heading must render.');
        self::assertStringContainsString('Route requested: site/index', $html, 'Callout message must render.');
        self::assertStringContainsString('yii-debug-row-success', $html, 'Matching log row must be highlighted.');
        self::assertStringContainsString('App\Web\HomePage', $html, 'Rule rows must surface their type.');
    }

    public function testRenderTabsShowsEmptyStatesWithoutData(): void
    {
        $html = RouterSectionRenderer::renderTabs(new RouterCurrentView(), [], [], []);

        self::assertStringContainsString('No route resolution captured.', $html, 'Current Route empty state.');
        self::assertStringContainsString('No routing rules configured.', $html, 'Router Rules empty state.');
        self::assertStringContainsString('No actions configured.', $html, 'Action Routes empty state.');
        self::assertStringNotContainsString('yii-debug-grid-summary', $html, 'No badges must skip the strip.');
    }

    public function testRenderTabsUsesSingularHeadingForOneRuleWithoutMatch(): void
    {
        $current = new RouterCurrentView(
            count: 1,
            hasMatch: false,
            logs: [new CurrentRouteLogRow(rule: '/admin', parent: '', match: false)],
        );

        self::assertStringContainsString(
            'Tested 1 rule.',
            RouterSectionRenderer::renderTabs($current, [], [], []),
            'Singular heading must render without the match suffix.',
        );
    }
}

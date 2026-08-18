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
 * Unit tests for {@see RouterSectionRenderer} rendering Router panel states.
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

        self::assertSame(
            <<<HTML
            <header class="yii-debug-grid-summary">
            <span class="yii-debug-badge yii-debug-badge-success">FastRoute Matcher</span>
            </header><ul class="yii-debug-tabs" role="tablist" aria-label="Router data">
            <li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link is-active" id="router-tab-0" href="#router-panel-0" role="tab" tabindex="0" aria-controls="router-panel-0" aria-selected="true" data-yii-debug-toggle="tab">Current Route</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="router-tab-1" href="#router-panel-1" role="tab" tabindex="-1" aria-controls="router-panel-1" aria-selected="false" data-yii-debug-toggle="tab">Router Rules</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="router-tab-2" href="#router-panel-2" role="tab" tabindex="-1" aria-controls="router-panel-2" aria-selected="false" data-yii-debug-toggle="tab">Action Routes</a>
            </li>
            </ul><div class="yii-debug-tab-content">
            <div class="yii-debug-tab-panel is-active" id="router-panel-0" role="tabpanel" aria-labelledby="router-tab-0">
            <dl class="yii-debug-router-summary">
            <dt>
            Resolved route
            </dt><dd>
            <code>site/index</code>
            </dd><dt>
            Dispatched action
            </dt><dd>
            <code>site/index</code>
            </dd>
            </dl><h2>
            Tested 2 rules before match.
            </h2><div class="yii-debug-callout yii-debug-callout-info yii-debug-router-callout">
            <p class="yii-debug-router-callout-message">
            Route requested: site/index
            </p>
            </div><div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Rule
            </th><th scope="col">
            Parent
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            /admin
            </td><td>
            </td>
            </tr><tr class="yii-debug-row-success">
            <td>
            2
            </td><td>
            /site
            </td><td>
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div><div class="yii-debug-tab-panel" id="router-panel-1" role="tabpanel" aria-labelledby="router-tab-1" hidden>
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Rule
            </th><th scope="col">
            Target
            </th><th scope="col">
            Verb
            </th><th scope="col">
            Suffix
            </th><th scope="col">
            Mode
            </th><th scope="col">
            Type
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            home
            </td><td>
            /
            </td><td>
            GET
            </td><td>
            </td><td>
            </td><td>
            App\Web\HomePage
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div><div class="yii-debug-tab-panel" id="router-panel-2" role="tabpanel" aria-labelledby="router-tab-2" hidden>
            <div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Action
            </th><th scope="col">
            Route
            </th><th scope="col">
            First Matching Rule
            </th><th scope="col">
            Rules Tested
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            App\Web\HomePage
            </td><td>
            home
            </td><td>
            /
            </td><td>
            0
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Complete markup must preserve the badge, summary, logs, and tab panels.',
        );
    }

    public function testRenderTabsShowsEmptyStatesWithoutData(): void
    {
        $html = RouterSectionRenderer::renderTabs(
            new RouterCurrentView(),
            [],
            [],
            [],
        );

        self::assertSame(
            <<<HTML
            <ul class="yii-debug-tabs" role="tablist" aria-label="Router data">
            <li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link is-active" id="router-tab-0" href="#router-panel-0" role="tab" tabindex="0" aria-controls="router-panel-0" aria-selected="true" data-yii-debug-toggle="tab">Current Route</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="router-tab-1" href="#router-panel-1" role="tab" tabindex="-1" aria-controls="router-panel-1" aria-selected="false" data-yii-debug-toggle="tab">Router Rules</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="router-tab-2" href="#router-panel-2" role="tab" tabindex="-1" aria-controls="router-panel-2" aria-selected="false" data-yii-debug-toggle="tab">Action Routes</a>
            </li>
            </ul><div class="yii-debug-tab-content">
            <div class="yii-debug-tab-panel is-active" id="router-panel-0" role="tabpanel" aria-labelledby="router-tab-0">
            <h2>
            No route resolution captured.
            </h2>
            </div><div class="yii-debug-tab-panel" id="router-panel-1" role="tabpanel" aria-labelledby="router-tab-1" hidden>
            <h2>
            No routing rules configured.
            </h2>
            </div><div class="yii-debug-tab-panel" id="router-panel-2" role="tabpanel" aria-labelledby="router-tab-2" hidden>
            <h2>
            No actions configured.
            </h2>
            </div>
            </div>
            HTML,
            $html,
            'Empty data must render the three empty-state panels.',
        );
    }

    public function testRenderTabsUsesSingularHeadingForOneRuleWithoutMatch(): void
    {
        $current = new RouterCurrentView(
            count: 1,
            hasMatch: false,
            logs: [new CurrentRouteLogRow(rule: '/admin', parent: '', match: false)],
        );

        self::assertSame(
            <<<HTML
            <ul class="yii-debug-tabs" role="tablist" aria-label="Router data">
            <li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link is-active" id="router-tab-0" href="#router-panel-0" role="tab" tabindex="0" aria-controls="router-panel-0" aria-selected="true" data-yii-debug-toggle="tab">Current Route</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="router-tab-1" href="#router-panel-1" role="tab" tabindex="-1" aria-controls="router-panel-1" aria-selected="false" data-yii-debug-toggle="tab">Router Rules</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="router-tab-2" href="#router-panel-2" role="tab" tabindex="-1" aria-controls="router-panel-2" aria-selected="false" data-yii-debug-toggle="tab">Action Routes</a>
            </li>
            </ul><div class="yii-debug-tab-content">
            <div class="yii-debug-tab-panel is-active" id="router-panel-0" role="tabpanel" aria-labelledby="router-tab-0">
            <h2>
            Tested 1 rule.
            </h2><div class="yii-debug-table-wrap">
            <table class="yii-debug-table">
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Rule
            </th><th scope="col">
            Parent
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <td>
            1
            </td><td>
            /admin
            </td><td>
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div><div class="yii-debug-tab-panel" id="router-panel-1" role="tabpanel" aria-labelledby="router-tab-1" hidden>
            <h2>
            No routing rules configured.
            </h2>
            </div><div class="yii-debug-tab-panel" id="router-panel-2" role="tabpanel" aria-labelledby="router-tab-2" hidden>
            <h2>
            No actions configured.
            </h2>
            </div>
            </div>
            HTML,
            RouterSectionRenderer::renderTabs($current, [], [], []),
            'Single-rule markup must use singular wording and preserve empty companion panels.',
        );
    }
}

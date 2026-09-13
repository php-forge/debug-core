<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\{
    RequestDataNormalizer,
    RequestHero,
    RequestRenderer,
    RequestSection,
    RequestSectionRenderer,
    RequestTab,
    RequestView,
};
use PHPForge\Debug\Panel\Request\Routing\{
    CurrentRouteView,
    RequestRoutingView,
    RouteBadge,
    RouteDefinition,
    RouteInventoryView,
    RouteTraceRow,
};
use PHPForge\Debug\Tests\Provider\RequestRendererProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function strpos;
use function substr_count;

/**
 * Unit tests for the composed Request and routing presentation.
 */
#[Group('panel')]
#[Group('request')]
#[Group('routing')]
final class RequestRendererTest extends TestCase
{
    public function testRenderBuildsOneOverviewAndCanonicalTabOrder(): void
    {
        $html = RequestRenderer::render(
            self::requestView(session: true, server: true),
            self::routingView(
                parameters: ['slug' => 'welcome'],
            ),
        );

        self::assertSame(
            1,
            substr_count($html, 'class="yii-debug-request-overview '),
            'Request and routing must share one overview instead of separate summary cards.',
        );
        self::assertStringContainsString(
            'class="yii-debug-request-overview-metrics"',
            $html,
            'Route, action, and duration must live in the overview telemetry rail.',
        );
        self::assertMatchesRegularExpression(
            '~<dt>\s*Route\s*</dt>.*<dt>\s*Action\s*</dt>.*<dt>\s*Duration\s*</dt>~s',
            $html,
            'The three primary metrics must follow route, action, duration order.',
        );

        $labels = ['Input', 'Headers', 'Session', 'Server'];
        $offset = -1;

        foreach ($labels as $label) {
            $position = strpos($html, $label);

            self::assertNotFalse(
                $position,
                "The '{$label}' tab must be present.",
            );
            self::assertGreaterThan(
                $offset,
                $position,
                "The '{$label}' tab must follow the preceding canonical tab.",
            );

            $offset = $position;
        }
    }
    public function testRenderCarriesTheRoutingConstraintsOnTheContextStrip(): void
    {
        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                CurrentRouteView::create(route: 'orders/view')->withDefinition(
                    RouteDefinition::create(name: 'orders/view', pattern: '/orders/<id>')
                        ->withMethods(['GET', 'HEAD'])
                        ->withHosts(['api.example.test'])
                        ->withMiddlewares(['Auth']),
                ),
            ),
        );

        $chips = [
            'Pattern' => '/orders/&lt;id&gt;',
            'Methods' => 'GET, HEAD',
            'Hosts' => 'api.example.test',
            'Middleware' => 'Auth',
        ];

        foreach ($chips as $label => $value) {
            self::assertStringContainsString(
                "<span class=\"yii-debug-request-overview-meta-label\">{$label}</span>",
                $html,
                "The context strip must label the {$label} chip.",
            );
            self::assertStringContainsString(
                ">{$value}</span>",
                $html,
                "The {$label} chip must carry its captured value.",
            );
        }

        $wildcards = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                CurrentRouteView::create(route: 'orders/view')->withDefinition(
                    RouteDefinition::create(name: 'orders/view', pattern: '/orders')->withMiddlewares([]),
                ),
            ),
        );

        self::assertStringContainsString(
            '>Any</span>',
            $wildcards,
            'A definition without methods or hosts must read as accepting any.',
        );
        self::assertStringContainsString(
            '>None</span>',
            $wildcards,
            'An empty middleware stack must read as none.',
        );
        self::assertStringNotContainsString(
            '<span class="yii-debug-request-overview-meta-label">Middleware</span>',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(
                    CurrentRouteView::create(route: 'orders/view')->withDefinition(
                        RouteDefinition::create(name: 'orders/view', pattern: '/orders'),
                    ),
                ),
            ),
            'A framework that reports no middleware must not show an empty chip.',
        );
    }

    public function testRenderDelegatesToLegacyRendererWithoutRoutingView(): void
    {
        $view = self::requestView();

        self::assertSame(
            RequestSectionRenderer::renderHero($view->hero)
            . RequestSectionRenderer::renderTabs($view->tabs),
            RequestRenderer::render($view),
            'Omitting routing must preserve the existing Request markup byte-for-byte.',
        );
    }

    public function testRenderEscapesCurrentAndInventoryRouteValues(): void
    {
        $value = '<script>alert("route")</script>';

        $definition = RouteDefinition::create(name: $value, pattern: '/<route>')
            ->withMethods(['G<script>'])
            ->withAction('<img src=x onerror=alert(1)>')
            ->withMiddlewares([]);
        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                current: CurrentRouteView::create(route: $value)
                    ->withAction($definition->getAction())
                    ->withDefinition($definition),
                inventory: RouteInventoryView::create(routes: [$definition]),
            ),
        );

        self::assertStringNotContainsString(
            $value,
            $html,
            'Route names must never reach markup unescaped.',
        );
        self::assertStringNotContainsString(
            '<img src=x',
            $html,
            'Dispatched actions must never reach markup unescaped.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Escaped route diagnostics must remain inspectable.',
        );
    }

    public function testRenderFallsBackToAnEmptyStateWhenTheHeadersTabIsMissing(): void
    {
        $view = new RequestView(
            RequestHero::create('GET', '/'),
            [new RequestTab(label: 'Parameters', sections: [], id: 'parameters')],
        );
        $html = RequestRenderer::render(
            $view,
            new RequestRoutingView(CurrentRouteView::create(), RouteInventoryView::create(routes: [])),
        );

        self::assertStringContainsString(
            'No headers captured.',
            $html,
            'A missing headers tab must render the empty-state card.',
        );
    }

    public function testRenderFallsBackToGenericSectionsWhenSemanticStructureIsIncomplete(): void
    {
        $view = new RequestView(
            hero: self::hero(),
            tabs: [
                new RequestTab(label: 'Parameters', sections: [], id: 'parameters'),
                new RequestTab(
                    label: 'Headers',
                    sections: [
                        new RequestSection(
                            caption: 'Legacy headers',
                            entries: ['Accept' => 'text/html'],
                            id: 'request-headers',
                        ),
                    ],
                    id: 'headers',
                ),
                new RequestTab(
                    label: 'Server',
                    sections: [
                        new RequestSection(caption: 'Legacy server', entries: ['APP_ENV' => 'debug']),
                        new RequestSection(caption: 'Extra', entries: ['value' => true]),
                    ],
                    id: 'server',
                ),
            ],
        );

        $html = RequestRenderer::render(
            $view,
            self::routingView(),
        );

        self::assertStringContainsString(
            'Legacy headers',
            $html,
            'Incomplete legacy headers must remain visible.',
        );
        self::assertStringContainsString(
            'Legacy server',
            $html,
            'Malformed legacy server sections must remain visible.',
        );
        self::assertStringContainsString(
            'Extra',
            $html,
            'Unexpected sections must never be dropped.',
        );
        self::assertStringNotContainsString(
            'yii-debug-header-exchange',
            $html,
            'Incomplete semantics must not consume data through the specialized renderer.',
        );
        self::assertStringNotContainsString(
            'yii-debug-server-environment',
            $html,
            'Unexpected server sections must retain the generic compatibility path.',
        );
    }

    public function testRenderFallsBackToTheMatchedDefinitionForRouteAndAction(): void
    {
        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                CurrentRouteView::create()->withDefinition(
                    RouteDefinition::create(name: 'definition-name')->withAction('DefinitionAction'),
                ),
            ),
        );

        self::assertStringContainsString(
            '<dd title="definition-name">' . "\n" . '<span>definition-name</span>',
            $html,
            'An unresolved request must fall back to the definition name.',
        );
        self::assertStringContainsString(
            '<dd title="DefinitionAction">' . "\n" . 'DefinitionAction' . "\n" . '</dd>',
            $html,
            'An unresolved request must fall back to the definition action.',
        );
        self::assertStringContainsString(
            '<dd title="CurrentAction">' . "\n" . 'CurrentAction' . "\n" . '</dd>',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(
                    CurrentRouteView::create(route: 'orders/view')
                        ->withAction('CurrentAction')
                        ->withDefinition(
                            RouteDefinition::create(name: 'orders/view')->withAction('DefinitionAction'),
                        ),
                ),
            ),
            'A dispatched action must win over the definition action.',
        );
    }

    public function testRenderGivesEveryPopulatedInputSectionItsOwnFilter(): void
    {
        $html = RequestRenderer::render(
            RequestDataNormalizer::fromPanelData([
                'GET' => ['page' => 1],
                'POST' => ['title' => 'Article'],
                'FILES' => ['upload' => ['name' => 'report.csv']],
                'COOKIE' => ['theme' => 'dark'],
                'requestBody' => ['body' => 'Text'],
            ], null),
            self::routingView(parameters: ['id' => '42']),
        );

        $captions = ['Route parameters', 'Get', 'Post', 'Files', 'Cookies', 'Request Body'];

        foreach ($captions as $caption) {
            self::assertStringContainsString(
                "aria-label=\"Filter {$caption}\"",
                $html,
                'Every populated Input bucket must be searchable.',
            );
        }
    }

    public function testRenderKeepsGenericSectionsWhenHeaderSemanticsRepeatOrAreUnknown(): void
    {
        $view = new RequestView(
            hero: self::hero(),
            tabs: [
                new RequestTab(
                    label: 'Headers',
                    sections: [
                        new RequestSection('First inbound', ['Accept' => 'text/html'], id: 'request-headers'),
                        new RequestSection('Second inbound', ['Host' => 'example.test'], id: 'request-headers'),
                        new RequestSection('Outbound', ['Vary' => 'Accept'], id: 'response-headers'),
                    ],
                    id: 'headers',
                ),
            ],
        );

        $html = RequestRenderer::render(
            $view,
            self::routingView(),
        );

        self::assertStringNotContainsString(
            'yii-debug-header-exchange',
            $html,
            'A repeated inbound bucket must not consume data through the exchange renderer.',
        );
        self::assertStringContainsString(
            'Second inbound',
            $html,
            'A repeated inbound bucket must stay visible through the generic path.',
        );

        $unknown = new RequestView(
            hero: self::hero(),
            tabs: [
                new RequestTab(
                    label: 'Headers',
                    sections: [
                        new RequestSection('Unknown', ['Accept' => 'text/html'], id: 'trailers'),
                        new RequestSection('Inbound', ['Host' => 'example.test'], id: 'request-headers'),
                        new RequestSection('Outbound', ['Vary' => 'Accept'], id: 'response-headers'),
                    ],
                    id: 'headers',
                ),
            ],
        );

        self::assertStringNotContainsString(
            'yii-debug-header-exchange',
            RequestRenderer::render($unknown, self::routingView()),
            'An unknown bucket must abandon the exchange renderer for the whole tab.',
        );
    }


    public function testRenderLiftsTheRoutingConfigurationIntoTheOverview(): void
    {
        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                CurrentRouteView::create(),
                RouteInventoryView::create(routes: [])->withBadges(
                    [
                        new RouteBadge('Pretty URL Enabled', 'success'),
                        new RouteBadge('Strict Parsing Disabled', 'muted'),
                        new RouteBadge('Unknown', 'custom'),
                    ],
                ),
            ),
        );

        self::assertMatchesRegularExpression(
            '~yii-debug-request-overview-meta".*yii-debug-badge yii-debug-badge-success">Pretty URL Enabled~s',
            $html,
            'The routing configuration must read on the overview context strip.',
        );
        self::assertStringContainsString(
            'yii-debug-badge yii-debug-badge-muted">Strict Parsing Disabled',
            $html,
            'Every configuration badge must keep its own tone.',
        );
        self::assertStringContainsString(
            'yii-debug-badge yii-debug-badge-muted">Unknown',
            $html,
            'An unknown variant must degrade to the muted vocabulary.',
        );
        self::assertStringNotContainsString(
            'Routes (',
            $html,
            'The route inventory must not reopen a tab that repeats the overview.',
        );
        self::assertStringNotContainsString(
            'No application routes registered.',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(CurrentRouteView::create(), RouteInventoryView::create(routes: [])),
            ),
            'An empty inventory must not render an empty ledger.',
        );
    }



    public function testRenderMovesRouteParametersIntoInputAndRemovesLegacyRoutingSection(): void
    {
        $html = RequestRenderer::render(
            self::requestView(),
            self::routingView(parameters: ['id' => 42]),
        );

        self::assertStringContainsString(
            'Route parameters',
            $html,
            'Dispatched route parameters must remain inspectable in Input.',
        );
        self::assertMatchesRegularExpression(
            '~Route parameters.*id.*42.*Get~s',
            $html,
            'Route parameters must precede ordinary request input sections.',
        );
        self::assertDoesNotMatchRegularExpression(
            '~<h2[^>]*>\s*Routing\s*</h2>~',
            $html,
            'The legacy duplicate Routing table must be removed by semantic section ID.',
        );
    }

    public function testRenderOpensPopulatedInputAndCollapsesEmptyBuckets(): void
    {
        $view = new RequestView(
            hero: self::hero(),
            tabs: [
                new RequestTab(
                    label: 'Parameters',
                    sections: [
                        new RequestSection(caption: 'Routing', entries: ['Route' => 'home'], id: 'routing'),
                        new RequestSection(caption: 'Get', entries: ['page' => 1], id: 'get'),
                        new RequestSection(caption: 'Post', entries: [], id: 'post'),
                    ],
                    id: 'parameters',
                ),
                new RequestTab(
                    label: 'Headers',
                    sections: [new RequestSection(caption: 'Request Headers', entries: ['Accept' => 'text/html'])],
                    id: 'headers',
                ),
            ],
        );

        $html = RequestRenderer::render(
            $view,
            self::routingView(),
        );

        self::assertSame(
            2,
            substr_count($html, '<details class="yii-debug-disclosure"'),
            'Populated and empty Input buckets must both use the shared disclosure.',
        );
        self::assertMatchesRegularExpression(
            '~yii-debug-disclosure-title">Get</span>.*yii-debug-disclosure-title">Post</span>~s',
            $html,
            'Input disclosures must preserve bucket registration order.',
        );
        self::assertSame(
            2,
            substr_count($html, 'data-yii-debug-hint="collapsed">click to expand'),
            'Every Input bucket must expose the shared disclosure affordance.',
        );
        self::assertMatchesRegularExpression(
            '~<details class="yii-debug-disclosure" open>.*yii-debug-disclosure-title">Get</span>~s',
            $html,
            'A populated Input bucket must render open by default.',
        );
        self::assertMatchesRegularExpression(
            '~<details class="yii-debug-disclosure">.*yii-debug-disclosure-title">Post</span>~s',
            $html,
            'An empty Input bucket must remain collapsed by default.',
        );
        self::assertDoesNotMatchRegularExpression(
            '~<h2[^>]*>\s*(?:Get|Post)\s*</h2>~',
            $html,
            'Input bucket labels must not be duplicated inside disclosure bodies.',
        );
        self::assertMatchesRegularExpression(
            '~<h2 class="yii-debug-request-section-title">\s*Request Headers\s*</h2>~',
            $html,
            'Populated Headers must retain the expanded section treatment.',
        );
    }

    public function testRenderReplacesMissingIdentityWithExplicitLabels(): void
    {
        $html = RequestRenderer::render(
            new RequestView(hero: RequestHero::create(method: '', url: ''), tabs: []),
            new RequestRoutingView(CurrentRouteView::create()),
        );

        self::assertStringContainsString(
            'URL unavailable',
            $html,
            'A capture without URL must say so instead of rendering a blank line.',
        );
        self::assertStringContainsString(
            '<dd title="Unresolved">' . "\n" . 'Unresolved' . "\n" . '</dd>',
            $html,
            'A capture without route must say so.',
        );
        self::assertSame(
            2,
            substr_count($html, '<dd title="Unavailable">'),
            'A capture without action or duration must say so for both.',
        );
        self::assertStringNotContainsString(
            'yii-debug-request-hero-method',
            $html,
            'A capture without method must not render an empty pill.',
        );
        self::assertStringNotContainsString(
            'yii-debug-request-overview-status',
            $html,
            'A capture without status must not render an empty badge.',
        );
        self::assertStringNotContainsString(
            'yii-debug-request-overview-meta-label',
            $html,
            'A capture without context must not render empty chips.',
        );
        self::assertStringNotContainsString(
            'yii-debug-route-match',
            $html,
            'A request that matched no route must not be marked as matched.',
        );
        self::assertStringContainsString(
            'class="yii-debug-request-overview yii-debug-verb-other"',
            $html,
            'A capture without method must fall back to the neutral verb vocabulary.',
        );
    }


    public function testRenderSelectsSpecializedHeaderAndServerLedgersBySemanticIds(): void
    {
        $view = new RequestView(
            hero: self::hero(),
            tabs: [
                new RequestTab(
                    label: 'Parameters',
                    sections: [new RequestSection(caption: 'Routing', entries: [], id: 'routing')],
                    id: 'parameters',
                ),
                new RequestTab(
                    label: 'Headers',
                    sections: [
                        new RequestSection(
                            caption: 'Request Headers',
                            entries: ['Accept' => 'text/html'],
                            id: 'request-headers',
                        ),
                        new RequestSection(
                            caption: 'Response Headers',
                            entries: ['Content-Type' => 'text/html'],
                            id: 'response-headers',
                        ),
                    ],
                    id: 'headers',
                ),
                new RequestTab(
                    label: 'Server',
                    sections: [
                        new RequestSection(
                            caption: 'Server',
                            entries: ['SERVER_PROTOCOL' => 'HTTP/1.1'],
                            id: 'server',
                        ),
                    ],
                    id: 'server',
                ),
            ],
        );

        $html = RequestRenderer::render(
            $view,
            self::routingView(),
        );

        self::assertStringContainsString(
            'yii-debug-header-exchange',
            $html,
            'Canonical header section IDs must activate the directional exchange.',
        );
        self::assertStringContainsString(
            'yii-debug-server-environment',
            $html,
            'The canonical server section ID must activate the grouped environment.',
        );
    }

    /**
     * @param array{SESSION: array<string, mixed>, flashes: array<string, mixed>} $data Captured session buckets.
     */
    #[DataProviderExternal(RequestRendererProvider::class, 'sessionCaptures')]
    public function testRenderSessionDisclosuresFollowTheirOwnDataAndFilterScope(array $data): void
    {
        $captions = ['SESSION' => 'Session', 'flashes' => 'Flashes'];

        $html = RequestRenderer::render(
            RequestDataNormalizer::fromPanelData($data, null),
            new RequestRoutingView(current: CurrentRouteView::create()),
        );

        preg_match_all('~<details class="yii-debug-disclosure"[^>]*>.*?</details>~s', $html, $matches);

        self::assertCount(
            2,
            $matches[0],
            'Session and Flashes must each have a disclosure.',
        );

        foreach ($captions as $key => $caption) {
            $index = $key === 'SESSION' ? 0 : 1;

            $section = $matches[0][$index] ?? null;

            self::assertNotNull(
                $section,
                'The section disclosure must exist.',
            );
            self::assertStringContainsString(
                'yii-debug-disclosure-title">' . $caption . '</span>',
                $section,
                'The section heading must identify its data.',
            );
            self::assertStringContainsString(
                'click to expand',
                $section,
                'The shared affordance must remain visible.',
            );

            if ($data[$key] === []) {
                self::assertStringStartsWith(
                    '<details class="yii-debug-disclosure">',
                    $section,
                    'Empty sections must start closed.',
                );
                self::assertStringContainsString(
                    'No data',
                    $section,
                    'Empty sections must explain their state.',
                );
                self::assertStringNotContainsString(
                    '<input',
                    $section,
                    'Empty sections must not show an unusable filter.',
                );

                continue;
            }

            self::assertStringStartsWith(
                '<details class="yii-debug-disclosure" open>',
                $section,
                'Populated sections must start open.',
            );
            self::assertStringContainsString(
                "aria-label=\"Filter {$caption}\"",
                $section,
                'Filters must identify their own section.',
            );
            self::assertSame(
                1,
                substr_count($section, 'data-yii-debug-filter="true"'),
                'Each section must have exactly one filter.',
            );
            self::assertSame(
                1,
                substr_count($section, 'data-yii-debug-filter-target="true"'),
                'Each disclosure must scope its own filter target.',
            );
        }
    }

    public function testRenderShowsTheRoutingResolutionUnderTheOverview(): void
    {
        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                CurrentRouteView::create(route: 'home')
                    ->withMessage('No matching URL rule; default parsing was used.')
                    ->withTrace(
                        [
                            new RouteTraceRow('fallback', matched: true),
                            new RouteTraceRow('site/<action>', parent: 'group'),
                        ],
                    ),
            ),
        );

        self::assertMatchesRegularExpression(
            '~yii-debug-request-overview-meta".*yii-debug-route-resolution~s',
            $html,
            'The resolution must read under the overview, not inside a tab.',
        );
        self::assertStringContainsString(
            'Routing resolution (2 rules tested)',
            $html,
            'The disclosure title must report the trace size.',
        );
        self::assertStringContainsString(
            'yii-debug-route-resolution-message',
            $html,
            'The resolver message must stay attached to the trace.',
        );
        self::assertStringContainsString(
            <<<HTML
            <thead>
            <tr>
            <th scope="col">
            #
            </th><th scope="col">
            Rule
            </th><th scope="col">
            Parent
            </th><th scope="col">
            Result
            </th>
            </tr>
            </thead>
            HTML,
            $html,
            'Trace columns must stay complete and ordered.',
        );
        self::assertStringContainsString(
            <<<HTML
            <tbody>
            <tr class="yii-debug-row-success">
            <td>
            1
            </td><td>
            fallback
            </td><td>
            —
            </td><td>
            <span class="yii-debug-badge yii-debug-badge-success">Matched</span>
            </td>
            </tr><tr>
            <td>
            2
            </td><td>
            site/&lt;action&gt;
            </td><td>
            group
            </td><td>
            <span class="yii-debug-badge yii-debug-badge-warning">Not matched</span>
            </td>
            </tr>
            </tbody>
            HTML,
            $html,
            'Rules must number from 1, fall back to a placeholder parent, and badge the result.',
        );
        self::assertStringContainsString(
            'Routing resolution</span>',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(CurrentRouteView::create(route: 'home')->withMessage('Default parsing.')),
            ),
            'A traceless resolution must drop the rule count.',
        );
        self::assertStringNotContainsString(
            'yii-debug-route-resolution-message',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(
                    CurrentRouteView::create(route: 'home')->withTrace([new RouteTraceRow('fallback', matched: true)]),
                ),
            ),
            'A messageless trace must not emit an empty paragraph.',
        );
        self::assertStringNotContainsString(
            'yii-debug-route-resolution',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(CurrentRouteView::create(route: 'home')->withMessage('')),
            ),
            'A capture without message or trace must not open a resolution.',
        );
    }

    public function testRenderStampsTheCapturedStatusAndVerbOnTheOverview(): void
    {
        $html = RequestRenderer::render(self::requestView(), self::routingView());

        self::assertStringStartsWith(
            '<section class="yii-debug-request-overview yii-debug-verb-get" aria-label="Request overview">',
            $html,
            'The overview must lead the composed view, never trail its tabs.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span>',
            $html,
            'The request line must open with the verb pill.',
        );
        self::assertStringContainsString(
            'class="yii-debug-request-overview-status-value yii-debug-snapshot-status yii-debug-status-2xx">200</span>',
            $html,
            'The status badge must carry the captured class.',
        );
        self::assertMatchesRegularExpression(
            '~<dt>\s*Route\s*</dt><dd title="home">\s*<span>home</span>'
            . '<span class="yii-debug-badge yii-debug-badge-success yii-debug-route-match">Matched</span>~',
            $html,
            'A request that reached a known route must be marked as matched.',
        );

        $chips = ['IP', 'Time'];

        foreach ($chips as $label) {
            self::assertStringContainsString(
                "<span class=\"yii-debug-request-overview-meta-label\">{$label}</span>",
                $html,
                "The context strip must label the {$label} chip.",
            );
        }
    }

    public function testRenderSurfacesCapturedAndLiveRoutingFailuresIndependently(): void
    {
        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                current: CurrentRouteView::create(route: 'home')
                    ->withError('Captured route metadata could not be read.'),
                inventory: RouteInventoryView::create(routes: [])
                    ->withError('Current route configuration could not be read.'),
            ),
        );

        self::assertSame(
            2,
            substr_count($html, 'yii-debug-request-routing-error'),
            'Each routing failure must open its own callout.',
        );
        self::assertMatchesRegularExpression(
            '~Captured route metadata could not be read\..*Current route configuration could not be read\.~s',
            $html,
            'The captured failure must lead the live one.',
        );
        self::assertStringNotContainsString(
            'yii-debug-request-routing-error',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(
                    CurrentRouteView::create(route: 'home')->withError(''),
                    RouteInventoryView::create(routes: []),
                ),
            ),
            'A blank failure must not open an empty callout.',
        );
    }

    public function testRenderUsesConciseInputEmptyStateAfterRemovingRouting(): void
    {
        $view = new RequestView(
            hero: self::hero(),
            tabs: [
                new RequestTab(
                    label: 'Parameters',
                    sections: [
                        new RequestSection(
                            caption: 'Routing',
                            entries: ['Route' => 'home'],
                            id: 'routing',
                        ),
                    ],
                    id: 'parameters',
                ),
                new RequestTab(label: 'Headers', sections: [], id: 'headers'),
            ],
        );

        self::assertStringContainsString(
            'No input data captured.',
            RequestRenderer::render($view, self::routingView()),
            'A routing-only legacy Parameters tab must become a useful empty Input state.',
        );
    }


    private static function hero(): RequestHero
    {
        return RequestHero::create(method: 'GET', url: 'https://example.test/')
            ->withStatus(200, '2xx')
            ->withIp('127.0.0.1')
            ->withTiming('12:34:56', '9.7 ms')
            ->withFlags(['AJAX']);
    }

    private static function requestView(bool $session = false, bool $server = false): RequestView
    {
        $tabs = [
            new RequestTab(
                label: 'Parameters',
                sections: [
                    new RequestSection(
                        caption: 'Routing',
                        entries: ['Route' => 'home', 'Action' => 'App\\HomeAction'],
                        id: 'routing',
                    ),
                    new RequestSection(caption: 'Get', entries: ['page' => 1], id: 'get'),
                ],
                id: 'parameters',
            ),
            new RequestTab(
                label: 'Headers',
                sections: [
                    new RequestSection(caption: 'Request Headers', entries: ['Accept' => 'text/html']),
                ],
                id: 'headers',
            ),
        ];

        if ($session) {
            $tabs[] = new RequestTab(
                label: 'Session',
                sections: [
                    new RequestSection(caption: 'Session', entries: ['user' => 1]),
                ],
                id: 'session',
            );
        }

        if ($server) {
            $tabs[] = new RequestTab(
                label: 'Server',
                sections: [
                    new RequestSection(caption: 'Server', entries: ['HTTP_HOST' => 'example.test']),
                ],
                id: 'server',
            );
        }

        return new RequestView(hero: self::hero(), tabs: $tabs);
    }


    /**
     * @param array<array-key, mixed> $parameters
     */
    private static function routingView(array $parameters = []): RequestRoutingView
    {
        $home = RouteDefinition::create(name: 'home', pattern: '/')
            ->withMethods(['GET'])
            ->withHosts([])
            ->withAction('App\\HomeAction')
            ->withMiddlewares([]);
        $article = RouteDefinition::create(name: 'article/view', pattern: '/articles/{id}')
            ->withMethods(['GET', 'HEAD'])
            ->withHosts([])
            ->withAction('App\\ArticleAction')
            ->withMiddlewares(['App\\Authentication']);

        return new RequestRoutingView(
            current: CurrentRouteView::create(route: 'home')
                ->withAction('App\\HomeAction')
                ->withParameters($parameters)
                ->withDefinition($home),
            inventory: RouteInventoryView::create(routes: [$home, $article]),
        );
    }
}

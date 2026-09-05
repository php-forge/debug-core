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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function strpos;

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

        $labels = ['Input', 'Headers', 'Session', 'Routes (2)', 'Server'];

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

        $html = RequestRenderer::render($view, self::routingView());

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

        foreach (['Route parameters', 'Get', 'Post', 'Files', 'Cookies', 'Request Body'] as $caption) {
            self::assertStringContainsString(
                "aria-label=\"Filter {$caption}\"",
                $html,
                'Every populated Input bucket must be searchable.',
            );
        }
    }

    public function testRenderKeepsYiiTwoMetadataInCommonRouteDetails(): void
    {
        $definition = RouteDefinition::create(pattern: 'post/<id:\\d+>')
            ->withMethods(['GET'])
            ->withTarget('post/view')
            ->withAction(null)
            ->withMiddlewares(null)
            ->withSuffix('.html')
            ->withMode('BOTH')
            ->withType('yii\\web\\UrlRule');

        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                current: CurrentRouteView::create(route: 'post/view')
                    ->withAction('PostController::actionView()'),
                inventory: RouteInventoryView::create(routes: [$definition]),
            ),
        );

        $table = self::routeLedger($html);

        foreach (['Methods', 'Pattern', 'Route', 'Details'] as $heading) {
            self::assertMatchesRegularExpression(
                "~<span>{$heading}</span>~",
                $table,
                "Yii 2 route inventories must include the '{$heading}' column.",
            );
        }

        foreach (['Name', 'Target', 'Hosts', 'Action', 'Middleware', 'Suffix', 'Mode', 'Type'] as $heading) {
            self::assertDoesNotMatchRegularExpression(
                "~<span>{$heading}</span>~",
                $table,
                "Unsupported '{$heading}' metadata must not create an empty Yii 2 column.",
            );
        }
    }

    public function testRenderMarksCapturedDynamicRuleAndOmitsUnavailableOverviewMetadata(): void
    {
        $definition = RouteDefinition::create(pattern: 'post/<action>')
            ->withTarget('post/<action>');
        $html = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(
                current: CurrentRouteView::create(route: 'post/view')
                    ->withDefinition($definition),
                inventory: RouteInventoryView::create(routes: [$definition]),
            ),
        );

        self::assertStringContainsString(
            'data-yii-debug-route-match="true"',
            $html,
            'The selected dynamic rule must be marked.',
        );
        self::assertStringNotContainsString(
            'yii-debug-request-overview-meta-label">Middleware',
            $html,
            'Unsupported metadata must not add placeholder noise.',
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

    public function testRenderOmitsUnsupportedAndShowsEmptyRouteInventories(): void
    {
        $withoutInventory = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(CurrentRouteView::create()),
        );
        $emptyInventory = RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(CurrentRouteView::create(), RouteInventoryView::create(routes: [])
                ->withLive(false)),
        );

        self::assertStringNotContainsString(
            'Routes (',
            $withoutInventory,
            'Unsupported route enumeration must not add an empty Routes tab.',
        );
        self::assertStringContainsString(
            'No application routes registered.',
            $emptyInventory,
            'A supported empty route collection must be distinguished from unavailable enumeration.',
        );
        self::assertStringContainsString(
            'Routes (0)',
            $emptyInventory,
            'The Routes tab must retain an explicit zero count.',
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
        $html = RequestRenderer::render($view, self::routingView());

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
            4,
            substr_count($html, 'data-yii-debug-hint="collapsed">click to expand'),
            'Input buckets and route details must expose the shared disclosure affordance.',
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

    public function testRenderPreservesSpecificMetadataInsideSearchableDetails(): void
    {
        $definition = RouteDefinition::create(name: 'post', pattern: '/post')
            ->withTarget('post/view')
            ->withHosts(['one.example.test', 'two.example.test'])
            ->withAction('Post::<view>')
            ->withMiddlewares(['Auth', 'Session'])
            ->withSuffix('.html')
            ->withMode('BOTH')
            ->withType('GROUP');

        $html = self::routeLedger(RequestRenderer::render(
            self::requestView(),
            new RequestRoutingView(current: CurrentRouteView::create(), inventory: RouteInventoryView::create(routes: [$definition])),
        ));

        foreach (['post/view', 'one.example.test', 'two.example.test', 'Post::&lt;view&gt;', 'Auth', 'Session', '.html', 'BOTH', 'GROUP'] as $value) {
            self::assertStringContainsString(
                $value,
                $html,
                'Route metadata must remain inspectable and escaped.',
            );
        }

        self::assertStringContainsString(
            'data-yii-debug-filter-details="true"',
            $html,
            'Filtering must reveal metadata.',
        );
        self::assertStringContainsString(
            'data-yii-debug-filter-unit="routes"',
            RequestRenderer::render(
                self::requestView(),
                new RequestRoutingView(current: CurrentRouteView::create(), inventory: RouteInventoryView::create(routes: [$definition])),
            ),
            'The filter must count routes rather than metadata fields.',
        );
        self::assertStringContainsString(
            'No routes match this filter.',
            $html,
            'An unmatched filter must explain the empty result.',
        );
        self::assertDoesNotMatchRegularExpression(
            '~<details[^>]* open~',
            $html,
            'Metadata must start collapsed.',
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

        $html = RequestRenderer::render($view, self::routingView());

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

    public function testRenderSessionDisclosuresFollowTheirOwnDataAndFilterScope(): void
    {
        foreach ([
            ['SESSION' => ['user' => 1], 'flashes' => []],
            ['SESSION' => [], 'flashes' => ['notice' => 'Saved']],
            ['SESSION' => ['user' => 1], 'flashes' => ['notice' => 'Saved']],
            ['SESSION' => [], 'flashes' => []],
        ] as $data) {
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

            foreach (['SESSION' => 'Session', 'flashes' => 'Flashes'] as $key => $caption) {
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
    }

    public function testRenderSurfacesIndependentErrorsAndCollapsedResolutionTrace(): void
    {
        $routing = new RequestRoutingView(
            current: CurrentRouteView::create(route: 'home')
                ->withMessage('No matching URL rule; default parsing was used.')
                ->withTrace([new RouteTraceRow('fallback', matched: true)])
                ->withError('Captured route metadata could not be read.'),
            inventory: RouteInventoryView::create(
                routes: [
                    RouteDefinition::create(name: 'home', pattern: '/')->withMiddlewares([]),
                ]
            )
            ->withBadges(
                [
                    new RouteBadge('Pretty URLs enabled', 'success'),
                    new RouteBadge('Unknown', 'custom'),
                ],
            )
            ->withSource('Current application configuration.')
            ->withLive(true)
            ->withError('Current route configuration could not be read.'),
        );

        $html = RequestRenderer::render(self::requestView(), $routing);

        self::assertStringContainsString(
            'yii-debug-request-routing-error',
            $html,
            'Captured routing errors must remain attached to the request overview.',
        );
        self::assertStringContainsString(
            'yii-debug-route-inventory-error',
            $html,
            'Live inventory errors must not suppress the current request diagnostics.',
        );
        self::assertStringContainsString(
            'Source: Current application configuration. Live configuration may differ from this capture.',
            $html,
            'Live inventory provenance must warn when it may differ from a historical capture.',
        );
        self::assertStringContainsString(
            '<details class="yii-debug-disclosure">',
            $html,
            'Resolver messages and traces must stay available in a collapsed disclosure.',
        );
        self::assertStringContainsString(
            'Routing resolution (1 rules tested)',
            $html,
            'Resolution disclosure must report the trace size.',
        );
        self::assertStringContainsString(
            'yii-debug-badge yii-debug-badge-muted">Unknown',
            $html,
            'Unknown badge variants must degrade to the safe muted vocabulary.',
        );
    }

    public function testRenderUsesCommonColumnsVocabularyMethodsAndAccessibleMatch(): void
    {
        $html = RequestRenderer::render(self::requestView(), self::routingView());

        $table = self::routeLedger($html);

        foreach (['Methods', 'Pattern', 'Route', 'Details'] as $heading) {
            self::assertMatchesRegularExpression(
                "~<span>{$heading}</span>~",
                $table,
                "Yii 3 route inventories must include the '{$heading}' column.",
            );
        }

        foreach (['Name', 'Hosts', 'Action', 'Middleware', 'Target', 'Suffix', 'Mode', 'Type'] as $heading) {
            self::assertDoesNotMatchRegularExpression(
                "~<span>{$heading}</span>~",
                $table,
                "Unsupported '{$heading}' metadata must not create an empty column.",
            );
        }

        self::assertStringContainsString(
            'yii-debug-route-method yii-debug-verb-get',
            $html,
            'HTTP methods must use the shared semantic verb vocabulary.',
        );
        self::assertStringContainsString(
            'data-yii-debug-route-match="true"',
            $html,
            'The resolved route row must expose a non-color match marker.',
        );
        self::assertMatchesRegularExpression(
            '~yii-debug-route-match[^>]*>Matched</span>~',
            $html,
            'The matched state must be expressed as visible text.',
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

    public function testRenderUsesIdenticalInventoryMarkupForEquivalentAdapterRoutes(): void
    {
        $tables = [];

        foreach (
            [
                RouteDefinition::create(name: 'post/view', pattern: '/posts')
                    ->withMethods(['GET']),
                RouteDefinition::create(pattern: '/posts')
                    ->withTarget('post/view')
                    ->withMethods(['GET']),
            ] as $definition) {
            $tables[] = self::routeLedger(
                RequestRenderer::render(
                    self::requestView(),
                    new RequestRoutingView(
                        current: CurrentRouteView::create(route: 'post/view'),
                        inventory: RouteInventoryView::create(routes: [$definition]),
                    ),
                )
            );
        }

        self::assertSame(
            $tables[0],
            $tables[1],
            'Equivalent adapter data must produce identical inventory markup.',
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

    private static function routeLedger(string $html): string
    {
        $start = strpos($html, '<div class="yii-debug-route-ledger"');

        self::assertNotFalse(
            $start,
            'The route inventory ledger must be present.',
        );

        return substr($html, $start);
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

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Router;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\Router\{ActionRouteRow, RouterPanel, RouterRuleRow, RouterSnapshot};
use PHPForge\Debug\Tests\Support\PanelViewAccessors;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * Unit tests for {@see RouterPanel} covering the route overview, rule trace, URL rules, and action routes.
 */
#[Group('panel')]
#[Group('router')]
final class RouterPanelTest extends TestCase
{
    use PanelViewAccessors;

    public function testCapturedTraceDescribesTheRouteAndEveryInspectedRule(): void
    {
        $view = self::present(
            [
                'action' => 'app\\controllers\\SiteController::actionIndex',
                'route' => 'site/index',
                'message' => 'Request parsed with URL rule: <controller>/<action>',
                'entries' => [
                    ['rule' => 'yii\\rest\\UrlRule', 'parent' => '', 'match' => false],
                    ['rule' => '<controller>/<action>', 'parent' => 'yii\\rest\\UrlRule', 'match' => true],
                ],
            ],
        );

        self::assertSame(
            ['site/index', '2'],
            self::metricValues($view->summaryMetrics()),
            'The summary must report the route and the number of inspected rules.',
        );
        self::assertSame(
            ' rules tested',
            ($view->summaryMetrics()[1] ?? self::fail('The trace size must stay in the summary.'))['label'],
            'More than one inspected rule must use the plural label.',
        );
        self::assertSame(
            [['label' => 'Route', 'value' => ['kind' => 'text', 'value' => 'site/index', 'style' => 'plain']]],
            $view->toolbarMetrics(),
            'The toolbar must report the resolved route.',
        );

        $overview = self::overview(self::blockAt($view, 0));

        self::assertTrue(
            $overview['compact'],
            'The route overview must use the compact presentation.',
        );
        self::assertSame(
            ['Route', 'Action', 'Pretty URL', 'Strict parsing', 'Global suffix'],
            array_keys(self::fields($overview)),
            'The overview row order must stay stable.',
        );
        self::assertSame(
            'app\\controllers\\SiteController::actionIndex',
            self::textValue(
                self::fields($overview)['Action'] ?? self::fail('The overview must keep the action row.'),
            ),
            'The dispatched action must survive the migration.',
        );
        self::assertSame(
            ['kind' => 'text', 'value' => 'site/index', 'style' => 'code'],
            self::fields($overview)['Route'] ?? self::fail('The overview must keep the route row.'),
            'A resolved route must read as source code.',
        );
        self::assertSame(
            ['Request parsed with URL rule: <controller>/<action>'],
            self::inlineValues(self::paragraph(self::blockAt($view, 1))),
            'The captured trace message must stay visible.',
        );
        self::assertSame(
            Tone::INFO,
            self::paragraph(self::blockAt($view, 1))['tone'],
            'The trace message must read as a neutral callout.',
        );

        $heading = self::heading(self::blockAt($view, 2));

        self::assertSame(
            'Tested 2 rules before match',
            $heading['title'],
            'The trace heading must report the inspected rules and the match.',
        );
        self::assertTrue(
            $heading['section'],
            'The trace must open a section-level heading.',
        );

        $trace = self::table(self::blockAt($view, 3));

        self::assertSame(
            ['#', 'Rule', 'Parent', 'Result'],
            $trace['headers'],
            'The trace column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::IDENTIFIER,
                2 => ColumnStyle::IDENTIFIER,
                3 => ColumnStyle::PILL,
            ],
            $trace['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $trace['collapsible'],
            'A long trace must stay collapsible.',
        );
        self::assertSame(
            ['1', 'yii\\rest\\UrlRule', '—'],
            self::textValues([
                self::row($trace, 0)[0] ?? self::fail('Every inspected rule must be listed.'),
                self::row($trace, 0)[1] ?? self::fail('Every inspected rule must be listed.'),
                self::row($trace, 0)[2] ?? self::fail('Every inspected rule must be listed.'),
            ]),
            'A rule without parent must show the placeholder.',
        );
        self::assertSame(
            'no match',
            self::badge(self::row($trace, 0)[3] ?? self::fail('Every rule must report its result.'))['label'],
            'A rule that did not match must say so.',
        );
        self::assertSame(
            'match',
            self::badge(self::row($trace, 1)[3] ?? self::fail('Every rule must report its result.'))['label'],
            'The matching rule must be badged.',
        );
        self::assertSame(
            Tone::SUCCESS,
            self::badge(self::row($trace, 1)[3] ?? self::fail('Every rule must report its result.'))['tone'],
            'The matching rule must use the success tone.',
        );
    }

    public function testDisabledUrlManagerFlagsStayDeEmphasized(): void
    {
        $view = (new RouterPanel())->urlManager(false, false, '')->present(self::capture());

        $fields = self::fields(self::overview(self::blockAt($view, 0)));

        self::assertSame(
            'disabled',
            self::badge($fields['Pretty URL'] ?? self::fail('The overview must keep the pretty URL row.'))['label'],
            'A disabled flag must read as disabled.',
        );
        self::assertSame(
            Tone::MUTED,
            self::badge($fields['Strict parsing'] ?? self::fail('The overview must keep the parsing row.'))['tone'],
            'A disabled flag must stay de-emphasized.',
        );
        self::assertSame(
            '—',
            self::textValue($fields['Global suffix'] ?? self::fail('The overview must keep the suffix row.')),
            'A URL manager without suffix must show the placeholder.',
        );
        self::assertSame(
            ['kind' => 'text', 'value' => '—', 'style' => 'plain'],
            self::fields(self::overview(self::blockAt(self::present(self::unresolved()), 0)))['Route']
                ?? self::fail('The overview must keep the route row.'),
            'A request without route must show the plain placeholder.',
        );
    }

    public function testEmptyCaptureFallsBackToPlaceholdersAndExplainsEverySection(): void
    {
        $view = self::present(['action' => null, 'route' => '', 'message' => null, 'entries' => []]);

        self::assertSame(
            ['—', '0'],
            self::metricValues($view->summaryMetrics()),
            'A request without route must show the placeholder.',
        );
        self::assertSame(
            '—',
            self::textValue(
                self::fields(self::overview(self::blockAt($view, 0)))['Action']
                    ?? self::fail('The overview must keep the action row.'),
            ),
            'A request without action must show the placeholder.',
        );
        self::assertSame(
            'Rules tested',
            self::heading(self::blockAt($view, 1))['title'],
            'An empty trace must keep a neutral heading.',
        );
        self::assertSame(
            ['The router captured no rule trace for this request.'],
            self::inlineValues(self::paragraph(self::blockAt($view, 2))),
            'An empty trace must be explained.',
        );
        $rulesHeading = self::heading(self::blockAt($view, 3));

        self::assertSame(
            'URL rules (0)',
            $rulesHeading['title'],
            'The rules heading must report the rule count.',
        );
        self::assertTrue(
            $rulesHeading['section'],
            'The rules section must open a section-level heading.',
        );
        self::assertSame(
            ['The URL manager declares no rules.'],
            self::inlineValues(self::paragraph(self::blockAt($view, 4))),
            'An empty rule set must be explained.',
        );

        $actionHeading = self::heading(self::blockAt($view, 5));

        self::assertSame(
            'Action routes (0)',
            $actionHeading['title'],
            'The action heading must report the action count.',
        );
        self::assertTrue(
            $actionHeading['section'],
            'The action section must open a section-level heading.',
        );
        self::assertSame(
            ['No actions are configured.'],
            self::inlineValues(self::paragraph(self::blockAt($view, 6))),
            'An empty action set must be explained.',
        );
    }

    public function testMetadataMatchesTheBuiltInRouterPanel(): void
    {
        $panel = new RouterPanel();

        self::assertSame(
            'router',
            $panel->id(),
            'The persisted panel identifier must stay stable.',
        );
        self::assertSame(
            'Router',
            $panel->name(),
            'The navigation title must stay stable.',
        );
        self::assertSame(
            'router',
            $panel->icon(),
            'The panel must reuse the existing icon.',
        );
    }

    public function testSingleInspectedRuleWithoutMatchUsesTheSingularHeading(): void
    {
        $view = self::present(
            [
                'action' => '',
                'route' => 'site/index',
                'message' => null,
                'entries' => [['rule' => 'first', 'parent' => '', 'match' => false]],
            ],
        );

        self::assertSame(
            ' rule tested',
            ($view->summaryMetrics()[1] ?? self::fail('The trace size must stay in the summary.'))['label'],
            'A single inspected rule must use the singular label.',
        );
        self::assertSame(
            'Tested 1 rule',
            self::heading(self::blockAt($view, 1))['title'],
            'A trace without match must not claim one.',
        );
        self::assertSame(
            '—',
            self::textValue(
                self::fields(self::overview(self::blockAt($view, 0)))['Action']
                    ?? self::fail('The overview must keep the action row.'),
            ),
            'An empty action must show the placeholder.',
        );
    }

    public function testUrlManagerConfigurationRulesAndActionRoutesComeFromTheAdapter(): void
    {
        $panel = new RouterPanel();

        self::assertNotSame(
            $panel,
            $panel->urlManager(true, true, '.html'),
            'New instance must be returned (immutability).',
        );
        self::assertNotSame(
            $panel,
            $panel->rules([]),
            'New instance must be returned (immutability).',
        );
        self::assertNotSame(
            $panel,
            $panel->actionRoutes([]),
            'New instance must be returned (immutability).',
        );

        $view = $panel
            ->urlManager(true, true, '.html')
            ->rules(
                [
                    RouterRuleRow::from(
                        [
                            'name' => 'post/<id:\\d+>',
                            'route' => 'post/view',
                            'verb' => ['GET', 'HEAD'],
                            'suffix' => '.json',
                            'mode' => 'PARSING ONLY',
                            'type' => 'yii\\web\\UrlRule',
                        ],
                    ),
                    RouterRuleRow::from(['name' => 'bare']),
                ],
            )
            ->actionRoutes(
                [
                    ActionRouteRow::from(
                        'app\\controllers\\PostController::actionView',
                        ['route' => 'post/view', 'rule' => 'post/<id:\\d+>', 'count' => 3],
                    ),
                    ActionRouteRow::from('app\\controllers\\SiteController::actionIndex', []),
                ],
            )
            ->present(self::capture());

        $fields = self::fields(self::overview(self::blockAt($view, 0)));

        self::assertSame(
            '.html',
            self::textValue($fields['Global suffix'] ?? self::fail('The overview must keep the suffix row.')),
            'The global suffix must survive the migration.',
        );
        self::assertSame(
            ['enabled', 'enabled'],
            [
                self::badge($fields['Pretty URL'] ?? self::fail('The overview must keep the pretty URL row.'))['label'],
                self::badge($fields['Strict parsing'] ?? self::fail('The overview must keep the parsing row.'))['label'],
            ],
            'Enabled URL manager flags must read as enabled.',
        );
        self::assertSame(
            'URL rules (2)',
            self::heading(self::blockAt($view, 3))['title'],
            'The rules heading must report the rule count.',
        );

        $rules = self::table(self::blockAt($view, 4));

        self::assertSame(
            ['#', 'Name', 'Route', 'Verb', 'Suffix', 'Mode', 'Type'],
            $rules['headers'],
            'The rule column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::MONOSPACE,
                2 => ColumnStyle::MONOSPACE,
                5 => ColumnStyle::IDENTIFIER,
                6 => ColumnStyle::IDENTIFIER,
            ],
            $rules['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $rules['collapsible'],
            'A long rule set must stay collapsible.',
        );
        self::assertSame(
            ['1', 'post/<id:\\d+>', 'post/view', 'GET, HEAD', '.json', 'PARSING ONLY', 'yii\\web\\UrlRule'],
            self::textValues(self::row($rules, 0)),
            'Every declared rule column must survive the migration.',
        );
        self::assertSame(
            ['2', 'bare', '—', '—', '—', '—', '—'],
            self::textValues(self::row($rules, 1)),
            'A rule declaring nothing must fall back to placeholders.',
        );
        self::assertSame(
            'Action routes (2)',
            self::heading(self::blockAt($view, 5))['title'],
            'The action heading must report the action count.',
        );

        $actions = self::table(self::blockAt($view, 6));

        self::assertSame(
            ['#', 'Action', 'Route', 'First matching rule', 'Rules tested'],
            $actions['headers'],
            'The action column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::IDENTIFIER,
                2 => ColumnStyle::MONOSPACE,
                3 => ColumnStyle::IDENTIFIER,
                4 => ColumnStyle::NUMBER,
            ],
            $actions['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $actions['collapsible'],
            'A long action list must stay collapsible.',
        );
        self::assertSame(
            ['1', 'app\\controllers\\PostController::actionView', 'post/view', 'post/<id:\\d+>', '3'],
            self::textValues(self::row($actions, 0)),
            'Every discovered action column must survive the migration.',
        );
        self::assertSame(
            ['2', 'app\\controllers\\SiteController::actionIndex', '—', '—', '0'],
            self::textValues(self::row($actions, 1)),
            'An action without route must fall back to placeholders.',
        );
    }

    /**
     * Builds a minimal routing capture payload.
     *
     * @return array<string, mixed> Payload accepted by {@see RouterPanel::present()}.
     */
    private static function capture(): array
    {
        return [
            'action' => 'app\\controllers\\SiteController::actionIndex',
            'route' => 'site/index',
            'message' => null,
            'entries' => [],
        ];
    }

    /**
     * Presents the given capture through the panel under test.
     *
     * @param array<string, mixed> $capture Routing capture payload.
     *
     * @return PanelView Description built by the panel.
     */
    private static function present(array $capture): PanelView
    {
        return (new RouterPanel())->present(RouterSnapshot::fromArray($capture, '$')->jsonSerialize());
    }

    /**
     * Builds a routing capture payload without a resolved route.
     *
     * @return array<string, mixed> Payload accepted by {@see RouterPanel::present()}.
     */
    private static function unresolved(): array
    {
        return [
            'action' => null,
            'route' => '',
            'message' => null,
            'entries' => [],
        ];
    }
}

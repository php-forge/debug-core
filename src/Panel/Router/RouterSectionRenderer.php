<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Router;

use PHPForge\Debug\Helper\Tabs;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Phrasing\{Code, Span};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};

use function count;
use function sprintf;

/**
 * Renders the Router panel detail view from framework-neutral row models.
 *
 * Stateless static helpers: the public entry point takes the typed Current Route view plus pre-built rule and
 * action rows, and returns a fully-rendered HTML string. Concentrates tab-strip wiring, badge tinting, the three
 * section tables (Current Route logs / Router Rules / Action Routes), and the callout block in one testable place.
 */
final class RouterSectionRenderer
{
    /**
     * Renders the entire Router panel detail: the router-wide flags strip, the tab strip (Current Route / Router
     * Rules / Action Routes), and the per-tab content panels.
     *
     * Usage example:
     * ```php
     * $html = \PHPForge\Debug\Panel\Router\RouterSectionRenderer::renderTabs($current, $rules, $actions, $badges);
     * ```
     *
     * @param RouterCurrentView $current Current-route resolver view.
     * @param list<RouterRuleRow> $ruleRows Router rules in display order.
     * @param list<ActionRouteRow> $actionRows Discovered action routes in display order.
     * @param list<array{label: string, variant: string}> $badges Router-wide flag badges for the strip.
     */
    public static function renderTabs(
        RouterCurrentView $current,
        array $ruleRows,
        array $actionRows,
        array $badges,
    ): string {
        $flags = self::renderFlagsStrip($badges);

        $tabs = Tabs::render(
            'router',
            'Router data',
            [
                ['label' => 'Current Route', 'content' => self::renderCurrentRoutePanel($current)],
                ['label' => 'Router Rules', 'content' => self::renderRouterRulesPanel($ruleRows)],
                ['label' => 'Action Routes', 'content' => self::renderActionRoutesPanel($actionRows)],
            ],
        );

        return "{$flags}{$tabs}";
    }

    /**
     * Renders the Action Routes section as a `<table>` of action → route, first matching rule, and rules tested.
     *
     * @param list<ActionRouteRow> $actionRows Discovered action routes in display order.
     */
    private static function renderActionRoutesPanel(array $actionRows): string
    {
        if ($actionRows === []) {
            return H2::tag()->content('No actions configured.')->render();
        }

        $rows = [];

        foreach ($actionRows as $i => $row) {
            $rows[] = Tr::tag()
                ->html(
                    Td::tag()->content((string) ($i + 1)),
                    Td::tag()->content($row->action),
                    Td::tag()->content($row->route),
                    Td::tag()->content($row->rule),
                    Td::tag()->content((string) $row->count),
                );
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()->html(
                                    Th::tag()->scope('col')->content('#'),
                                    Th::tag()->scope('col')->content('Action'),
                                    Th::tag()->scope('col')->content('Route'),
                                    Th::tag()->scope('col')->content('First Matching Rule'),
                                    Th::tag()->scope('col')->content('Rules Tested'),
                                ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    /**
     * Renders one read-only badge chip for the flags strip.
     */
    private static function renderBadgeChip(string $label, string $variant): Span
    {
        return Span::tag()
            ->class("yii-debug-badge yii-debug-badge-{$variant}")
            ->content($label);
    }

    /**
     * Renders the callout block surfaced by {@see RouterCurrentView::$message}.
     */
    private static function renderCalloutBlock(RouterCurrentView $current): string
    {
        if ($current->message === null) {
            return '';
        }

        return Div::tag()
            ->class('yii-debug-callout yii-debug-callout-info yii-debug-router-callout')
            ->html(
                P::tag()
                    ->class('yii-debug-router-callout-message')
                    ->content($current->message),
            )
            ->render();
    }

    /**
     * Renders the Current Route section: route summary, heading, callout block, and rules-tested log table.
     *
     * Falls back to a dedicated empty-state heading when no route resolution data was captured for the request.
     */
    private static function renderCurrentRoutePanel(RouterCurrentView $current): string
    {
        $heading = $current->count === 0
            ? ''
            : H2::tag()
                ->content(
                    sprintf(
                        'Tested %d %s%s.',
                        $current->count,
                        $current->count === 1 ? 'rule' : 'rules',
                        $current->hasMatch ? ' before match' : '',
                    ),
                )
                ->render();

        $summary = self::renderRouteSummary($current);
        $callout = self::renderCalloutBlock($current);
        $logs = self::renderLogsTable($current);
        $body = "{$summary}{$heading}{$callout}{$logs}";

        return $body === ''
            ? H2::tag()->content('No route resolution captured.')->render()
            : $body;
    }

    /**
     * Renders the router-wide flags strip from adapter-supplied badges.
     *
     * Reuses the shared `yii-debug-grid-summary` strip so the read-only flags sit above the tab row instead of
     * competing with the navigable tabs.
     *
     * @param list<array{label: string, variant: string}> $badges Router-wide flag badges.
     */
    private static function renderFlagsStrip(array $badges): string
    {
        if ($badges === []) {
            return '';
        }

        $chips = [];

        foreach ($badges as $badge) {
            $chips[] = self::renderBadgeChip($badge['label'], $badge['variant']);
        }

        return Header::tag()
            ->class('yii-debug-grid-summary')
            ->html(...$chips)
            ->render();
    }

    /**
     * Renders the rules-tested log table beneath the Current Route callout.
     *
     * Returns `''` when there are no captured logs, since the heading already conveys the `Tested 0 rules` state.
     */
    private static function renderLogsTable(RouterCurrentView $current): string
    {
        if (count($current->logs) === 0) {
            return '';
        }

        $rows = [];

        foreach ($current->logs as $i => $row) {
            $tr = Tr::tag()
                ->html(
                    Td::tag()->content((string) ($i + 1)),
                    Td::tag()->content($row->rule),
                    Td::tag()->content($row->parent),
                );

            if ($row->match) {
                $tr = $tr->class('yii-debug-row-success');
            }

            $rows[] = $tr;
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()->html(
                                    Th::tag()->scope('col')->content('#'),
                                    Th::tag()->scope('col')->content('Rule'),
                                    Th::tag()->scope('col')->content('Parent'),
                                ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    /**
     * Renders the Router Rules section as a `<table>` of rule → target, verb, suffix, mode, and type.
     *
     * @param list<RouterRuleRow> $ruleRows Router rules in display order.
     */
    private static function renderRouterRulesPanel(array $ruleRows): string
    {
        if (count($ruleRows) === 0) {
            return H2::tag()
                ->content('No routing rules configured.')
                ->render();
        }

        $rows = [];

        foreach ($ruleRows as $i => $row) {
            $rows[] = Tr::tag()
                ->html(
                    Td::tag()->content((string) ($i + 1)),
                    Td::tag()->content($row->name),
                    Td::tag()->content($row->route),
                    Td::tag()->content($row->verb),
                    Td::tag()->content($row->suffix),
                    Td::tag()->content($row->mode),
                    Td::tag()->content($row->type),
                );
        }

        return Div::tag()
            ->class('yii-debug-table-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table')
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()->html(
                                    Th::tag()->scope('col')->content('#'),
                                    Th::tag()->scope('col')->content('Rule'),
                                    Th::tag()->scope('col')->content('Target'),
                                    Th::tag()->scope('col')->content('Verb'),
                                    Th::tag()->scope('col')->content('Suffix'),
                                    Th::tag()->scope('col')->content('Mode'),
                                    Th::tag()->scope('col')->content('Type'),
                                ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }

    /**
     * Renders the resolved-route / dispatched-action summary `<dl>` for the Current Route section.
     *
     * Surfaces the captured route and action unconditionally, so the pane stays informative when the resolver left no
     * trace messages to replay.
     */
    private static function renderRouteSummary(RouterCurrentView $current): string
    {
        if ($current->route === '' && $current->action === '') {
            return '';
        }

        $items = [];

        if ($current->route !== '') {
            $items[] = Dt::tag()
                ->content('Resolved route');
            $items[] = Dd::tag()
                ->html(Code::tag()->content($current->route));
        }

        if ($current->action !== '') {
            $items[] = Dt::tag()
                ->content('Dispatched action');
            $items[] = Dd::tag()
                ->html(Code::tag()->content($current->action));
        }

        return Dl::tag()
            ->class('yii-debug-router-summary')
            ->html(...$items)
            ->render();
    }
}

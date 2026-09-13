<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Router;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};

use function count;
use function sprintf;

/**
 * Presents the routing trace of the request, the configured URL rules, and the discovered action routes.
 *
 * URL rules and action routes are read from live framework services, so the adapter supplies them with
 * {@see self::rules()}, {@see self::actionRoutes()}, and {@see self::urlManager()} before presenting.
 *
 * Usage example:
 * ```php
 * $view = (new \PHPForge\Debug\Panel\Router\RouterPanel())
 *     ->urlManager(true, false, '')
 *     ->rules($ruleRows)
 *     ->actionRoutes($actionRows)
 *     ->present($snapshot->jsonSerialize());
 * ```
 *
 * @phpstan-import-type BadgeInline from PanelView
 */
final class RouterPanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Router navigation entry.
     */
    protected const string ICON = 'router';

    /**
     * @var string Stable identifier associating the panel with the captured routing payload.
     */
    protected const string ID = 'router';

    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = 'Router';

    /**
     * @var string Placeholder shown wherever the capture left a field empty.
     */
    private const string PLACEHOLDER = '—';

    /**
     * @var list<ActionRouteRow> Discovered action routes in display order.
     */
    private array $actionRows = [];

    /**
     * @var bool Whether the URL manager generates and parses pretty URLs.
     */
    private bool $prettyUrl = false;

    /**
     * @var list<RouterRuleRow> Configured URL rules in display order.
     */
    private array $ruleRows = [];

    /**
     * @var bool Whether the URL manager only accepts requests matching a configured rule.
     */
    private bool $strictParsing = false;

    /**
     * @var string Global URL suffix, or `''` when the URL manager declares none.
     */
    private string $suffix = '';

    /**
     * Returns a new instance with the discovered action routes.
     *
     * @param list<ActionRouteRow> $rows Action routes in display order.
     *
     * @return self New instance carrying the requested action routes.
     */
    public function actionRoutes(array $rows): self
    {
        $new = clone $this;
        $new->actionRows = $rows;

        return $new;
    }

    /**
     * Builds the panel view from the decoded routing capture.
     *
     * @param array<string, mixed> $data Decoded panel payload with `action`, `route`, `message`, and `entries` keys.
     *
     * @return PanelView Route overview, rules-tested trace, URL rules, and action routes.
     */
    public function present(array $data): PanelView
    {
        $snapshot = RouterSnapshot::fromArray($data, '$.router');

        $entries = $snapshot->entries();

        $tested = count($entries);

        $route = $snapshot->route;

        $view = PanelView::create()
            ->summary('', $route === '' ? self::PLACEHOLDER : $route)
            ->summary($tested === 1 ? ' rule tested' : ' rules tested', $tested)
            ->toolbar('Route', $route === '' ? self::PLACEHOLDER : $route)
            ->overview(
                [
                    'Route' => $route === '' ? self::PLACEHOLDER : PanelView::code($route),
                    'Action' => self::action($snapshot),
                    'Pretty URL' => self::flag($this->prettyUrl),
                    'Strict parsing' => self::flag($this->strictParsing),
                    'Global suffix' => $this->suffix === '' ? self::PLACEHOLDER : PanelView::code($this->suffix),
                ],
                true,
            );

        if ($snapshot->message !== null) {
            $view = $view->callout(Tone::INFO, $snapshot->message);
        }

        $view = $view->heading(self::testedHeading($tested, $snapshot->hasMatch()), true);

        if ($entries === []) {
            $view = $view->paragraph('The router captured no rule trace for this request.');
        } else {
            $rows = [];

            foreach ($entries as $index => $entry) {
                $rows[] = [
                    $index + 1,
                    $entry->rule,
                    $entry->parent === '' ? self::PLACEHOLDER : $entry->parent,
                    $entry->match
                        ? PanelView::badge('match', Tone::SUCCESS)
                        : PanelView::badge('no match', Tone::MUTED),
                ];
            }

            $view = $view->table(
                ['#', 'Rule', 'Parent', 'Result'],
                $rows,
                true,
                [
                    0 => ColumnStyle::NUMBER,
                    1 => ColumnStyle::IDENTIFIER,
                    2 => ColumnStyle::IDENTIFIER,
                    3 => ColumnStyle::PILL,
                ],
            );
        }

        $view = $view->heading(sprintf('URL rules (%d)', count($this->ruleRows)), true);

        $view = $this->ruleRows === []
            ? $view->paragraph('The URL manager declares no rules.')
            : $view->table(
                ['#', 'Name', 'Route', 'Verb', 'Suffix', 'Mode', 'Type'],
                self::ruleRows($this->ruleRows),
                true,
                [
                    0 => ColumnStyle::NUMBER,
                    1 => ColumnStyle::MONOSPACE,
                    2 => ColumnStyle::MONOSPACE,
                    5 => ColumnStyle::IDENTIFIER,
                    6 => ColumnStyle::IDENTIFIER,
                ],
            );

        $view = $view->heading(sprintf('Action routes (%d)', count($this->actionRows)), true);

        return $this->actionRows === []
            ? $view->paragraph('No actions are configured.')
            : $view->table(
                ['#', 'Action', 'Route', 'First matching rule', 'Rules tested'],
                self::actions($this->actionRows),
                true,
                [
                    0 => ColumnStyle::NUMBER,
                    1 => ColumnStyle::IDENTIFIER,
                    2 => ColumnStyle::MONOSPACE,
                    3 => ColumnStyle::IDENTIFIER,
                    4 => ColumnStyle::NUMBER,
                ],
            );
    }

    /**
     * Returns a new instance with the configured URL rules.
     *
     * @param list<RouterRuleRow> $rows URL rules in display order.
     *
     * @return self New instance carrying the requested URL rules.
     */
    public function rules(array $rows): self
    {
        $new = clone $this;
        $new->ruleRows = $rows;

        return $new;
    }

    /**
     * Returns a new instance with the URL manager configuration.
     *
     * @param bool $prettyUrl Whether the URL manager generates and parses pretty URLs.
     * @param bool $strictParsing Whether the URL manager only accepts requests matching a configured rule.
     * @param string $suffix Global URL suffix, or `''` when the URL manager declares none.
     *
     * @return self New instance carrying the requested configuration.
     */
    public function urlManager(bool $prettyUrl, bool $strictParsing, string $suffix): self
    {
        $new = clone $this;
        $new->prettyUrl = $prettyUrl;
        $new->strictParsing = $strictParsing;
        $new->suffix = $suffix;

        return $new;
    }

    /**
     * Returns the dispatched action, or the placeholder when routing resolved none.
     *
     * @param RouterSnapshot $snapshot Captured routing trace.
     *
     * @return string Dispatched action descriptor, or the placeholder.
     */
    private static function action(RouterSnapshot $snapshot): string
    {
        $action = $snapshot->action;

        return $action === null || $action === '' ? self::PLACEHOLDER : $action;
    }

    /**
     * Builds the action-routes table rows in discovery order.
     *
     * @param list<ActionRouteRow> $rows Discovered action routes in display order.
     *
     * @return list<list<mixed>> One row per action, matching the declared column order.
     */
    private static function actions(array $rows): array
    {
        $result = [];

        foreach ($rows as $index => $row) {
            $result[] = [
                $index + 1,
                $row->action,
                $row->route === '' ? self::PLACEHOLDER : $row->route,
                $row->rule === '' ? self::PLACEHOLDER : $row->rule,
                $row->count,
            ];
        }

        return $result;
    }

    /**
     * Builds the badge reporting whether a URL manager flag is enabled.
     *
     * @param bool $enabled Whether the flag is enabled.
     *
     * @return BadgeInline Success badge when enabled, muted badge otherwise.
     */
    private static function flag(bool $enabled): array
    {
        return $enabled
            ? PanelView::badge('enabled', Tone::SUCCESS)
            : PanelView::badge('disabled', Tone::MUTED);
    }

    /**
     * Builds the URL-rules table rows in declaration order.
     *
     * @param list<RouterRuleRow> $rows Configured URL rules in display order.
     *
     * @return list<list<mixed>> One row per rule, matching the declared column order.
     */
    private static function ruleRows(array $rows): array
    {
        $result = [];

        foreach ($rows as $index => $row) {
            $result[] = [
                $index + 1,
                $row->name,
                $row->route === '' ? self::PLACEHOLDER : $row->route,
                $row->verb === '' ? self::PLACEHOLDER : $row->verb,
                $row->suffix === '' ? self::PLACEHOLDER : $row->suffix,
                $row->mode === '' ? self::PLACEHOLDER : $row->mode,
                $row->type === '' ? self::PLACEHOLDER : $row->type,
            ];
        }

        return $result;
    }

    /**
     * Builds the heading of the rules-tested trace.
     *
     * @param int $tested Number of rules inspected during routing.
     * @param bool $matched Whether any inspected rule reported a successful match.
     *
     * @return string Heading describing the routing pass.
     */
    private static function testedHeading(int $tested, bool $matched): string
    {
        if ($tested === 0) {
            return 'Rules tested';
        }

        return sprintf(
            'Tested %d %s%s',
            $tested,
            $tested === 1 ? 'rule' : 'rules',
            $matched ? ' before match' : '',
        );
    }
}

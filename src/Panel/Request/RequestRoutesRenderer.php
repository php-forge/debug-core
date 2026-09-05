<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\{Disclosure, EmptyState, Vocabulary};
use PHPForge\Debug\Panel\Request\Routing\{
    CurrentRouteView,
    RouteBadge,
    RouteDefinition,
    RouteInventoryView,
    RouteTraceRow,
};
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\H2;
use UIAwesome\Html\Interactive\{Details, Summary};
use UIAwesome\Html\List\{Dd, Dl, Dt, Li, Ol};
use UIAwesome\Html\Phrasing\{Code, Span};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Table\{Table, Tbody, Td, Th, Thead, Tr};

use function count;
use function in_array;
use function rtrim;

/**
 * Renders a common route inventory with framework-specific metadata inside row disclosures.
 *
 * @internal
 */
final class RequestRoutesRenderer
{
    private const array BADGE_VARIANTS = ['danger', 'info', 'muted', 'success', 'warning'];

    public static function render(CurrentRouteView $current, RouteInventoryView $inventory): string
    {
        $routeCount = count($inventory->getRoutes());

        $heading = [
            Div::tag()
                ->class('yii-debug-route-inventory-title')
                ->html(
                    H2::tag()->content('Application routes'),
                    Span::tag()
                        ->class('yii-debug-route-inventory-count')
                        ->content((string) $routeCount),
                ),
        ];

        if ($inventory->getRoutes() !== []) {
            $heading[] = InputSearch::tag()
                ->addAriaAttribute('label', 'Filter routes')
                ->addDataAttribute('yii-debug-filter', true)
                ->class('yii-debug-filter-input yii-debug-route-filter')
                ->placeholder('Filter routes…');
        }

        $content = Header::tag()
            ->class('yii-debug-route-inventory-header')
            ->html(...$heading)
            ->render();

        $content .= self::renderInventoryContext($inventory);

        if ($inventory->getError() !== null && $inventory->getError() !== '') {
            $content .= Div::tag()
                ->class('yii-debug-callout yii-debug-callout-danger yii-debug-route-inventory-error')
                ->html(P::tag()->content($inventory->getError()))
                ->render();
        }

        $content .= $inventory->getRoutes() === []
            ? EmptyState::card('No application routes registered.')
            : self::renderInventory($inventory->getRoutes(), $current);

        return $content . self::renderResolution($current);
    }

    private static function renderBadge(RouteBadge $badge): Span
    {
        $variant = in_array($badge->variant, self::BADGE_VARIANTS, true) ? $badge->variant : 'muted';

        return Span::tag()
            ->class("yii-debug-badge yii-debug-badge-{$variant}")
            ->content($badge->label);
    }

    private static function renderDetails(RouteDefinition $route): Dl
    {
        $items = [];

        $values = [
            'Hosts' => $route->getHosts() === [] ? 'Any' : $route->getHosts(),
            'Target' => $route->getName() !== '' ? $route->getTarget() : null,
            'Action' => $route->getAction(),
            'Middleware' => $route->getMiddlewares() === [] ? 'None' : $route->getMiddlewares(),
            'Suffix' => $route->getSuffix(),
            'Mode' => $route->getMode(),
            'Type' => $route->getType(),
        ];

        foreach ($values as $label => $value) {
            if ($value === null) {
                continue;
            }

            $items[] = Div::tag()->html(
                Dt::tag()->content($label),
                Dd::tag()->html(RequestDiagnosticValueRenderer::header($value)),
            );
        }

        return Dl::tag()
            ->class('yii-debug-route-metadata')
            ->html(...$items);
    }

    /**
     * @param list<RouteDefinition> $routes
     */
    private static function renderInventory(array $routes, CurrentRouteView $current): string
    {
        $headings = [];

        foreach (['#', 'Methods', 'Pattern', 'Route', 'Details'] as $label) {
            $headings[] = Span::tag()->content($label);
        }

        $rows = [];

        foreach ($routes as $index => $route) {
            $matched = $current->getRoute() !== '' && (
                $route->getName() === $current->getRoute()
                || $route->getTarget() === $current->getRoute()
                || $route === $current->getDefinition()
            );

            $identity = $route->getName() !== '' ? $route->getName() : ($route->getTarget() ?? '—');

            $contents = [Code::tag()->content($identity)];

            if ($matched) {
                $contents[] = Span::tag()
                    ->class('yii-debug-badge yii-debug-badge-success yii-debug-route-match')
                    ->content('Matched');
            }

            $entry = Details::tag()
                ->class('yii-debug-route-entry')
                ->addDataAttribute('yii-debug-filter-details', true)
                ->addDataAttribute('yii-debug-filter-default-open', 'false')
                ->html(
                    Summary::tag()->class('yii-debug-route-summary')->html(
                        Span::tag()->class('yii-debug-route-order')->content((string) ($index + 1)),
                        self::renderMethodChips($route->getMethods()),
                        Code::tag()->class('yii-debug-route-pattern')->content($route->getPattern() !== '' ? $route->getPattern() : '—'),
                        Span::tag()->class('yii-debug-route-identity')->html(...$contents),
                        Disclosure::hint(),
                    ),
                    self::renderDetails($route),
                );
            $row = Li::tag()->addDataAttribute('yii-debug-filter-row', true)->html($entry);

            if ($matched) {
                $row = $row->class('yii-debug-row-success')->addDataAttribute('yii-debug-route-match', true);
            }

            $rows[] = $row;
        }

        return Div::tag()
            ->class('yii-debug-route-ledger')
            ->addDataAttribute('yii-debug-filter-target', true)
            ->addDataAttribute('yii-debug-filter-unit', 'routes')
            ->html(
                Div::tag()
                    ->class('yii-debug-route-columns')
                    ->addAriaAttribute('hidden', 'true')
                    ->html(...$headings),
                Ol::tag()
                    ->class('yii-debug-route-list')
                    ->addAriaAttribute('label', 'Application routes')
                    ->html(...$rows),
                P::tag()
                    ->addDataAttribute('yii-debug-filter-empty', true)
                    ->addAttribute('hidden', true)
                    ->class('yii-debug-diagnostic-filter-empty')
                    ->content('No routes match this filter.'),
            )
            ->render();
    }

    private static function renderInventoryContext(RouteInventoryView $inventory): string
    {
        $source = rtrim($inventory->getSource(), " .\t\n\r\0\x0B");

        $message = $source === '' ? 'Configuration source unavailable.' : "Source: {$source}.";

        if ($inventory->isLive()) {
            $message .= ' Live configuration may differ from this capture.';
        }

        $badges = [];

        foreach ($inventory->getBadges() as $badge) {
            $badges[] = self::renderBadge($badge);
        }

        return Div::tag()
            ->class('yii-debug-route-inventory-context')
            ->html(
                P::tag()
                    ->class('yii-debug-route-inventory-provenance')
                    ->content($message),
                Div::tag()
                    ->class('yii-debug-route-inventory-badges')
                    ->html(...$badges),
            )
            ->render();
    }

    /**
     * @param list<string> $methods
     */
    private static function renderMethodChips(array $methods): Span
    {
        if ($methods === []) {
            return Span::tag()
                ->class('yii-debug-route-methods yii-debug-route-methods-any')
                ->content('Any');
        }

        $chips = [];

        foreach ($methods as $method) {
            $chips[] = Span::tag()
                ->class('yii-debug-route-method yii-debug-verb-' . Vocabulary::verb($method))
                ->content($method);
        }

        return Span::tag()
            ->class('yii-debug-route-methods')
            ->html(...$chips);
    }

    private static function renderResolution(CurrentRouteView $current): string
    {
        if (($current->getMessage() === null || $current->getMessage() === '') && $current->getTrace() === []) {
            return '';
        }

        $body = $current->getMessage() === null || $current->getMessage() === ''
            ? ''
            : P::tag()
                ->class('yii-debug-route-resolution-message')
                ->content($current->getMessage())
                ->render();

        if ($current->getTrace() !== []) {
            $body .= self::renderTraceTable($current->getTrace());
        }

        $count = count($current->getTrace());

        $title = $count === 0 ? 'Routing resolution' : "Routing resolution ({$count} rules tested)";

        return Div::tag()
            ->class('yii-debug-route-resolution')
            ->html(Disclosure::render($title, $body))
            ->render();
    }

    /**
     * @param list<RouteTraceRow> $trace
     */
    private static function renderTraceTable(array $trace): string
    {
        $rows = [];

        foreach ($trace as $index => $entry) {
            $result = Span::tag()
                ->class(
                    $entry->matched
                        ? 'yii-debug-badge yii-debug-badge-success'
                        : 'yii-debug-badge yii-debug-badge-muted',
                )
                ->content($entry->matched ? 'Matched' : 'Not matched');
            $row = Tr::tag()
                ->html(
                    Td::tag()->content((string) ($index + 1)),
                    Td::tag()->content($entry->rule),
                    Td::tag()->content($entry->parent !== '' ? $entry->parent : '—'),
                    Td::tag()->html($result),
                );

            if ($entry->matched) {
                $row = $row->class('yii-debug-row-success');
            }

            $rows[] = $row;
        }

        return Div::tag()
            ->class('yii-debug-table-wrap yii-debug-route-trace-wrap')
            ->html(
                Table::tag()
                    ->class('yii-debug-table yii-debug-route-trace')
                    ->html(
                        Thead::tag()
                            ->html(
                                Tr::tag()
                                    ->html(
                                        Th::tag()->scope('col')->content('#'),
                                        Th::tag()->scope('col')->content('Rule'),
                                        Th::tag()->scope('col')->content('Parent'),
                                        Th::tag()->scope('col')->content('Result'),
                                    ),
                            ),
                        Tbody::tag()->html(...$rows),
                    ),
            )
            ->render();
    }
}

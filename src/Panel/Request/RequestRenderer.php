<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\{Badge, Disclosure, EmptyState, Table, Tabs, Vocabulary};
use PHPForge\Debug\Panel\Request\Routing\{
    CurrentRouteView,
    RequestRoutingView,
    RouteInventoryView,
    RouteTraceRow,
};
use PHPForge\Debug\View\Grid\RowClass;
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;
use UIAwesome\Html\Table\{Td, Tr};

use function count;
use function implode;
use function in_array;

/**
 * Composes request and routing diagnostics into one framework-neutral detail view.
 */
final class RequestRenderer
{
    /**
     * @var list<string> Badge variants the shared vocabulary accepts.
     */
    private const array BADGE_VARIANTS = ['danger', 'info', 'muted', 'success', 'warning'];

    /**
     * Preserves the legacy Request presentation when no routing view is supplied.
     */
    public static function render(RequestView $view, RequestRoutingView|null $routing = null): string
    {
        if ($routing === null) {
            return RequestSectionRenderer::renderHero($view->hero) . RequestSectionRenderer::renderTabs($view->tabs);
        }

        return self::renderOverview($view->hero, $routing->current, $routing->inventory)
            . self::renderTabs($view, $routing);
    }

    /**
     * @param list<RequestSection> $sections
     */
    private static function hasSectionData(array $sections): bool
    {
        foreach ($sections as $section) {
            if ($section->entries !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $values
     */
    private static function listValue(array $values, string $empty): string
    {
        return $values === [] ? $empty : implode(', ', $values);
    }

    /**
     * @param list<RequestSection> $sections
     */
    private static function renderDisclosureSections(array $sections): string
    {
        $content = '';

        foreach ($sections as $section) {
            $content .= RequestSectionRenderer::renderDisclosureSection($section);
        }

        return $content;
    }

    private static function renderHeaders(RequestTab|null $tab): string
    {
        if ($tab === null) {
            return EmptyState::card('No headers captured.');
        }

        $request = null;
        $response = null;

        foreach ($tab->sections as $section) {
            if ($section->id === 'request-headers' && $request === null) {
                $request = $section;

                continue;
            }

            if ($section->id === 'response-headers' && $response === null) {
                $response = $section;

                continue;
            }

            return self::renderSections($tab->sections);
        }

        if ($request === null || $response === null) {
            return self::renderSections($tab->sections);
        }

        return RequestHeadersRenderer::render(
            $request->entries,
            $response->entries,
        );
    }

    private static function renderMetaItem(string $label, string $value): Span
    {
        return Span::tag()
            ->class('yii-debug-request-overview-meta-item')
            ->html(
                Span::tag()
                    ->class('yii-debug-request-overview-meta-label')
                    ->content($label),
                Span::tag()
                    ->class('yii-debug-request-overview-tag')
                    ->title($value)
                    ->content($value),
            );
    }

    private static function renderMetric(string $label, string $value, Span|null $marker = null): Div
    {
        $value_ = Dd::tag()->title($value);

        return Div::tag()
            ->class('yii-debug-request-overview-metric')
            ->html(
                Dt::tag()->content($label),
                $marker === null
                    ? $value_->content($value)
                    : $value_->html(Span::tag()->content($value), $marker),
            );
    }

    private static function renderOverview(
        RequestHero $hero,
        CurrentRouteView $current,
        RouteInventoryView|null $inventory,
    ): string {
        $definition = $current->getDefinition();
        $route = $current->getRoute() !== '' ? $current->getRoute() : ($definition?->getName() ?? '');
        $action = $current->getAction() ?? $definition?->getAction() ?? '';
        $method = $hero->getMethod();
        $url = $hero->getUrl() !== '' ? $hero->getUrl() : 'URL unavailable';

        $identity = [];

        if ($method !== '') {
            $identity[] = RequestSectionRenderer::renderMethodPill($method);
        }

        $identity[] = Span::tag()
            ->class('yii-debug-request-hero-url')
            ->title($hero->getUrl())
            ->content($url);

        $status = '';

        if ($hero->getStatusCode() > 0) {
            $status = Div::tag()
                ->class('yii-debug-request-overview-status')
                ->html(
                    Span::tag()
                        ->class(
                            'yii-debug-request-overview-status-value yii-debug-snapshot-status '
                            . "yii-debug-status-{$hero->getStatusVariant()}",
                        )
                        ->content((string) $hero->getStatusCode()),
                );
        }

        $meta = [];

        foreach (['IP' => $hero->getIp(), 'Time' => $hero->getTime()] as $label => $value) {
            if ($value !== '') {
                $meta[] = self::renderMetaItem($label, $value);
            }
        }

        if ($definition !== null) {
            $meta[] = self::renderMetaItem('Pattern', $definition->getPattern());
            $meta[] = self::renderMetaItem('Methods', self::listValue($definition->getMethods(), 'Any'));
            $meta[] = self::renderMetaItem('Hosts', self::listValue($definition->getHosts(), 'Any'));

            if ($definition->getMiddlewares() !== null) {
                $meta[] = self::renderMetaItem('Middleware', self::listValue($definition->getMiddlewares(), 'None'));
            }
        }

        foreach ($hero->getFlags() as $flag) {
            $meta[] = Span::tag()
                ->class('yii-debug-request-overview-flag')
                ->content($flag);
        }

        $configuration = [];

        foreach ($inventory?->getBadges() ?? [] as $badge) {
            $configuration[] = Badge::render(
                $badge->label,
                in_array($badge->variant, self::BADGE_VARIANTS, true) ? $badge->variant : 'muted',
            );
        }

        if ($configuration !== []) {
            $meta[] = Span::tag()
                ->class('yii-debug-request-overview-config')
                ->html(...$configuration);
        }

        $callouts = [];

        foreach ([$current->getError(), $inventory?->getError()] as $failure) {
            if ($failure !== null && $failure !== '') {
                $callouts[] = Div::tag()
                    ->class('yii-debug-callout yii-debug-callout-danger yii-debug-request-routing-error')
                    ->html(P::tag()->content($failure));
            }
        }

        $callouts[] = self::renderResolution($current);

        return Section::tag()
            ->addAriaAttribute('label', 'Request overview')
            ->class('yii-debug-request-overview yii-debug-verb-' . Vocabulary::verb($method))
            ->html(
                Header::tag()
                    ->class('yii-debug-request-overview-header')
                    ->html(
                        Div::tag()
                            ->class('yii-debug-request-overview-identity')
                            ->html(
                                Div::tag()
                                    ->class('yii-debug-request-hero-line')
                                    ->html(...$identity),
                            ),
                        $status,
                    ),
                Dl::tag()
                    ->class('yii-debug-request-overview-metrics')
                    ->html(
                        self::renderMetric(
                            'Route',
                            $route !== '' ? $route : 'Unresolved',
                            $definition === null
                                ? null
                                : Badge::render('Matched', 'success', 'yii-debug-route-match'),
                        ),
                        self::renderMetric(
                            'Action',
                            $action !== '' ? $action : 'Unavailable',
                        ),
                        self::renderMetric(
                            'Duration',
                            $hero->getDurationMs() !== '' ? $hero->getDurationMs() : 'Unavailable',
                        ),
                    ),
                Div::tag()
                    ->class('yii-debug-request-overview-meta')
                    ->html(...$meta),
                ...$callouts,
            )
            ->render();
    }

    /**
     * Renders the routing resolution the request went through, as a collapsed disclosure under the overview.
     *
     * @param CurrentRouteView $current Diagnostics for the route the request resolved to.
     *
     * @return string Collapsed resolution, or an empty string when nothing was captured.
     */
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
     * @param list<RequestSection> $sections
     */
    private static function renderSections(array $sections): string
    {
        $content = '';

        foreach ($sections as $section) {
            $content .= RequestSectionRenderer::renderSection($section);
        }

        return $content;
    }

    private static function renderServer(RequestTab $tab, RequestView $view): string
    {
        if (count($tab->sections) !== 1 || $tab->sections[0]->id !== 'server') {
            return self::renderSections($tab->sections);
        }

        return RequestServerRenderer::renderForRequest(
            $tab->sections[0]->entries,
            $view,
        );
    }

    private static function renderTabs(RequestView $view, RequestRoutingView $routing): string
    {
        $tabs = [];

        $parameters = self::tab($view->tabs, 'parameters');

        $inputSections = [];

        if ($routing->current->getParameters() !== []) {
            $inputSections[] = new RequestSection(
                caption: 'Route parameters',
                entries: $routing->current->getParameters(),
                filterable: true,
                id: 'route-parameters',
            );
        }

        if ($parameters !== null) {
            foreach ($parameters->sections as $section) {
                if ($section->id !== 'routing') {
                    $inputSections[] = $section;
                }
            }
        }

        $tabs[] = [
            'label' => 'Input',
            'content' => self::hasSectionData($inputSections)
                ? self::renderDisclosureSections($inputSections)
                : EmptyState::card('No input data captured.'),
        ];

        $headers = self::tab($view->tabs, 'headers');

        $tabs[] = [
            'label' => 'Headers',
            'content' => self::renderHeaders($headers),
        ];

        $session = self::tab($view->tabs, 'session');

        if ($session !== null) {
            $tabs[] = [
                'label' => 'Session',
                'content' => self::renderDisclosureSections($session->sections),
            ];
        }

        $server = self::tab($view->tabs, 'server');

        if ($server !== null) {
            $tabs[] = [
                'label' => 'Server',
                'content' => self::renderServer($server, $view),
            ];
        }

        return Div::tag()
            ->class('yii-debug-request-tabs')
            ->html(Tabs::render('request', 'Request data', $tabs))
            ->render();
    }

    /**
     * Renders the rules the router tested, in the order it tested them.
     *
     * @param list<RouteTraceRow> $trace Tested rules in evaluation order.
     *
     * @return string Rendered trace table.
     */
    private static function renderTraceTable(array $trace): string
    {
        $rows = [];

        foreach ($trace as $index => $entry) {
            $result = $entry->matched
                ? Badge::render('Matched', 'success')
                : Badge::render('Not matched', 'warning');
            $row = Tr::tag()
                ->html(
                    Td::tag()->content((string) ($index + 1)),
                    Td::tag()->content($entry->rule),
                    Td::tag()->content($entry->parent !== '' ? $entry->parent : '—'),
                    Td::tag()->html($result),
                );

            if ($entry->matched) {
                $row = $row->attributes(RowClass::for('success'));
            }

            $rows[] = $row;
        }

        return Table::render(
            ['#', 'Rule', 'Parent', 'Result'],
            $rows,
            'yii-debug-table yii-debug-route-trace',
            'yii-debug-table-wrap yii-debug-route-trace-wrap',
        );
    }

    /**
     * @param list<RequestTab> $tabs
     */
    private static function tab(array $tabs, string $id): RequestTab|null
    {
        foreach ($tabs as $tab) {
            if ($tab->id === $id) {
                return $tab;
            }
        }

        return null;
    }
}

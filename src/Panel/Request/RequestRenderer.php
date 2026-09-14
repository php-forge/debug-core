<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\{Badge, Disclosure, EmptyState, Table, Tabs, Vocabulary};
use PHPForge\Debug\Panel\Request\Routing\{CurrentRouteView, RequestRoutingView, RouteInventoryView, RouteTraceRow};
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
use function sprintf;

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
     * Reports whether any section carried a captured entry.
     *
     * @param list<RequestSection> $sections Sections to inspect.
     *
     * @return bool `true` when at least one section holds an entry.
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
     * Joins a constraint list into a single line, falling back to an explicit value when it is empty.
     *
     * @param list<string> $values Constraint values in declaration order.
     * @param string $empty Value shown when the list is empty.
     *
     * @return string Comma-separated values, or the empty fallback.
     */
    private static function listValue(array $values, string $empty): string
    {
        return $values === [] ? $empty : implode(', ', $values);
    }

    /**
     * Renders each section as its own disclosure, skipping the ones the capture left empty.
     *
     * @param list<RequestSection> $sections Sections to render.
     *
     * @return string Concatenated disclosure markup.
     */
    private static function renderDisclosureSections(array $sections): string
    {
        $content = '';

        foreach ($sections as $section) {
            $content .= RequestSectionRenderer::renderDisclosureSection($section);
        }

        return $content;
    }

    /**
     * Renders the header exchange of the headers tab, or nothing when the capture recorded no header.
     *
     * @param RequestTab|null $tab Headers tab, or `null` when the capture declared none.
     *
     * @return string Header exchange markup, or `''` when there is nothing to show.
     */
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

    /**
     * Renders one labeled item of the hero meta strip.
     *
     * @param string $label Item label.
     * @param string $value Item value.
     *
     * @return Span Meta strip item.
     */
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

    /**
     * Renders one metric of the request overview, optionally followed by a badge.
     *
     * @param string $label Metric label.
     * @param string $value Metric value, also used as the title of the value cell.
     * @param Span|null $marker Badge appended after the value, or `null` to omit it.
     *
     * @return Div Overview metric.
     */
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

    /**
     * Renders the request overview: resolved route, dispatched action, duration, and the route definition.
     *
     * @param RequestHero $hero Typed request header carrying the timing and client data.
     * @param CurrentRouteView $current Route resolved for the request.
     * @param RouteInventoryView|null $inventory Routing trace, or `null` when the adapter captured none.
     *
     * @return string Request overview markup.
     */
    private static function renderOverview(
        RequestHero $hero,
        CurrentRouteView $current,
        RouteInventoryView|null $inventory,
    ): string {
        $definition = $current->getDefinition();
        $route = $current->getRoute() !== '' ? $current->getRoute() : ($definition?->getName() ?? '');
        $action = $current->getAction() ?? $definition?->getAction() ?? '';
        $method = $hero->getMethod();
        $url = $hero->getUrl() !== '' ? $hero->getUrl() : RequestMessage::URL_UNAVAILABLE->value;

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

        $fields = [RequestMessage::IP->value => $hero->getIp(), RequestMessage::TIME->value => $hero->getTime()];

        foreach ($fields as $label => $value) {
            if ($value !== '') {
                $meta[] = self::renderMetaItem($label, $value);
            }
        }

        if ($definition !== null) {
            $meta[] = self::renderMetaItem(RequestMessage::PATTERN->value, $definition->getPattern());
            $meta[] = self::renderMetaItem(
                RequestMessage::METHODS->value,
                self::listValue($definition->getMethods(), RequestMessage::ANY->value),
            );
            $meta[] = self::renderMetaItem(
                RequestMessage::HOSTS->value,
                self::listValue($definition->getHosts(), RequestMessage::ANY->value),
            );

            if ($definition->getMiddlewares() !== null) {
                $meta[] = self::renderMetaItem(
                    RequestMessage::MIDDLEWARE->value,
                    self::listValue($definition->getMiddlewares(), RequestMessage::NONE->value),
                );
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
            ->addAriaAttribute('label', RequestMessage::REQUEST_OVERVIEW->value)
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
                            RequestMessage::ROUTE->value,
                            $route !== '' ? $route : RequestMessage::UNRESOLVED->value,
                            $definition === null
                                ? null
                                : Badge::render(RequestMessage::MATCHED->value, 'success', 'yii-debug-route-match'),
                        ),
                        self::renderMetric(
                            RequestMessage::ACTION->value,
                            $action !== '' ? $action : RequestMessage::UNAVAILABLE->value,
                        ),
                        self::renderMetric(
                            RequestMessage::DURATION->value,
                            $hero->getDurationMs() !== '' ? $hero->getDurationMs() : RequestMessage::UNAVAILABLE->value,
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

        $title = $count === 0
            ? RequestMessage::ROUTING_RESOLUTION->value
            : sprintf(RequestMessage::ROUTING_RESOLUTION_COUNT->value, $count);

        return Div::tag()
            ->class('yii-debug-route-resolution')
            ->html(Disclosure::render($title, $body))
            ->render();
    }

    /**
     * Renders each section as a captioned block, skipping the ones the capture left empty.
     *
     * @param list<RequestSection> $sections Sections to render.
     *
     * @return string Concatenated section markup.
     */
    private static function renderSections(array $sections): string
    {
        $content = '';

        foreach ($sections as $section) {
            $content .= RequestSectionRenderer::renderSection($section);
        }

        return $content;
    }

    /**
     * Renders the server tab, grouping the captured variables and deriving the ones the capture missed.
     *
     * @param RequestTab $tab Server tab holding the captured variables.
     * @param RequestView $view Typed request view, used to derive the missing variables.
     *
     * @return string Server tab markup.
     */
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

    /**
     * Renders the tab strip, folding the routing trace into the input tab.
     *
     * @param RequestView $view Typed request view carrying the captured tabs.
     * @param RequestRoutingView $routing Typed routing view carrying the trace and the route inventory.
     *
     * @return string Tab strip markup.
     */
    private static function renderTabs(RequestView $view, RequestRoutingView $routing): string
    {
        $tabs = [];

        $parameters = self::tab($view->tabs, 'parameters');

        $inputSections = [];

        if ($routing->current->getParameters() !== []) {
            $inputSections[] = new RequestSection(
                caption: RequestMessage::ROUTE_PARAMETERS->value,
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
            'label' => RequestMessage::INPUT->value,
            'content' => self::hasSectionData($inputSections)
                ? self::renderDisclosureSections($inputSections)
                : EmptyState::card('No input data captured.'),
        ];

        $headers = self::tab($view->tabs, 'headers');

        $tabs[] = [
            'label' => RequestMessage::HEADERS->value,
            'content' => self::renderHeaders($headers),
        ];

        $session = self::tab($view->tabs, 'session');

        if ($session !== null) {
            $tabs[] = [
                'label' => RequestMessage::SESSION->value,
                'content' => self::renderDisclosureSections($session->sections),
            ];
        }

        $server = self::tab($view->tabs, 'server');

        if ($server !== null) {
            $tabs[] = [
                'label' => RequestMessage::SERVER->value,
                'content' => self::renderServer($server, $view),
            ];
        }

        return Div::tag()
            ->class('yii-debug-request-tabs')
            ->html(Tabs::render('request', RequestMessage::REQUEST_DATA->value, $tabs))
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
     * Returns the tab carrying the requested identifier.
     *
     * @param list<RequestTab> $tabs Captured tabs in display order.
     * @param string $id Identifier to look up.
     *
     * @return RequestTab|null Matching tab, or `null` when the capture declared none.
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

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use PHPForge\Debug\Helper\{EmptyState, Tabs, Vocabulary};
use PHPForge\Debug\Panel\Request\Routing\{CurrentRouteView, RequestRoutingView};
use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;

use function count;
use function implode;

/**
 * Composes request and routing diagnostics into one framework-neutral detail view.
 */
final class RequestRenderer
{
    /**
     * Preserves the legacy Request presentation when no routing view is supplied.
     */
    public static function render(RequestView $view, RequestRoutingView|null $routing = null): string
    {
        if ($routing === null) {
            return RequestSectionRenderer::renderHero($view->hero)
                . RequestSectionRenderer::renderTabs($view->tabs);
        }

        return self::renderOverview($view->hero, $routing->current)
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

        return RequestHeadersRenderer::render($request->entries, $response->entries);
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

    private static function renderMetric(string $label, string $value): Div
    {
        return Div::tag()
            ->class('yii-debug-request-overview-metric')
            ->html(
                Dt::tag()->content($label),
                Dd::tag()->title($value)->content($value),
            );
    }

    private static function renderOverview(RequestHero $hero, CurrentRouteView $current): string
    {
        $definition = $current->getDefinition();
        $route = $current->getRoute() !== '' ? $current->getRoute() : ($definition?->getName() ?? '');
        $action = $current->getAction() ?? $definition?->getAction() ?? '';
        $method = $hero->getMethod();
        $url = $hero->getUrl() !== '' ? $hero->getUrl() : 'URL unavailable';

        $identity = [];

        if ($method !== '') {
            $identity[] = Span::tag()
                ->class('yii-debug-request-hero-method yii-debug-verb-' . Vocabulary::verb($method))
                ->content($method);
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

        $error = $current->getError() === null || $current->getError() === ''
            ? ''
            : Div::tag()
                ->class('yii-debug-callout yii-debug-callout-danger yii-debug-request-routing-error')
                ->html(P::tag()->content($current->getError()));

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
                        self::renderMetric('Route', $route !== '' ? $route : 'Unresolved'),
                        self::renderMetric('Action', $action !== '' ? $action : 'Unavailable'),
                        self::renderMetric('Duration', $hero->getDurationMs() !== '' ? $hero->getDurationMs() : 'Unavailable'),
                    ),
                Div::tag()
                    ->class('yii-debug-request-overview-meta')
                    ->html(...$meta),
                $error,
            )
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

        return RequestServerRenderer::renderForRequest($tab->sections[0]->entries, $view);
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

        if ($routing->inventory !== null) {
            $tabs[] = [
                'label' => 'Routes (' . count($routing->inventory->getRoutes()) . ')',
                'content' => RequestRoutesRenderer::render($routing->current, $routing->inventory),
            ];
        }

        $server = self::tab($view->tabs, 'server');

        if ($server !== null) {
            $tabs[] = ['label' => 'Server', 'content' => self::renderServer($server, $view)];
        }

        return Div::tag()
            ->class('yii-debug-request-tabs')
            ->html(Tabs::render('request', 'Request data', $tabs))
            ->render();
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

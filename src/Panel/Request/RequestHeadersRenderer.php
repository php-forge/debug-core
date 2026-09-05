<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\{H2, H3};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;

use function count;

/**
 * Renders request and response headers as a directional HTTP exchange ledger.
 */
final class RequestHeadersRenderer
{
    /**
     * @param array<int|string, mixed> $request
     * @param array<int|string, mixed> $response
     */
    public static function render(array $request, array $response): string
    {
        $requestCount = count($request);
        $responseCount = count($response);

        $total = $requestCount + $responseCount;

        $heading = Div::tag()
            ->class('yii-debug-diagnostic-heading-copy')
            ->html(
                H2::tag()
                    ->id('yii-debug-header-exchange-title')
                    ->content('Header exchange'),
                Div::tag()
                    ->class('yii-debug-diagnostic-counts')
                    ->html(
                        self::renderCount($requestCount, 'inbound'),
                        self::renderCount($responseCount, 'outbound'),
                    ),
            );

        $headerChildren = [$heading];

        if ($total > 0) {
            $headerChildren[] = InputSearch::tag()
                ->addAriaAttribute('label', 'Filter request and response headers')
                ->addDataAttribute('yii-debug-filter', true)
                ->class('yii-debug-filter-input yii-debug-diagnostic-filter')
                ->placeholder('Filter headers…');
        }

        return Section::tag()
            ->addAriaAttribute('labelledby', 'yii-debug-header-exchange-title')
            ->class('yii-debug-diagnostic-shell yii-debug-header-exchange')
            ->html(
                Header::tag()
                    ->class('yii-debug-diagnostic-header')
                    ->html(...$headerChildren),
                Div::tag()
                    ->addDataAttribute('yii-debug-filter-target', true)
                    ->addDataAttribute('yii-debug-filter-unit', 'fields')
                    ->class('yii-debug-header-exchange-body')
                    ->html(
                        Div::tag()
                            ->class('yii-debug-header-lanes')
                            ->html(
                                self::renderLane(
                                    id: 'request',
                                    direction: 'Inbound',
                                    title: 'Request headers',
                                    entries: $request,
                                ),
                                self::renderLane(
                                    id: 'response',
                                    direction: 'Outbound',
                                    title: 'Response headers',
                                    entries: $response,
                                ),
                            ),
                        P::tag()
                            ->addDataAttribute('yii-debug-filter-empty', true)
                            ->addAttribute('hidden', true)
                            ->class('yii-debug-diagnostic-filter-empty')
                            ->content('No headers match this filter.'),
                    ),
            )
            ->render();
    }

    private static function renderCount(int $count, string $direction): Span
    {
        return Span::tag()
            ->class('yii-debug-diagnostic-count')
            ->html(
                Span::tag()
                    ->class('yii-debug-diagnostic-count-value')
                    ->content((string) $count),
                " {$direction}",
            );
    }

    /**
     * @param array<int|string, mixed> $entries
     */
    private static function renderLane(string $id, string $direction, string $title, array $entries): Section
    {
        $count = count($entries);

        $content = $entries === []
            ? P::tag()
                ->class('yii-debug-diagnostic-lane-empty')
                ->content($id === 'response' ? 'No response headers captured.' : 'No request headers captured.')
                ->render()
            : self::renderLedger($entries, $id === 'response');

        return Section::tag()
            ->addAriaAttribute('labelledby', "yii-debug-header-{$id}-title")
            ->addDataAttribute('yii-debug-filter-group', true)
            ->class("yii-debug-header-lane yii-debug-header-lane-{$id}")
            ->html(
                Header::tag()
                    ->class('yii-debug-header-lane-header')
                    ->html(
                        Div::tag()
                            ->html(
                                Span::tag()
                                    ->class('yii-debug-header-direction')
                                    ->content($direction),
                                H3::tag()
                                    ->id("yii-debug-header-{$id}-title")
                                    ->content($title),
                            ),
                        Span::tag()
                            ->class('yii-debug-header-lane-count')
                            ->content($count . ($count === 1 ? ' field' : ' fields')),
                    ),
                $content,
            );
    }

    /**
     * @param array<int|string, mixed> $entries
     */
    private static function renderLedger(array $entries, bool $response): string
    {
        $rows = [];

        foreach ($entries as $name => $value) {
            $label = is_int($name)
                ? ($response ? 'Raw response line ' : 'Raw header line ') . $name
                : $name;
            $class = is_int($name)
                ? 'yii-debug-diagnostic-row yii-debug-header-raw-row'
                : 'yii-debug-diagnostic-row';

            $rows[] = Div::tag()
                ->addDataAttribute('yii-debug-filter-row', true)
                ->class($class)
                ->html(
                    Dt::tag()->html(RequestDiagnosticValueRenderer::escape($label)),
                    Dd::tag()->html(RequestDiagnosticValueRenderer::header($value)),
                );
        }

        return Dl::tag()
            ->class('yii-debug-diagnostic-ledger yii-debug-header-ledger')
            ->html(...$rows)
            ->render();
    }
}

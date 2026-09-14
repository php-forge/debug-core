<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

use UIAwesome\Html\Flow\{Div, P};
use UIAwesome\Html\Form\InputSearch;
use UIAwesome\Html\Heading\{H2, H3};
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;

use function count;
use function is_int;

/**
 * Renders request and response headers as a directional HTTP exchange ledger.
 */
final class RequestHeadersRenderer
{
    /**
     * Renders the request and response headers side by side, with a shared filter above both lanes.
     *
     * @param array<int|string, mixed> $request Captured request headers, keyed by header name.
     * @param array<int|string, mixed> $response Captured response headers, keyed by header name.
     *
     * @return string Header exchange markup.
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
                    ->content(RequestMessage::HEADER_EXCHANGE),
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
                ->addAriaAttribute('label', RequestMessage::HEADERS_FILTER->value)
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
                                    direction: RequestMessage::INBOUND->value,
                                    title: RequestMessage::REQUEST_HEADERS_TITLE->value,
                                    entries: $request,
                                ),
                                self::renderLane(
                                    id: 'response',
                                    direction: RequestMessage::OUTBOUND->value,
                                    title: RequestMessage::RESPONSE_HEADERS_TITLE->value,
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

    /**
     * Renders the header count of one lane, annotated with its direction.
     *
     * @param int $count Number of headers captured in the lane.
     * @param string $direction Direction of the lane, shown after the count.
     *
     * @return Span Count element of the lane.
     */
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
     * Renders one direction of the exchange, replacing its ledger with a note when no header was captured.
     *
     * @param string $id Identifier of the lane, used to associate it with the filter.
     * @param string $direction Direction of the lane, shown next to its count.
     * @param string $title Heading of the lane.
     * @param array<int|string, mixed> $entries Captured headers of the lane, keyed by header name.
     *
     * @return Section Lane section carrying the heading, count, and ledger.
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
     * Renders the captured headers of one lane as a diagnostic ledger.
     *
     * @param array<int|string, mixed> $entries Captured headers, keyed by header name.
     * @param bool $response Whether the entries belong to the response lane, which labels its raw lines apart.
     *
     * @return string Ledger markup.
     */
    private static function renderLedger(array $entries, bool $response): string
    {
        $rows = [];

        foreach ($entries as $name => $value) {
            $label = is_int($name)
                ? ($response
                    ? RequestMessage::RAW_RESPONSE_LINE->value
                    : RequestMessage::RAW_HEADER_LINE->value) . $name
                : $name;
            $rows[] = RequestDiagnosticLedger::row(
                RequestDiagnosticValueRenderer::escape($label),
                RequestDiagnosticValueRenderer::header($value),
                is_int($name) ? 'yii-debug-header-raw-row' : '',
            );
        }

        return RequestDiagnosticLedger::render('yii-debug-header-ledger', ...$rows);
    }
}

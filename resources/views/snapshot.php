<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, Pre};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Phrasing\{Code, Small, Span, Strong};
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\Section;

/**
 * @var bool $ajax Whether the captured request was an AJAX request.
 * @var string $capturedAt Formatted capture timestamp.
 * @var string $clientIp Formatted client address.
 * @var string $duration Formatted request duration.
 * @var array{exception: string, stage: string}|null $failure Captured panel failure.
 * @var string $memory Formatted peak memory.
 * @var string $method HTTP request method.
 * @var string $methodVariant Semantic method class suffix.
 * @var string $panelLabel Selected panel label.
 * @var string|null $panelContent Trusted adapter panel markup or `null` to render the JSON fallback.
 * @var string $payload Formatted, encoded panel payload.
 * @var string|null $renderError Panel renderer error message or `null` when rendering succeeded.
 * @var int $statusCode HTTP response status.
 * @var string $statusVariant Semantic response status class suffix.
 * @var string $tag Captured request tag.
 * @var string $url Captured request URL.
 */
$requestMeta = Div::tag()
    ->class('yii-debug-request-overview-meta')
    ->html(
        Span::tag()
            ->class('yii-debug-request-overview-meta-item')
            ->html(
                Small::tag()->class('yii-debug-request-overview-meta-label')->content('Captured'),
                Span::tag()->content($capturedAt),
            ),
        Span::tag()
            ->class('yii-debug-request-overview-meta-item')
            ->html(
                Small::tag()->class('yii-debug-request-overview-meta-label')->content('Request ID'),
                Span::tag()->class('yii-debug-request-overview-tag')->content($tag)->title($tag),
            ),
    );

if ($ajax) {
    $requestMeta = $requestMeta->html(
        Span::tag()->class('yii-debug-request-overview-flag')->content('AJAX'),
    );
}

$requestMetrics = Dl::tag()
    ->class('yii-debug-request-overview-metrics')
    ->html(
        Div::tag()
            ->class('yii-debug-request-overview-metric')
            ->html(
                Dt::tag()->content('Duration'),
                Dd::tag()->content($duration),
            ),
        Div::tag()
            ->class('yii-debug-request-overview-metric')
            ->html(
                Dt::tag()->content('Peak memory'),
                Dd::tag()->content($memory),
            ),
        Div::tag()
            ->class('yii-debug-request-overview-metric')
            ->html(
                Dt::tag()->content('Client IP'),
                Dd::tag()->content($clientIp),
            ),
    );
$requestIdentity = Div::tag()
    ->class('yii-debug-request-overview-identity')
    ->html(
        Small::tag()->class('yii-debug-request-overview-label')->content('Captured request'),
        Div::tag()
            ->class('yii-debug-request-hero-line')
            ->html(
                Span::tag()
                    ->class('yii-debug-request-hero-method yii-debug-verb-' . $methodVariant)
                    ->content($method),
                Span::tag()->class('yii-debug-request-hero-url')->content($url)->title($url),
            ),
    );
$responseStatus = Div::tag()
    ->class('yii-debug-request-overview-status')
    ->html(
        Small::tag()->class('yii-debug-request-overview-status-label')->content('Response'),
        Strong::tag()
            ->class('yii-debug-request-overview-status-value yii-debug-snapshot-status yii-debug-status-' . $statusVariant)
            ->content((string) $statusCode),
    );
$overview = Section::tag()
    ->addAriaAttribute('label', 'Request overview')
    ->class('yii-debug-request-overview yii-debug-status-' . $statusVariant)
    ->html(
        Header::tag()
            ->class('yii-debug-request-overview-header')
            ->html($requestIdentity, $responseStatus),
        $requestMetrics,
        $requestMeta,
    );
$failureCallout = $failure === null
    ? ''
    : Div::tag()
        ->role('alert')
        ->class('yii-debug-callout yii-debug-callout-danger')
        ->html(
            Div::tag()->html(
                Strong::tag()->content('Panel ' . $failure['stage'] . ' failed.'),
                '<br>',
                Span::tag()->content($failure['exception']),
            ),
        );
$renderCallout = $renderError === null
    ? ''
    : Div::tag()
        ->role('alert')
        ->class('yii-debug-callout yii-debug-callout-danger')
        ->html(
            Div::tag()->html(
                Strong::tag()->content('Panel rendering failed.'),
                '<br>',
                Span::tag()->content($renderError),
            ),
        );
$panelHeader = Header::tag()
    ->class('yii-debug-panel-heading')
    ->html(
        Div::tag()
            ->class('yii-debug-panel-heading-copy')
            ->html(
                Small::tag()->class('yii-debug-panel-heading-eyebrow')->content('Selected panel'),
                H2::tag()->id('yii-debug-panel-title')->content($panelLabel),
            ),
        Span::tag()
            ->class('yii-debug-panel-heading-kind')
            ->content($panelContent === null ? 'JSON snapshot' : 'Rendered view'),
    );
$panelBody = $panelContent ?? Pre::tag()
    ->class('yii-debug-panel-payload')
    ->html(Code::tag()->content($payload))
    ->render();
?>
<?= H1::tag()
    ->class('yii-debug-sr-only')
    ->content($method . ' ' . $url)
    ->render()
?>
<?= $overview->render() ?>
<?= $panelHeader->render() ?>
<?= $failureCallout ?>
<?= $renderCallout ?>
<?= $panelBody;

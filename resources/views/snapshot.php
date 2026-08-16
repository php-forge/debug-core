<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, Pre};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Code, Span, Strong};
use UIAwesome\Html\Root\Header;

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
 * @var string $payload Formatted, encoded panel payload.
 * @var int $statusCode HTTP response status.
 * @var string $statusVariant Semantic response status class suffix.
 * @var string $tag Captured request tag.
 * @var string $url Captured request URL.
 */

$requestMeta = Div::tag()
    ->class('yii-debug-request-hero-meta')
    ->html(
        Span::tag()->content($capturedAt),
        Span::tag()->content($clientIp),
    );

if ($ajax) {
    $requestMeta = $requestMeta->html(Span::tag()->content('AJAX'));
}

$requestMeta = $requestMeta->html(Span::tag()->content($tag));
$hero = Div::tag()
    ->class('yii-debug-request-hero')
    ->html(
        Div::tag()
            ->class('yii-debug-request-hero-line')
            ->html(
                Span::tag()
                    ->class('yii-debug-request-hero-method yii-debug-verb-' . $methodVariant)
                    ->content($method),
                Span::tag()->class('yii-debug-request-hero-url')->content($url)->title($url),
                Span::tag()
                    ->class('yii-debug-snapshot-status yii-debug-status-' . $statusVariant)
                    ->content((string) $statusCode),
            ),
        $requestMeta,
    );
$summary = Header::tag()
    ->class('yii-debug-grid-summary')
    ->html(
        Span::tag()->html(Strong::tag()->content($method), ' Method'),
        Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
        Span::tag()->html(Strong::tag()->content((string) $statusCode), ' Status'),
        Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
        Span::tag()->html(Strong::tag()->content($duration), ' Duration'),
        Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
        Span::tag()->html(Strong::tag()->content($memory), ' Peak memory'),
        Span::tag()->class('yii-debug-grid-summary-sep')->content('·'),
        Span::tag()->html(Strong::tag()->content($clientIp), ' Client IP'),
    );
$failureCallout = $failure === null
    ? ''
    : Div::tag()
        ->class('yii-debug-callout yii-debug-callout-danger')
        ->html(
            Div::tag()->html(
                Strong::tag()->content('Panel ' . $failure['stage'] . ' failed.'),
                '<br>',
                Span::tag()->content($failure['exception']),
            ),
        );
?>
<?= H1::tag()->class('yii-debug-sr-only')->content($method . ' ' . $url)->render() ?>
<?= $hero->render() ?>
<?= $summary->render() ?>
<?= H2::tag()->content($panelLabel)->render() ?>
<?= Pre::tag()->html(Code::tag()->content($payload))->render() ?>
<?= $failureCallout;

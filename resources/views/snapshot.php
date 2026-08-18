<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\{Div, Pre};
use UIAwesome\Html\Heading\{H1, H2};
use UIAwesome\Html\Phrasing\{Code, Small, Span, Strong};
use UIAwesome\Html\Root\Header;

/**
 * @var array{exception: string, stage: string}|null $failure Captured panel failure.
 * @var string $method HTTP request method.
 * @var string $panelLabel Selected panel label.
 * @var string|null $panelContent Trusted adapter panel markup or `null` to render the JSON fallback.
 * @var string $payload Formatted, encoded panel payload.
 * @var string|null $renderError Panel renderer error message or `null` when rendering succeeded.
 * @var string $url Captured request URL.
 */
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
$panelHeader = $panelContent !== null
    ? ''
    : Header::tag()
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
                ->content('JSON snapshot'),
        )
        ->render();
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
<?= $panelHeader ?>
<?= $failureCallout ?>
<?= $renderCallout ?>
<?= $panelBody;

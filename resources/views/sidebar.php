<?php

declare(strict_types=1);

use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\List\{Li, Ul};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Sectioning\{Aside, Nav, Section};

/**
 * @var array{
 *     ajax: bool,
 *     method: string,
 *     methodVariant: string,
 *     statusCode: int|null,
 *     statusVariant: string,
 *     time: string,
 *     title: string,
 *     url: string,
 * } $snapshot Sidebar snapshot card data.
 * @var list<array{active: bool, icon: string, label: string, url: string}> $navigation Panel navigation data.
 */

$snapshotMeta = Div::tag()->class('yii-debug-snapshot-meta');

if ($snapshot['statusCode'] !== null) {
    $snapshotMeta = $snapshotMeta->html(
        Span::tag()
            ->class('yii-debug-snapshot-status yii-debug-status-' . $snapshot['statusVariant'])
            ->content((string) $snapshot['statusCode']),
    );
}

if ($snapshot['time'] !== '') {
    $snapshotMeta = $snapshotMeta->html(Span::tag()->content($snapshot['time']));
}

if ($snapshot['ajax']) {
    $snapshotMeta = $snapshotMeta->html(Span::tag()->class('yii-debug-snapshot-tag')->content('AJAX'));
}

$snapshotCard = Div::tag()
    ->class('yii-debug-history-card')
    ->html(
        Div::tag()
            ->class('yii-debug-snapshot-line')
            ->html(
                Span::tag()
                    ->class('yii-debug-snapshot-method yii-debug-verb-' . $snapshot['methodVariant'])
                    ->content($snapshot['method']),
                Span::tag()->class('yii-debug-snapshot-url')->content($snapshot['url']),
            ),
        $snapshotMeta,
    )
    ->title(trim($snapshot['method'] . ' ' . $snapshot['url']));
$navigationList = Ul::tag();

foreach ($navigation as $item) {
    $link = A::tag()
        ->addAriaAttribute('current', $item['active'] ? 'page' : null)
        ->class('yii-debug-nav-link' . ($item['active'] ? ' is-active' : ''))
        ->href($item['url'])
        ->html(
            Span::tag()
                ->addAriaAttribute('hidden', 'true')
                ->class('yii-debug-nav-link-icon')
                ->html($item['icon']),
            Span::tag()->class('yii-debug-nav-link-label')->content($item['label']),
        );
    $navigationList = $navigationList->html(Li::tag()->html($link));
}

$sidebar = Aside::tag()
    ->class('yii-debug-sidebar')
    ->html(
        Section::tag()
            ->class('yii-debug-side-section')
            ->html(
                Span::tag()->class('yii-debug-side-section-title')->content($snapshot['title']),
                $snapshotCard,
            ),
        Nav::tag()
            ->addAriaAttribute('label', 'Debugger panels')
            ->class('yii-debug-nav yii-debug-nav-iconed')
            ->html($navigationList),
    );
?>
<?= $sidebar->render();

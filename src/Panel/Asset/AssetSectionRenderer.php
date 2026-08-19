<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

use PHPForge\Debug\Helper\Icon;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\H1;
use UIAwesome\Html\List\{Li, Ol};
use UIAwesome\Html\Phrasing\{Span, Strong};
use UIAwesome\Html\Root\Header;

/**
 * Renders the shared Asset Bundles heading, aggregate statistics, and normalized bundle inventory.
 */
final class AssetSectionRenderer
{
    /**
     * Renders the accessible panel heading and aggregate bundle, CSS, JavaScript, and dependency statistics.
     */
    public static function renderHeader(AssetSummary $summary): string
    {
        $stats = [
            ['bundles', 'asset', $summary->totalBundles, 'bundle' . ($summary->totalBundles === 1 ? '' : 's')],
            ['css', 'brand-css3', $summary->totalCss, 'css'],
            ['js', 'brand-javascript', $summary->totalJs, 'js'],
            ['deps', 'link', $summary->totalDeps, 'link' . ($summary->totalDeps === 1 ? '' : 's')],
        ];
        $blocks = [];

        foreach ($stats as [$kind, $icon, $value, $label]) {
            $blocks[] = Div::tag()
                ->addDataAttribute('kind', $kind)
                ->class('yii-debug-asset-stat')
                ->html(
                    Span::tag()
                        ->addAriaAttribute('hidden', 'true')
                        ->class('yii-debug-asset-stat-icon')
                        ->html(Icon::render($icon)),
                    Strong::tag()
                        ->class('yii-debug-asset-stat-value')
                        ->content((string) $value),
                    Span::tag()
                        ->class('yii-debug-asset-stat-label')
                        ->content($label),
                );
        }

        return H1::tag()
            ->class('yii-debug-sr-only')
            ->content('Asset Bundles')
            ->render()
            . Header::tag()
                ->class('yii-debug-asset-stats')
                ->html(...$blocks)
                ->render();
    }

    /**
     * Renders the normalized bundle inventory as an ordered list of shared bundle cards.
     */
    public static function renderInventory(AssetSummary $summary): string
    {
        if ($summary->isEmpty()) {
            return '';
        }

        $items = [];

        foreach ($summary->bundles as $bundle) {
            $items[] = Li::tag()
                ->class('yii-debug-asset-list-item')
                ->html(AssetCardRenderer::renderCard($bundle, $summary));
        }

        return Ol::tag()
            ->class('yii-debug-asset-list')
            ->html(...$items)
            ->render();
    }
}

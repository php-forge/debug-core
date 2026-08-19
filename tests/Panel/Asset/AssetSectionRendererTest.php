<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Asset;

use PHPForge\Debug\Helper\Icon;
use PHPForge\Debug\Panel\Asset\{AssetBundleNormalizer, AssetBundleRow, AssetSectionRenderer};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AssetSectionRenderer} covering shared statistics and inventory structure.
 */
#[Group('asset')]
#[Group('panel')]
final class AssetSectionRendererTest extends TestCase
{
    public function testRenderHeaderIncludesAccessibleTitleAndAllStatistics(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(self::headerRows());

        $assetIcon = Icon::render('asset');
        $cssIcon = Icon::render('brand-css3');
        $jsIcon = Icon::render('brand-javascript');
        $depsIcon = Icon::render('link');

        self::assertSame(
            <<<HTML
            <h1 class="yii-debug-sr-only">
            Asset Bundles
            </h1><header class="yii-debug-asset-stats">
            <div class="yii-debug-asset-stat" data-kind="bundles">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">{$assetIcon}</span><strong class="yii-debug-asset-stat-value">2</strong><span class="yii-debug-asset-stat-label">bundles</span>
            </div><div class="yii-debug-asset-stat" data-kind="css">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">{$cssIcon}</span><strong class="yii-debug-asset-stat-value">3</strong><span class="yii-debug-asset-stat-label">css</span>
            </div><div class="yii-debug-asset-stat" data-kind="js">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">{$jsIcon}</span><strong class="yii-debug-asset-stat-value">2</strong><span class="yii-debug-asset-stat-label">js</span>
            </div><div class="yii-debug-asset-stat" data-kind="deps">
            <span class="yii-debug-asset-stat-icon" aria-hidden="true">{$depsIcon}</span><strong class="yii-debug-asset-stat-value">2</strong><span class="yii-debug-asset-stat-label">links</span>
            </div>
            </header>
            HTML,
            AssetSectionRenderer::renderHeader($summary),
            'The header must render the accessible title and exact aggregate statistics.',
        );
    }

    public function testRenderInventoryReturnsEmptyStringWithoutBundles(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize([]);

        self::assertSame(
            '',
            AssetSectionRenderer::renderInventory($summary),
            'Empty inventories render no list.',
        );
    }

    public function testRenderInventoryUsesOrderedListAndSharedCards(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(self::inventoryRows());

        $assetIcon = Icon::render('asset');

        self::assertSame(
            <<<HTML
            <ol class="yii-debug-asset-list">
            <li class="yii-debug-asset-list-item">
            <article class="yii-debug-asset-card" id="app\assets\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true">{$assetIcon}</span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\assets\</span>
            </div><div class="yii-debug-asset-card-meta">
            </div>
            </header>
            </article>
            </li><li class="yii-debug-asset-list-item">
            <article class="yii-debug-asset-card" id="vendor\assets\-vendor-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true">{$assetIcon}</span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            VendorAsset
            </h2><span class="yii-debug-asset-card-fqcn">vendor\assets\</span>
            </div><div class="yii-debug-asset-card-meta">
            </div>
            </header>
            </article>
            </li>
            </ol>
            HTML,
            AssetSectionRenderer::renderInventory($summary),
            'Inventory must render exactly two cards in registration order inside an ordered list.',
        );
    }

    /**
     * @return list<AssetBundleRow>
     */
    private static function headerRows(): array
    {
        return [
            new AssetBundleRow(
                'app\\assets\\AppAsset',
                '',
                '',
                '',
                ['site.css'],
                ['site.js'],
                ['yii\\web\\YiiAsset'],
            ),
            new AssetBundleRow(
                'vendor\\assets\\VendorAsset',
                '',
                '',
                '',
                ['vendor.css', 'theme.css'],
                ['vendor.js'],
                ['vendor\\base\\BaseAsset'],
            ),
        ];
    }

    /**
     * @return list<AssetBundleRow>
     */
    private static function inventoryRows(): array
    {
        return [
            new AssetBundleRow('app\\assets\\AppAsset', '', '', '', [], [], []),
            new AssetBundleRow('vendor\\assets\\VendorAsset', '', '', '', [], [], []),
        ];
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Asset;

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
        $summary = (new AssetBundleNormalizer())->normalize(self::rows());

        $html = AssetSectionRenderer::renderHeader($summary);

        self::assertStringContainsString(
            'yii-debug-sr-only',
            $html,
            'The panel title must remain accessible.',
        );
        self::assertStringContainsString(
            'yii-debug-asset-stats',
            $html,
            'The statistics strip must render.',
        );
        self::assertStringContainsString(
            'data-kind="bundles"',
            $html,
            'Bundle count must render.',
        );
        self::assertStringContainsString(
            'data-kind="css"',
            $html,
            'CSS count must render.',
        );
        self::assertStringContainsString(
            'data-kind="js"',
            $html,
            'JavaScript count must render.',
        );
        self::assertStringContainsString(
            'data-kind="deps"',
            $html,
            'Dependency count must render.',
        );
    }

    public function testRenderInventoryReturnsEmptyStringWithoutBundles(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize([]);

        self::assertSame(
            '',
            AssetSectionRenderer::renderInventory($summary),
            'Empty inventories render no list.',
        );
    }

    public function testRenderInventoryUsesOrderedListAndSharedCards(): void
    {
        $summary = (new AssetBundleNormalizer())->normalize(self::rows());

        $html = AssetSectionRenderer::renderInventory($summary);

        self::assertStringContainsString(
            'yii-debug-asset-list',
            $html,
            'Inventory must use the shared list class.',
        );
        self::assertStringContainsString(
            'yii-debug-asset-list-item',
            $html,
            'Each bundle must use a list item.',
        );
        self::assertStringContainsString(
            'yii-debug-asset-card',
            $html,
            'Each bundle must render as a shared card.',
        );
    }

    /**
     * @return list<AssetBundleRow>
     */
    private static function rows(): array
    {
        return [
            new AssetBundleRow(
                'app\\assets\\AppAsset',
                '',
                '/assets',
                '/assets',
                ['site.css'],
                ['site.js'],
                ['yii\\web\\YiiAsset'],
            ),
        ];
    }
}

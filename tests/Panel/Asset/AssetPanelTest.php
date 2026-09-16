<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Asset;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\Asset\{AssetPanel, AssetSnapshot};
use PHPForge\Debug\Presenter\{
    BadgeInline,
    EmptyStateBlock,
    FactEntry,
    FileEntry,
    LinkInline,
    ParagraphBlock,
    StatEntry,
    ToolbarMetric,
};
use PHPForge\Debug\Tests\Support\PanelViewAccessors;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see AssetPanel} covering the aggregate statistics, the Vite section, and the per-bundle cards.
 */
#[Group('panel')]
#[Group('asset')]
final class AssetPanelTest extends TestCase
{
    use PanelViewAccessors;

    public function testBundleWithoutFilesWiringOrDependenciesRendersABodylessCard(): void
    {
        $card = self::card(
            self::blockAt(
                self::present(
                    [
                        self::bundle(
                            [
                                'name' => 'app\\assets\\EmptyAsset',
                                'sourcePath' => '',
                                'basePath' => '',
                                'baseUrl' => '',
                                'css' => [],
                                'js' => [],
                                'depends' => [],
                            ],
                        ),
                    ],
                ),
                1,
            ),
        );

        self::assertSame(
            'app-assets-emptyasset-e3777dff',
            $card->id,
            'Anchor must survive an empty capture.',
        );
        self::assertSame(
            [],
            $card->meta,
            'Nothing declared means no count chip.',
        );
        self::assertSame(
            [],
            $card->columns,
            'Nothing declared means no body.',
        );
    }

    public function testEmptyCaptureDeactivatesThePanelAndExplainsTheMissingBundles(): void
    {
        $view = self::present([]);

        self::assertFalse(
            $view->isActive(),
            'An empty inventory must not activate navigation.',
        );
        self::assertEquals(
            [
                new StatEntry('asset', 'bundles', '0', Tone::MUTED),
                new StatEntry('brand-css3', 'css', '0', Tone::INFO),
                new StatEntry('brand-javascript', 'js', '0', Tone::WARNING),
                new StatEntry('link', 'links', '0', Tone::SUCCESS),
            ],
            self::stats(self::blockAt($view, 0))->stats,
            'The aggregate tiles must stay visible for an empty inventory.',
        );

        $state = self::emptyState(self::blockAt($view, 1));

        self::assertCount(
            2,
            $view->blocks(),
            'An empty inventory must replace every card.',
        );
        self::assertSame(
            'No asset bundles loaded',
            $state->title,
            'The empty state must keep its heading.',
        );
        self::assertSame(
            [
                'This request did not register any ',
                'yii\\web\\AssetBundle',
                ' via ',
                'register()',
                ', so the inventory is empty.',
            ],
            self::inlineValues($state->paragraphs[0] ?? self::fail('The empty state must explain itself.')),
            'The first paragraph must stay complete and ordered.',
        );
        self::assertSame(
            [
                'Bundles appear here when something in the request actively pulls them in, typically a layout or ',
                'view that calls a bundle\'s ',
                'register()',
                ', or any bundle reached transitively through the ',
                'depends',
                ' chain.',
            ],
            self::inlineValues($state->paragraphs[1] ?? self::fail('The empty state must explain the chain.')),
            'The second paragraph must stay complete and ordered.',
        );
    }

    public function testGlobalNamespaceBundleDropsTheSubtitleAndAnchorsOnItsBareClassName(): void
    {
        $card = self::card(
            self::blockAt(
                self::present([self::bundle(['name' => 'GlobalAsset', 'js' => [], 'depends' => []])]),
                1,
            ),
        );

        self::assertSame(
            'globalasset-762af4c5',
            $card->id,
            'Anchor must slug the class name alone.',
        );
        self::assertSame(
            'GlobalAsset',
            $card->title,
            'Title must stay the class name.',
        );
        self::assertSame(
            '',
            $card->subtitle,
            'A class outside any namespace must carry no qualifier.',
        );
    }

    public function testMetadataMatchesTheBuiltInAssetPanel(): void
    {
        $panel = new AssetPanel();

        self::assertSame(
            'asset',
            $panel->id(),
            'The persisted panel identifier must stay stable.',
        );
        self::assertSame(
            'Asset Bundles',
            $panel->name(),
            'The navigation title must stay stable.',
        );
        self::assertSame(
            'asset',
            $panel->icon(),
            'The panel must reuse the existing icon.',
        );
    }

    public function testRegisteredBundlesProduceTheirCardsWithFilesAndWiringColumns(): void
    {
        $view = self::present(
            [
                self::bundle(['depends' => ['yii\\web\\YiiAsset', 'yii\\web\\JqueryAsset']]),
                self::bundle(
                    [
                        'name' => 'yii\\web\\YiiAsset',
                        'sourcePath' => '',
                        'basePath' => '',
                        'baseUrl' => '',
                        'css' => [],
                        'js' => ['yii.js'],
                        'depends' => [],
                    ],
                ),
                self::bundle(
                    [
                        'name' => 'yii\\web\\JqueryAsset',
                        'sourcePath' => '',
                        'basePath' => '',
                        'baseUrl' => '',
                        'css' => [],
                        'js' => ['jquery.js'],
                        'depends' => [],
                    ],
                ),
            ],
        );

        self::assertTrue(
            $view->isActive(),
            'A captured bundle must activate navigation.',
        );
        self::assertEquals(
            [
                new StatEntry('asset', 'bundles', '3', Tone::MUTED),
                new StatEntry('brand-css3', 'css', '1', Tone::INFO),
                new StatEntry('brand-javascript', 'js', '4', Tone::WARNING),
                new StatEntry('link', 'links', '2', Tone::SUCCESS),
            ],
            self::stats(self::blockAt($view, 0))->stats,
            'The aggregate tiles must total every bundle.',
        );
        self::assertEquals(
            [new ToolbarMetric('Bundles', '3')],
            $view->toolbarMetrics(),
            'The toolbar must report the bundle count.',
        );

        $card = self::card(self::blockAt($view, 1));

        self::assertSame(
            'app-assets-appasset-3c6a8113',
            $card->id,
            'Anchor must derive from the bundle class.',
        );
        self::assertSame(
            'asset',
            $card->icon,
            'Cards must reuse the panel icon.',
        );
        self::assertSame(
            'AppAsset',
            $card->title,
            'Title must shorten the class name.',
        );
        self::assertSame(
            'app\\assets\\',
            $card->subtitle,
            'Qualifier must keep its trailing separator.',
        );
        self::assertEquals(
            [
                new BadgeInline('1 css', Tone::INFO),
                new BadgeInline('2 js', Tone::WARNING),
                new BadgeInline('2 deps', Tone::SUCCESS),
            ],
            $card->meta,
            'Chips must count stylesheets, scripts, then dependencies.',
        );

        $files = self::column($card, 0);

        self::assertSame(
            'Files',
            $files->title,
            'The declared files must open the body.',
        );
        self::assertEquals(
            [new FileEntry('.css', 'css/site.css', Tone::INFO)],
            self::files(self::columnBlockAt($files, 0))->files,
            'Stylesheets must fill the first list.',
        );
        self::assertEquals(
            [
                new FileEntry('.js', 'js/app.js', Tone::WARNING),
                new FileEntry('.js', 'js/extra.js', Tone::WARNING),
            ],
            self::files(self::columnBlockAt($files, 1))->files,
            'Scripts must follow in a list of their own.',
        );

        $wiring = self::column($card, 1);

        self::assertSame(
            'Wiring',
            $wiring->title,
            'The resolution detail must close the body.',
        );
        self::assertEquals(
            [
                new FactEntry('source', '@app/assets'),
                new FactEntry('base', '@webroot/assets/1a2b'),
                new FactEntry('url', '/assets/1a2b'),
            ],
            self::facts(self::columnBlockAt($wiring, 0))->facts,
            'Order: source, base, then URL.',
        );

        $depends = self::links(self::columnBlockAt($wiring, 1));

        self::assertSame(
            'Depends on 2',
            $depends->label,
            'The strip must count what it lists.',
        );

        $yii = self::card(self::blockAt($view, 2));
        $jquery = self::card(self::blockAt($view, 3));

        self::assertSame(
            ['yii-web-yiiasset-7afeb318', 'yii-web-jqueryasset-2772d8b9'],
            [$yii->id, $jquery->id],
            'Every dependency must own a URL-safe card anchor.',
        );
        self::assertEquals(
            [
                new LinkInline('YiiAsset', "#{$yii->id}", false),
                new LinkInline('JqueryAsset', "#{$jquery->id}", false),
            ],
            $depends->links,
            'Each dependency must point at the anchor of its own card.',
        );
        self::assertEquals(
            [new BadgeInline('1 js', Tone::WARNING)],
            $yii->meta,
            'An absent kind must leave no chip behind.',
        );
        self::assertCount(
            1,
            $yii->columns,
            'A bundle that publishes nothing must keep only its files.',
        );
        self::assertEquals(
            [new FileEntry('.js', 'yii.js', Tone::WARNING)],
            self::files(self::columnBlockAt(self::column($yii, 0), 0))->files,
            'Scripts alone must still fill the first list.',
        );
    }

    public function testSingleBundleAndDependencyUseTheSingularLabels(): void
    {
        $view = self::present(
            [
                self::bundle(
                    [
                        'name' => 'only\\OnlyAsset',
                        'basePath' => '',
                        'baseUrl' => '',
                        'js' => [],
                        'depends' => ['x\\OneAsset'],
                    ],
                ),
            ],
        );

        self::assertEquals(
            [
                new StatEntry('asset', 'bundle', '1', Tone::MUTED),
                new StatEntry('brand-css3', 'css', '1', Tone::INFO),
                new StatEntry('brand-javascript', 'js', '0', Tone::WARNING),
                new StatEntry('link', 'link', '1', Tone::SUCCESS),
            ],
            self::stats(self::blockAt($view, 0))->stats,
            'A lone bundle and a lone dependency must read in the singular.',
        );

        $card = self::card(self::blockAt($view, 1));

        self::assertEquals(
            [
                new BadgeInline('1 css', Tone::INFO),
                new BadgeInline('1 dep', Tone::SUCCESS),
            ],
            $card->meta,
            'A lone dependency must read in the singular.',
        );
        self::assertCount(
            1,
            self::column($card, 0)->content->blocks(),
            'Stylesheets alone must fill a single list.',
        );
        self::assertEquals(
            [new FactEntry('source', '@app/assets')],
            self::facts(self::columnBlockAt(self::column($card, 1), 0))->facts,
            'An unpublished bundle must contribute no base or URL row.',
        );
    }

    public function testViteBridgeIsDescribedBeforeTheBundles(): void
    {
        $view = self::present(
            [],
            [
                'baseUrl' => '/build/',
                'devMode' => false,
                'devServerUrl' => null,
                'manifestPath' => '/app/public/build/manifest.json',
                'chunks' => [
                    [
                        'name' => 'resources/js/app.js',
                        'file' => 'assets/app-1a2b.js',
                        'cssCount' => 2,
                        'imports' => 1,
                        'isEntry' => true,
                    ],
                    [
                        'name' => 'resources/js/vendor.js',
                        'file' => '',
                        'cssCount' => 0,
                        'imports' => 0,
                        'isEntry' => false,
                    ],
                ],
            ],
        );

        self::assertTrue(
            $view->isActive(),
            'A captured Vite bridge must activate navigation even without bundles.',
        );

        $viteHeading = self::heading(self::blockAt($view, 1));

        self::assertSame(
            'Vite',
            $viteHeading->title,
            'The Vite section must follow the aggregate tiles.',
        );
        self::assertTrue(
            $viteHeading->section,
            'The Vite section must open a section-level heading.',
        );

        $bridge = self::overview(self::blockAt($view, 2));

        self::assertTrue(
            $bridge->compact,
            'The bridge configuration must use the compact presentation.',
        );
        self::assertSame(
            ['Mode' => 'Build manifest', 'Base URL' => '/build/', 'Manifest' => '/app/public/build/manifest.json'],
            self::textFields($bridge),
            'The bridge configuration must survive the migration.',
        );

        $chunks = self::table(self::blockAt($view, 3));

        self::assertSame(
            ['#', 'Chunk', 'Output', 'CSS', 'Imports', 'Entry'],
            $chunks->headers,
            'The chunk column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::MONOSPACE,
                2 => ColumnStyle::MONOSPACE,
                3 => ColumnStyle::NUMBER,
                4 => ColumnStyle::NUMBER,
                5 => ColumnStyle::PILL,
            ],
            $chunks->styles,
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $chunks->collapsible,
            'A long chunk list must stay collapsible.',
        );
        self::assertSame(
            'entry',
            self::badge($chunks->rows[0][5] ?? self::fail('Every chunk must report its entry state.'))->label,
            'An entry chunk must be badged.',
        );
        self::assertSame(
            ['2', 'resources/js/vendor.js', '—', '0', '0', '—'],
            self::textValues($chunks->rows[1] ?? self::fail('Every chunk must be listed.')),
            'A chunk without output must show the placeholder.',
        );
    }

    public function testViteBuildWithoutChunksExplainsTheMissingManifest(): void
    {
        $view = self::present([], self::vite(['devMode' => false]));

        self::assertInstanceOf(
            ParagraphBlock::class,
            self::blockAt($view, 3),
            'A build without chunks must explain the missing manifest.',
        );
    }

    public function testViteDevServerModeNeedsNoManifestExplanation(): void
    {
        $view = self::present([], self::vite(['devMode' => true, 'devServerUrl' => 'http://localhost:5173']));

        self::assertSame(
            'Dev server (http://localhost:5173)',
            self::textFields(self::overview(self::blockAt($view, 2)))['Mode'] ?? null,
            'The dev server URL must stay visible.',
        );
        self::assertInstanceOf(
            EmptyStateBlock::class,
            self::blockAt($view, 3),
            'A dev server without chunks must not explain a missing manifest.',
        );
    }

    public function testViteDevServerWithoutUrlKeepsTheBareMode(): void
    {
        $view = self::present([], self::vite(['devMode' => true]));

        self::assertSame(
            'Dev server',
            self::textFields(self::overview(self::blockAt($view, 2)))['Mode'] ?? null,
            'A dev server without URL must keep the bare mode label.',
        );
    }

    public function testWiringColumnAdaptsToTheCapturedPathsAndDependencies(): void
    {
        $view = self::present(
            [
                self::bundle(
                    [
                        'name' => 'dep\\OnlyAsset',
                        'sourcePath' => '',
                        'basePath' => '',
                        'baseUrl' => '',
                        'css' => [],
                        'js' => [],
                        'depends' => ['yii\\web\\YiiAsset'],
                    ],
                ),
                self::bundle(
                    [
                        'name' => 'path\\OnlyAsset',
                        'sourcePath' => '',
                        'css' => [],
                        'js' => [],
                        'depends' => [],
                    ],
                ),
            ],
        );

        $dependsOnly = self::column(self::card(self::blockAt($view, 1)), 0);

        self::assertSame(
            'Wiring',
            $dependsOnly->title,
            'Dependencies alone must still open the column.',
        );
        self::assertCount(
            1,
            $dependsOnly->content->blocks(),
            'An unpublished bundle must contribute no fact strip.',
        );
        self::assertSame(
            'Depends on 1',
            self::links(self::columnBlockAt($dependsOnly, 0))->label,
            'The strip must count what it lists.',
        );

        $pathsOnly = self::column(self::card(self::blockAt($view, 2)), 0);

        self::assertCount(
            1,
            $pathsOnly->content->blocks(),
            'A bundle without dependencies must contribute no link strip.',
        );
        self::assertEquals(
            [
                new FactEntry('base', '@webroot/assets/1a2b'),
                new FactEntry('url', '/assets/1a2b'),
            ],
            self::facts(self::columnBlockAt($pathsOnly, 0))->facts,
            'An undeclared source path must contribute no row.',
        );
    }

    /**
     * Builds a capture payload for one bundle, overriding the requested fields.
     *
     * @param array<string, mixed> $overrides Capture fields replacing the defaults.
     *
     * @return array<string, mixed> Captured bundle.
     */
    private static function bundle(array $overrides = []): array
    {
        return [
            'name' => 'app\\assets\\AppAsset',
            'sourcePath' => '@app/assets',
            'basePath' => '@webroot/assets/1a2b',
            'baseUrl' => '/assets/1a2b',
            'css' => ['css/site.css'],
            'js' => ['js/app.js', 'js/extra.js'],
            'depends' => ['yii\\web\\YiiAsset'],
            ...$overrides,
        ];
    }

    /**
     * Presents the given capture through the panel under test.
     *
     * @param list<array<string, mixed>> $bundles Captured bundles in registration order.
     * @param array<string, mixed>|null $vite Captured Vite bridge snapshot, or `null` when absent.
     *
     * @return PanelView Description built by the panel.
     */
    private static function present(array $bundles, array|null $vite = null): PanelView
    {
        return (new AssetPanel())->present(
            AssetSnapshot::fromArray(['bundles' => $bundles, 'vite' => $vite], '$')->jsonSerialize(),
        );
    }

    /**
     * Builds a Vite capture without chunks, overriding the requested fields.
     *
     * @param array<string, mixed> $overrides Capture fields replacing the defaults.
     *
     * @return array<string, mixed> Captured Vite bridge snapshot.
     */
    private static function vite(array $overrides = []): array
    {
        return [
            'baseUrl' => '',
            'devMode' => false,
            'devServerUrl' => null,
            'manifestPath' => '',
            'chunks' => [],
            ...$overrides,
        ];
    }
}

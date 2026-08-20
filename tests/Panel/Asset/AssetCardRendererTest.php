<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Asset;

use PHPForge\Debug\Panel\Asset\{AssetBundleNormalizer, AssetBundleRow, AssetCardRenderer};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function is_array;
use function is_string;

/**
 * Unit tests for {@see AssetCardRenderer} covering anchor resolution, chip pluralization, and the rendered article
 * structure for representative bundle states.
 */
#[Group('panel')]
#[Group('asset')]
final class AssetCardRendererTest extends TestCase
{
    public function testRenderCardEmitsArticleWithBundleAnchorId(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\AppAsset' => ['css' => ['style.css']]]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            <span class="yii-debug-asset-chip yii-debug-asset-chip-css"><strong>1</strong> css</span>
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Files
            </h3><div class="yii-debug-asset-files">
            <div class="yii-debug-asset-file">
            <span class="yii-debug-asset-file-type yii-debug-asset-file-type-css">.css</span><span class="yii-debug-asset-file-name" title="style.css">style.css</span>
            </div>
            </div>
            </section>
            </div>
            </article>
            HTML,
            $html,
            'Card must carry the wrapper class.',
        );

    }

    public function testRenderCardEmitsCssFilesListAndChipForCssOnlyBundle(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\AppAsset' => ['css' => ['app.css']]]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            <span class="yii-debug-asset-chip yii-debug-asset-chip-css"><strong>1</strong> css</span>
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Files
            </h3><div class="yii-debug-asset-files">
            <div class="yii-debug-asset-file">
            <span class="yii-debug-asset-file-type yii-debug-asset-file-type-css">.css</span><span class="yii-debug-asset-file-name" title="app.css">app.css</span>
            </div>
            </div>
            </section>
            </div>
            </article>
            HTML,
            $html,
            "CSS-only bundle must render the 'css' chip.",
        );


    }

    public function testRenderCardEmitsJsFilesListAndChipForJsOnlyBundle(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\AppAsset' => ['js' => ['app.js']]]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            <span class="yii-debug-asset-chip yii-debug-asset-chip-js"><strong>1</strong> js</span>
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Files
            </h3><div class="yii-debug-asset-files">
            <div class="yii-debug-asset-file">
            <span class="yii-debug-asset-file-type yii-debug-asset-file-type-js">.js</span><span class="yii-debug-asset-file-name" title="app.js">app.js</span>
            </div>
            </div>
            </section>
            </div>
            </article>
            HTML,
            $html,
            "JS-only bundle must render the 'js' chip.",
        );


    }

    public function testRenderCardEmitsPluralChipForMultipleDependencies(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(
                    [
                        'app\\AppAsset' => [
                            'depends' => [
                                'app\\A',
                                'app\\B',
                                'app\\C',
                            ],
                        ],
                    ],
                ),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            <span class="yii-debug-asset-chip yii-debug-asset-chip-deps"><strong>3</strong> deps</span>
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Wiring
            </h3><div class="yii-debug-asset-depends">
            <span class="yii-debug-asset-depends-label">Depends on 3</span><div class="yii-debug-asset-depends-list">
            <a class="yii-debug-asset-depend" href="#app\-a" title="app\A"><span class="yii-debug-asset-depend-icon" aria-hidden="true">↳</span><span class="yii-debug-asset-depend-name">A</span></a><a class="yii-debug-asset-depend" href="#app\-b" title="app\B"><span class="yii-debug-asset-depend-icon" aria-hidden="true">↳</span><span class="yii-debug-asset-depend-name">B</span></a><a class="yii-debug-asset-depend" href="#app\-c" title="app\C"><span class="yii-debug-asset-depend-icon" aria-hidden="true">↳</span><span class="yii-debug-asset-depend-name">C</span></a>
            </div>
            </div>
            </section>
            </div>
            </article>
            HTML,
            $html,
            "Multiple dependencies must read 'N deps'.",
        );
    }

    public function testRenderCardEmitsShortNameAndNamespacePrefix(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['vendor\\package\\AppAsset' => []]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertMatchesRegularExpression(
            '/>\s*AppAsset\s*</',
            $html,
            'Header must render the bundle short name.',
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="vendor\package\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">vendor\package\</span>
            </div><div class="yii-debug-asset-card-meta">
            </div>
            </header>
            </article>
            HTML,
            $html,
            'Header must render the namespace prefix.',
        );
    }

    public function testRenderCardEmitsSingularChipForOneDependency(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\AppAsset' => ['depends' => ['app\\Other']]]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            <span class="yii-debug-asset-chip yii-debug-asset-chip-deps"><strong>1</strong> dep</span>
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Wiring
            </h3><div class="yii-debug-asset-depends">
            <span class="yii-debug-asset-depends-label">Depends on 1</span><div class="yii-debug-asset-depends-list">
            <a class="yii-debug-asset-depend" href="#app\-other" title="app\Other"><span class="yii-debug-asset-depend-icon" aria-hidden="true">↳</span><span class="yii-debug-asset-depend-name">Other</span></a>
            </div>
            </div>
            </section>
            </div>
            </article>
            HTML,
            $html,
            "Single dependency must read '1 dep'.",
        );
    }

    public function testRenderCardEmitsTwoColumnLayoutWhenFilesAndWiringPresent(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(
                    [
                        'app\\AppAsset' => [
                            'css' => ['app.css'],
                            'sourcePath' => '@app/assets',
                        ],
                    ],
                ),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            <span class="yii-debug-asset-chip yii-debug-asset-chip-css"><strong>1</strong> css</span>
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="2">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Files
            </h3><div class="yii-debug-asset-files">
            <div class="yii-debug-asset-file">
            <span class="yii-debug-asset-file-type yii-debug-asset-file-type-css">.css</span><span class="yii-debug-asset-file-name" title="app.css">app.css</span>
            </div>
            </div>
            </section><section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Wiring
            </h3><dl class="yii-debug-asset-wiring">
            <div class="yii-debug-asset-wiring-row">
            <dt class="yii-debug-asset-wiring-label">
            source
            </dt><dd class="yii-debug-asset-wiring-value">
            @app/assets
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </article>
            HTML,
            $html,
            "Files + wiring must produce a '2-column' body.",
        );


    }

    public function testRenderCardLinksDependencyToRegisteredAnchor(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(
                    [
                        'app\\AppAsset' => ['depends' => ['app\\Target']],
                        'app\\Target' => [],
                    ],
                ),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected the source bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            <span class="yii-debug-asset-chip yii-debug-asset-chip-deps"><strong>1</strong> dep</span>
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Wiring
            </h3><div class="yii-debug-asset-depends">
            <span class="yii-debug-asset-depends-label">Depends on 1</span><div class="yii-debug-asset-depends-list">
            <a class="yii-debug-asset-depend" href="#app\-target" title="app\Target"><span class="yii-debug-asset-depend-icon" aria-hidden="true">↳</span><span class="yii-debug-asset-depend-name">Target</span></a>
            </div>
            </div>
            </section>
            </div>
            </article>
            HTML,
            $html,
            'Dep link must target the registered anchor.',
        );
    }

    public function testRenderCardOmitsBodyWhenBundleHasNoFilesOrWiringOrDeps(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\BareAsset' => []]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-bare-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            BareAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            </div>
            </header>
            </article>
            HTML,
            $html,
            'Empty bundles must omit the card body.',
        );
    }

    public function testRenderCardWiringRendersBasePathRow(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\AppAsset' => ['basePath' => '@webroot/assets']]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertMatchesRegularExpression(
            '/>\s*base\s*</',
            $html,
            "Populated 'basePath' must render its row.",
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Wiring
            </h3><dl class="yii-debug-asset-wiring">
            <div class="yii-debug-asset-wiring-row">
            <dt class="yii-debug-asset-wiring-label">
            base
            </dt><dd class="yii-debug-asset-wiring-value">
            @webroot/assets
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </article>
            HTML,
            $html,
            "Populated 'basePath' value must be rendered.",
        );
    }

    public function testRenderCardWiringRendersOnlyPopulatedFields(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\AppAsset' => ['baseUrl' => '/assets']]),
            );

        $bundle = $summary->bundles[0] ?? self::fail('Expected one bundle.');

        $html = AssetCardRenderer::renderCard($bundle, $summary)->render();

        self::assertMatchesRegularExpression(
            '/>\s*url\s*</',
            $html,
            "Populated 'baseUrl' must render its row.",
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-asset-card" id="app\-app-asset">
            <header class="yii-debug-asset-card-head">
            <span class="yii-debug-asset-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m12 3l8 4.5v9L12 21l-8-4.5v-9zm0 9l8-4.5M12 12v9m0-9L4 7.5m12-2.25l-8 4.5"/></svg></span><div class="yii-debug-asset-card-title">
            <h2 class="yii-debug-asset-card-name">
            AppAsset
            </h2><span class="yii-debug-asset-card-fqcn">app\</span>
            </div><div class="yii-debug-asset-card-meta">
            </div>
            </header><div class="yii-debug-asset-card-body" data-cols="1">
            <section class="yii-debug-asset-section">
            <h3 class="yii-debug-asset-section-title">
            Wiring
            </h3><dl class="yii-debug-asset-wiring">
            <div class="yii-debug-asset-wiring-row">
            <dt class="yii-debug-asset-wiring-label">
            url
            </dt><dd class="yii-debug-asset-wiring-value">
            /assets
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </article>
            HTML,
            $html,
            "Populated 'baseUrl' value must be rendered.",
        );
        self::assertDoesNotMatchRegularExpression(
            '/>\s*source\s*</',
            $html,
            "Empty 'sourcePath' must not render a row.",
        );
        self::assertDoesNotMatchRegularExpression(
            '/>\s*base\s*</',
            $html,
            "Empty 'basePath' must not render a row.",
        );
    }

    public function testResolveAnchorFallsBackToCamel2idForUnregisteredDep(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(['app\\AppAsset' => []]),
            );

        self::assertSame(
            'unknown\\package\\-stranger-asset',
            AssetCardRenderer::resolveAnchor('unknown\\package\\StrangerAsset', $summary),
            "Unregistered deps must use the same 'Inflector::camel2id()' rule as registered ones.",
        );
    }

    public function testResolveAnchorReturnsRegisteredBundleId(): void
    {
        $summary = (new AssetBundleNormalizer())
            ->normalize(
                self::rows(
                    [
                        'app\\AppAsset' => [],
                        'app\\OtherAsset' => [],
                    ],
                ),
            );

        $bundle = $summary->bundles[1] ?? self::fail('Expected a second bundle.');

        self::assertSame(
            $bundle->id,
            AssetCardRenderer::resolveAnchor('app\\OtherAsset', $summary),
            'Registered deps must resolve to the matching card id.',
        );
    }

    /**
     * @param array<array-key, mixed> $bundles
     *
     * @return list<AssetBundleRow>
     */
    private static function rows(array $bundles): array
    {
        $rows = [];

        foreach ($bundles as $name => $bundle) {
            if (is_string($name) && is_array($bundle)) {
                $rows[] = AssetBundleRow::fromBundle($name, $bundle);
            }
        }

        return $rows;
    }
}

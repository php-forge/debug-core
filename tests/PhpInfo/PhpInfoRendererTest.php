<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\PhpInfo;

use PHPForge\Debug\PhpInfo\{
    PhpInfoCompactModule,
    PhpInfoDataNormalizer,
    PhpInfoModuleGroup,
    PhpInfoRenderer,
    PhpInfoSection,
    PhpInfoTile,
    PhpInfoTocEntry,
    PhpInfoToken,
    PhpInfoView,
};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PhpInfoRenderer} covering the TOC sidebar, the per-section composition (eyebrow + headline +
 * tiles), the tile-kind rendering branches and the Configure Command details disclosure.
 */
#[Group('phpinfo')]
final class PhpInfoRendererTest extends TestCase
{
    public function testModuleGroupBucketPreservesEveryGroupAndPublicResolution(): void
    {
        self::assertSame(
            'Database',
            PhpInfoModuleGroup::resolve('PDO'),
            'Public resolution must remain callable.',
        );
        self::assertSame(
            [
                'Core & Runtime',
                'Database',
                'Text & Localization',
                'Network & Security',
                'XML, Data & Media',
                'System & Compression',
                'Environment',
                'Other',
            ],
            array_keys(PhpInfoModuleGroup::bucket([], static fn(string $title): string => $title)),
            'Bucketing must preserve every group in display order even when it is empty.',
        );
    }

    public function testRenderEmitsTocLinkPerEntry(): void
    {
        $view = $this->emptyView(
            [
                new PhpInfoTocEntry(
                    title: 'Overview',
                    slug: 'phpinfo-overview',
                ),
                new PhpInfoTocEntry(
                    title: 'apcu',
                    slug: 'phpinfo-apcu',
                ),
            ],
        );

        $html = PhpInfoRenderer::render($view);

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>1</span><span>modules</span>
            </header><ul class="yii-debug-phpinfo-toc-overview">
            <li>
            <a class="yii-debug-phpinfo-toc-link is-active" href="#phpinfo-overview" data-toc-target="phpinfo-overview" aria-current="page">Overview</a>
            </li>
            </ul><div class="yii-debug-phpinfo-toc-groups">
            <details class="yii-debug-phpinfo-toc-group" data-yii-debug-phpinfo-toc-group="true">
            <summary class="yii-debug-phpinfo-toc-group-summary">
            <span>Core &amp; Runtime</span><span class="yii-debug-phpinfo-toc-group-count" aria-label="1 module">1</span>
            </summary><ul class="yii-debug-phpinfo-toc-group-list">
            <li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-apcu" data-toc-target="phpinfo-apcu">apcu</a>
            </li>
            </ul>
            </details>
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'TOC must link to the Overview slug.',
        );
    }

    public function testRenderGroupsModulesAndFallsBackToOther(): void
    {
        $html = PhpInfoRenderer::render(
            $this->emptyView(
                [
                    new PhpInfoTocEntry(
                        title: 'Overview',
                        slug: 'phpinfo-overview',
                    ),
                    new PhpInfoTocEntry(
                        title: 'Core',
                        slug: 'phpinfo-core',
                    ),
                    new PhpInfoTocEntry(
                        title: 'date',
                        slug: 'phpinfo-date',
                    ),
                    new PhpInfoTocEntry(
                        title: 'PDO',
                        slug: 'phpinfo-pdo',
                    ),
                    new PhpInfoTocEntry(
                        title: 'vendor_extension',
                        slug: 'phpinfo-vendor-extension',
                    ),
                ],
            ),
        );

        self::assertMatchesRegularExpression(
            '~Core &amp; Runtime.*?href="#phpinfo-core".*?href="#phpinfo-date".*?</details>~s',
            $html,
            'Runtime modules must share one collapsed navigation group.',
        );
        self::assertMatchesRegularExpression(
            '~Database.*?href="#phpinfo-pdo".*?</details>~s',
            $html,
            'Database drivers must render in the Database group.',
        );
        self::assertMatchesRegularExpression(
            '~Other.*?href="#phpinfo-vendor-extension".*?</details>~s',
            $html,
            'Unknown extensions must remain accessible in the Other group.',
        );
        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>4</span><span>modules</span>
            </header><ul class="yii-debug-phpinfo-toc-overview">
            <li>
            <a class="yii-debug-phpinfo-toc-link is-active" href="#phpinfo-overview" data-toc-target="phpinfo-overview" aria-current="page">Overview</a>
            </li>
            </ul><div class="yii-debug-phpinfo-toc-groups">
            <details class="yii-debug-phpinfo-toc-group" data-yii-debug-phpinfo-toc-group="true">
            <summary class="yii-debug-phpinfo-toc-group-summary">
            <span>Core &amp; Runtime</span><span class="yii-debug-phpinfo-toc-group-count" aria-label="2 modules">2</span>
            </summary><ul class="yii-debug-phpinfo-toc-group-list">
            <li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-core" data-toc-target="phpinfo-core">Core</a>
            </li><li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-date" data-toc-target="phpinfo-date">date</a>
            </li>
            </ul>
            </details><details class="yii-debug-phpinfo-toc-group" data-yii-debug-phpinfo-toc-group="true">
            <summary class="yii-debug-phpinfo-toc-group-summary">
            <span>Database</span><span class="yii-debug-phpinfo-toc-group-count" aria-label="1 module">1</span>
            </summary><ul class="yii-debug-phpinfo-toc-group-list">
            <li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-pdo" data-toc-target="phpinfo-pdo">PDO</a>
            </li>
            </ul>
            </details><details class="yii-debug-phpinfo-toc-group" data-yii-debug-phpinfo-toc-group="true">
            <summary class="yii-debug-phpinfo-toc-group-summary">
            <span>Other</span><span class="yii-debug-phpinfo-toc-group-count" aria-label="1 module">1</span>
            </summary><ul class="yii-debug-phpinfo-toc-group-list">
            <li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-vendor-extension" data-toc-target="phpinfo-vendor-extension">vendor_extension</a>
            </li>
            </ul>
            </details>
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Every module group must expose the JavaScript synchronization hook.',
        );
    }

    public function testRenderMarksLongOverviewValuesAsWide(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Build',
            tiles: [
                new PhpInfoTile(
                    label: 'Build System',
                    displayValue: 'A deliberately long build-system value that needs the complete card width',
                    rawValue: 'A deliberately long build-system value that needs the complete card width',
                    kind: PhpInfoTile::KIND_TEXT,
                ),
            ],
        );

        $html = PhpInfoRenderer::render(
            new PhpInfoView(
                sections: [$section],
                tocEntries: [],
                compactModules: [],
                modulesHtml: '',
                configureCommand: '',
            ),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="Build">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">Build</span>
            </header><dl class="yii-debug-phpinfo-overview-hero-metrics">
            <div class="yii-debug-phpinfo-overview-hero-metric is-wide">
            <dt>
            Build System
            </dt><dd>
            <code>A deliberately long build-system value that needs the complete card width</code>
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Long technical values must span the overview card instead of wrapping inside a narrow grid cell.',
        );
    }

    public function testRenderMarksOverviewAsInitialTocSelection(): void
    {
        $html = PhpInfoRenderer::render(
            $this->emptyView(
                [
                    new PhpInfoTocEntry(
                        title: 'Overview',
                        slug: 'phpinfo-overview',
                    ),
                    new PhpInfoTocEntry(
                        title: 'Core',
                        slug: 'phpinfo-core',
                    ),
                ],
            ),
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>1</span><span>modules</span>
            </header><ul class="yii-debug-phpinfo-toc-overview">
            <li>
            <a class="yii-debug-phpinfo-toc-link is-active" href="#phpinfo-overview" data-toc-target="phpinfo-overview" aria-current="page">Overview</a>
            </li>
            </ul><div class="yii-debug-phpinfo-toc-groups">
            <details class="yii-debug-phpinfo-toc-group" data-yii-debug-phpinfo-toc-group="true">
            <summary class="yii-debug-phpinfo-toc-group-summary">
            <span>Core &amp; Runtime</span><span class="yii-debug-phpinfo-toc-group-count" aria-label="1 module">1</span>
            </summary><ul class="yii-debug-phpinfo-toc-group-list">
            <li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-core" data-toc-target="phpinfo-core">Core</a>
            </li>
            </ul>
            </details>
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Overview must render as the initial selected view before JavaScript initializes.',
        );
    }

    public function testRenderModulesHtmlPassesThroughVerbatim(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '<section id="phpinfo-apcu">module-body</section>',
            configureCommand: '',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            </div>
            </section><section id="phpinfo-apcu">module-body</section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Modules HTML must round-trip verbatim into the main column.',
        );
    }

    public function testRenderRendersConfigureCommandWhenPresent(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: './configure --foo',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            </div><details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Configure Command</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <pre>
            ./configure --foo
            </pre>
            </div>
            </details>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Configure Command details must surface.',
        );

    }

    public function testRenderSearchInputCarriesFilterHooks(): void
    {
        $html = PhpInfoRenderer::render($this->emptyView([]));

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Search input must enable the filter JS hook explicitly.',
        );


        self::assertMatchesRegularExpression(
            '~<button class="yii-debug-phpinfo-search-clear"[^>]*\shidden(?:\s|>)~',
            $html,
            'The clear action must remain hidden until the search has content.',
        );
    }

    public function testRenderSectionRendersEyebrowHeader(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Runtime',
            tiles: [],
        );
        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );

        $html = PhpInfoRenderer::render($view);

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="Runtime">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">Runtime</span>
            </header><dl class="yii-debug-phpinfo-overview-hero-metrics">
            </dl>
            </section>
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Every overview section must retain its eyebrow header.',
        );
    }

    public function testRenderSectionWithMutedPillTile(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Capabilities',
            tiles: [
                new PhpInfoTile(
                    label: 'Debug Build',
                    displayValue: 'no',
                    rawValue: 'no',
                    kind: PhpInfoTile::KIND_PILL_MUTED,
                ),
            ],
        );

        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
        $html = PhpInfoRenderer::render($view);

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="Capabilities">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">Capabilities</span>
            </header><dl class="yii-debug-phpinfo-overview-hero-metrics">
            <div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            Debug Build
            </dt><dd>
            <span class="yii-debug-phpinfo-overview-pill" data-variant="muted">no</span>
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'Muted pill must carry the pill CSS class.',
        );

    }

    public function testRenderSectionWithPathListTokens(): void
    {
        $tile = new PhpInfoTile(
            label: 'Additional .ini files parsed',
            displayValue: '/etc/a.ini, /etc/b.ini',
            rawValue: '/etc/a.ini, /etc/b.ini',
            kind: PhpInfoTile::KIND_PATH_LIST,
            tokens: [
                new PhpInfoToken(label: 'a.ini', title: '/etc/a.ini'),
                new PhpInfoToken(label: 'b.ini', title: '/etc/b.ini'),
            ],
        );
        $section = new PhpInfoSection(
            eyebrow: 'Configuration',
            tiles: [$tile],
        );
        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
        $html = PhpInfoRenderer::render($view);

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="Configuration">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">Configuration</span>
            </header><dl class="yii-debug-phpinfo-overview-hero-metrics">
            <div class="yii-debug-phpinfo-overview-hero-metric is-wide">
            <dt>
            Additional .ini files parsed
            </dt><dd>
            <span class="yii-debug-phpinfo-overview-files"><code class="yii-debug-phpinfo-overview-token" title="/etc/a.ini">a.ini</code><code class="yii-debug-phpinfo-overview-token" title="/etc/b.ini">b.ini</code></span>
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            $html,
            'First token basename must render inside a code chip.',
        );


    }

    public function testRenderSectionWithPathTileRendersCodeWithFullPathTitle(): void
    {
        $tile = new PhpInfoTile(
            label: 'Loaded Configuration File',
            displayValue: 'php.ini',
            rawValue: '/etc/php/8.5/cli/php.ini',
            kind: PhpInfoTile::KIND_PATH,
        );
        $section = new PhpInfoSection(
            eyebrow: 'Configuration',
            tiles: [$tile],
        );
        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="Configuration">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">Configuration</span>
            </header><dl class="yii-debug-phpinfo-overview-hero-metrics">
            <div class="yii-debug-phpinfo-overview-hero-metric is-wide">
            <dt>
            Loaded Configuration File
            </dt><dd>
            <code title="/etc/php/8.5/cli/php.ini">php.ini</code>
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            PhpInfoRenderer::render($view),
            'KIND_PATH must render inside a `<code>` element.',
        );
    }

    public function testRenderSectionWithSuccessPillTile(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Capabilities',
            tiles: [
                new PhpInfoTile(
                    label: 'IPv6 Support',
                    displayValue: 'enabled',
                    rawValue: 'enabled',
                    kind: PhpInfoTile::KIND_PILL_SUCCESS,
                ),
            ],
        );
        $view = new PhpInfoView(
            sections: [$section],
            tocEntries: [],
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );


        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="Capabilities">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">Capabilities</span>
            </header><dl class="yii-debug-phpinfo-overview-hero-metrics">
            <div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            IPv6 Support
            </dt><dd>
            <span class="yii-debug-phpinfo-overview-pill" data-variant="success">enabled</span>
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            PhpInfoRenderer::render($view),
            'Success pill must carry the success variant attribute.',
        );
    }

    public function testRenderSkipsConfigureCommandWhenEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>0</span><span>modules</span>
            </header><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            PhpInfoRenderer::render($this->emptyView([])),
            'Empty Configure Command must drop the disclosure.',
        );
    }

    public function testRenderSummarizesCompactModulesInOverview(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [
                new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview'),
                new PhpInfoTocEntry(title: 'Core', slug: 'phpinfo-core'),
            ],
            compactModules: [
                new PhpInfoCompactModule(
                    title: 'calendar',
                    slug: 'phpinfo-calendar',
                    tiles: [
                        new PhpInfoTile(
                            label: 'Calendar support',
                            displayValue: 'enabled',
                            rawValue: 'enabled',
                            kind: PhpInfoTile::KIND_PILL_SUCCESS,
                        ),
                    ],
                ),
            ],
            modulesHtml: '<section id="phpinfo-core">Core</section>',
            configureCommand: '',
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>2</span><span>modules</span><span class="yii-debug-phpinfo-toc-title-note">1 in Overview</span>
            </header><ul class="yii-debug-phpinfo-toc-overview">
            <li>
            <a class="yii-debug-phpinfo-toc-link is-active" href="#phpinfo-overview" data-toc-target="phpinfo-overview" aria-current="page">Overview</a>
            </li>
            </ul><div class="yii-debug-phpinfo-toc-groups">
            <details class="yii-debug-phpinfo-toc-group" data-yii-debug-phpinfo-toc-group="true">
            <summary class="yii-debug-phpinfo-toc-group-summary">
            <span>Core &amp; Runtime</span><span class="yii-debug-phpinfo-toc-group-count" aria-label="1 module">1</span>
            </summary><ul class="yii-debug-phpinfo-toc-group-list">
            <li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-core" data-toc-target="phpinfo-core">Core</a>
            </li>
            </ul>
            </details>
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <details class="yii-debug-disclosure yii-debug-phpinfo-extensions" aria-label="Loaded extensions" data-yii-debug-phpinfo-extensions="true">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Loaded extensions</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-phpinfo-extension-groups">
            <section class="yii-debug-phpinfo-extension-group" aria-label="System &amp; Compression">
            <header class="yii-debug-phpinfo-extension-group-head">
            <span>System &amp; Compression</span><span class="yii-debug-phpinfo-extension-group-count" data-yii-debug-phpinfo-extension-group-count="true" data-yii-debug-phpinfo-total="1">1</span>
            </header><div class="yii-debug-ext-strip">
            <span class="yii-debug-ext-pill is-on" id="phpinfo-calendar" title="Calendar support: enabled" data-section="calendar" data-yii-debug-phpinfo-compact-module="true"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">calendar</span><span class="yii-debug-ext-pill-state">on</span><span class="yii-debug-sr-only">Calendar support: enabled</span></span>
            </div>
            </section>
            </div>
            </details>
            </div>
            </section><section id="phpinfo-core">Core</section>
            </div>
            </div>
            </div>
            HTML,
            PhpInfoRenderer::render($view),
            'Facts-only modules must surface in the Overview.',
        );
    }

    public function testRenderSurfacesVersionAndDisabledStateInCompactPills(): void
    {
        $view = new PhpInfoView(
            sections: [],
            tocEntries: [new PhpInfoTocEntry(title: 'Overview', slug: 'phpinfo-overview')],
            compactModules: [
                new PhpInfoCompactModule(
                    title: 'pdo_sqlite',
                    slug: 'phpinfo-pdo-sqlite',
                    tiles: [
                        new PhpInfoTile(
                            label: 'PDO Driver for SQLite 3.x',
                            displayValue: 'enabled',
                            rawValue: 'enabled',
                            kind: PhpInfoTile::KIND_PILL_SUCCESS,
                        ),
                        new PhpInfoTile(
                            label: 'SQLite Library',
                            displayValue: '3.53.3',
                            rawValue: '3.53.3',
                            kind: PhpInfoTile::KIND_TEXT,
                        ),
                    ],
                ),
                new PhpInfoCompactModule(
                    title: 'sysvshm',
                    slug: 'phpinfo-sysvshm',
                    tiles: [
                        new PhpInfoTile(
                            label: 'sysvshm support',
                            displayValue: 'disabled',
                            rawValue: 'disabled',
                            kind: PhpInfoTile::KIND_PILL_MUTED,
                        ),
                        new PhpInfoTile(
                            label: 'Version',
                            displayValue: '1.2.3',
                            rawValue: '1.2.3',
                            kind: PhpInfoTile::KIND_TEXT,
                        ),
                    ],
                ),
            ],
            modulesHtml: '',
            configureCommand: '',
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>2</span><span>modules</span><span class="yii-debug-phpinfo-toc-title-note">2 in Overview</span>
            </header><ul class="yii-debug-phpinfo-toc-overview">
            <li>
            <a class="yii-debug-phpinfo-toc-link is-active" href="#phpinfo-overview" data-toc-target="phpinfo-overview" aria-current="page">Overview</a>
            </li>
            </ul><div class="yii-debug-phpinfo-toc-groups">
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <details class="yii-debug-disclosure yii-debug-phpinfo-extensions" aria-label="Loaded extensions" data-yii-debug-phpinfo-extensions="true">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Loaded extensions</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-phpinfo-extension-groups">
            <section class="yii-debug-phpinfo-extension-group" aria-label="Database">
            <header class="yii-debug-phpinfo-extension-group-head">
            <span>Database</span><span class="yii-debug-phpinfo-extension-group-count" data-yii-debug-phpinfo-extension-group-count="true" data-yii-debug-phpinfo-total="1">1</span>
            </header><div class="yii-debug-ext-strip">
            <span class="yii-debug-ext-pill is-on" id="phpinfo-pdo-sqlite" title="PDO Driver for SQLite 3.x: enabled · SQLite Library: 3.53.3" data-section="pdo_sqlite" data-yii-debug-phpinfo-compact-module="true"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">pdo_sqlite</span><span class="yii-debug-ext-pill-state">3.53.3</span><span class="yii-debug-sr-only">PDO Driver for SQLite 3.x: enabled · SQLite Library: 3.53.3</span></span>
            </div>
            </section><section class="yii-debug-phpinfo-extension-group" aria-label="System &amp; Compression">
            <header class="yii-debug-phpinfo-extension-group-head">
            <span>System &amp; Compression</span><span class="yii-debug-phpinfo-extension-group-count" data-yii-debug-phpinfo-extension-group-count="true" data-yii-debug-phpinfo-total="1">1</span>
            </header><div class="yii-debug-ext-strip">
            <span class="yii-debug-ext-pill is-off" id="phpinfo-sysvshm" title="sysvshm support: disabled · Version: 1.2.3" data-section="sysvshm" data-yii-debug-phpinfo-compact-module="true"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">sysvshm</span><span class="yii-debug-ext-pill-state">1.2.3</span><span class="yii-debug-sr-only">sysvshm support: disabled · Version: 1.2.3</span></span>
            </div>
            </section>
            </div>
            </details>
            </div>
            </section>
            </div>
            </div>
            </div>
            HTML,
            PhpInfoRenderer::render($view),
            'The state slot must prefer the version over a redundant on.',
        );
    }

    public function testRenderUsesUnicodeAwareStrictWideTileBoundary(): void
    {
        $section = new PhpInfoSection(
            eyebrow: 'Build',
            tiles: [
                new PhpInfoTile('Boundary', str_repeat('a', 48), str_repeat('a', 48), PhpInfoTile::KIND_TEXT),
                new PhpInfoTile('Unicode', str_repeat('é', 30), str_repeat('é', 30), PhpInfoTile::KIND_TEXT),
                new PhpInfoTile('Short', 'short', 'short', PhpInfoTile::KIND_TEXT),
                new PhpInfoTile('Path', '/x', '/x', PhpInfoTile::KIND_PATH),
            ],
        );

        $html = PhpInfoRenderer::render(new PhpInfoView([$section], [], [], '', ''));

        foreach (['Boundary', 'Unicode', 'Short'] as $label) {
            self::assertMatchesRegularExpression(
                '~<div class="yii-debug-phpinfo-overview-hero-metric">\s*<dt>\s*' . $label . '\s*</dt>~',
                $html,
                "{$label} must remain a compact metric.",
            );
        }
        self::assertMatchesRegularExpression(
            '~<div class="yii-debug-phpinfo-overview-hero-metric is-wide">\s*<dt>\s*Path\s*</dt>~',
            $html,
            'Path tiles must remain wide regardless of their short value.',
        );
    }

    public function testRenderViaNormalizerSnapshotProducesExpectedAnchors(): void
    {
        $body = <<<HTML
        <h2>apcu</h2>
        <table>
        <tr><td>Version</td><td>5.1</td></tr>
        <tr><td>Debug</td><td>disabled</td></tr>
        <tr><td>MMAP</td><td>enabled</td></tr>
        </table>
        HTML;

        $view = PhpInfoDataNormalizer::fromOutput(
            $body,
            '8.5.3',
            'cli',
            'Linux',
            '128M',
        );

        self::assertSame(
            <<<HTML
            <div class="yii-debug-phpinfo-shell">
            <aside class="yii-debug-phpinfo-toc" aria-label="phpinfo modules">
            <header class="yii-debug-phpinfo-toc-title">
            <span>1</span><span>modules</span>
            </header><ul class="yii-debug-phpinfo-toc-overview">
            <li>
            <a class="yii-debug-phpinfo-toc-link is-active" href="#phpinfo-overview" data-toc-target="phpinfo-overview" aria-current="page">Overview</a>
            </li>
            </ul><div class="yii-debug-phpinfo-toc-groups">
            <details class="yii-debug-phpinfo-toc-group" data-yii-debug-phpinfo-toc-group="true">
            <summary class="yii-debug-phpinfo-toc-group-summary">
            <span>Core &amp; Runtime</span><span class="yii-debug-phpinfo-toc-group-count" aria-label="1 module">1</span>
            </summary><ul class="yii-debug-phpinfo-toc-group-list">
            <li>
            <a class="yii-debug-phpinfo-toc-link" href="#phpinfo-apcu" data-toc-target="phpinfo-apcu">apcu</a>
            </li>
            </ul>
            </details>
            </div>
            </aside><div class="yii-debug-phpinfo-main">
            <div class="yii-debug-phpinfo-search" role="search" aria-label="PHP configuration">
            <div class="yii-debug-phpinfo-search-control">
            <label class="yii-debug-sr-only" for="yii-debug-phpinfo-filter">Filter PHP modules and settings</label><span class="yii-debug-phpinfo-search-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span><input class="yii-debug-phpinfo-search-input" id="yii-debug-phpinfo-filter" name="phpinfo-filter" type="search" autocomplete="off" spellcheck="false" aria-controls="yii-debug-phpinfo-results" data-yii-debug-phpinfo-search="true" placeholder="Search modules or settings…"><button class="yii-debug-phpinfo-search-clear" type="button" hidden aria-label="Clear PHP configuration search" data-yii-debug-phpinfo-clear="true">Clear</button>
            </div><div class="yii-debug-phpinfo-search-feedback">
            <span class="yii-debug-phpinfo-search-status" aria-live="polite" data-yii-debug-phpinfo-status="true"></span><span class="yii-debug-phpinfo-search-empty" hidden data-yii-debug-phpinfo-empty="true">No modules or settings match this query.</span>
            </div>
            </div><div id="yii-debug-phpinfo-results">
            <section class="yii-debug-phpinfo-section" id="phpinfo-overview" data-section="Overview">
            <div class="yii-debug-phpinfo-overview-hero">
            <section class="yii-debug-phpinfo-overview-hero-section" aria-label="PHP version">
            <header class="yii-debug-phpinfo-overview-block-head">
            <span class="yii-debug-phpinfo-overview-block-eyebrow">PHP version</span>
            </header><div class="yii-debug-phpinfo-overview-hero-headline">
            <strong class="yii-debug-phpinfo-overview-hero-version">8.5.3</strong><span class="yii-debug-phpinfo-overview-hero-mark" aria-hidden="true">php</span>
            </div><dl class="yii-debug-phpinfo-overview-hero-metrics">
            <div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            SAPI
            </dt><dd>
            <code>cli</code>
            </dd>
            </div><div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            OS
            </dt><dd>
            <code>Linux</code>
            </dd>
            </div><div class="yii-debug-phpinfo-overview-hero-metric">
            <dt>
            Memory limit
            </dt><dd>
            <code>128M</code>
            </dd>
            </div>
            </dl>
            </section>
            </div>
            </section><section class="yii-debug-phpinfo-section yii-debug-phpinfo-module" id="phpinfo-apcu" data-section="apcu"><header class="yii-debug-phpinfo-module-head"><h2 id="phpinfo-apcu-heading">apcu</h2></header>
            <div class="yii-debug-table-wrap yii-debug-phpinfo-table-section is-facts"><header class="yii-debug-phpinfo-table-section-head"><span>Module information</span><span class="yii-debug-phpinfo-table-section-count">3 values</span></header><div class="yii-debug-phpinfo-table-scroll"><table aria-label="Module information" class="yii-debug-table is-facts">
            <tr class="yii-debug-phpinfo-fact"><td>Version</td><td>5.1</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>Debug</td><td>disabled</td></tr>
            <tr class="yii-debug-phpinfo-fact"><td>MMAP</td><td>enabled</td></tr>
            </table></div></div></section>
            </div>
            </div>
            </div>
            HTML,
            PhpInfoRenderer::render($view),
            'Overview anchor must surface in the rendered shell.',
        );


    }

    /**
     * @param list<PhpInfoTocEntry> $entries
     */
    private function emptyView(array $entries): PhpInfoView
    {
        return new PhpInfoView(
            sections: [],
            tocEntries: $entries,
            compactModules: [],
            modulesHtml: '',
            configureCommand: '',
        );
    }
}

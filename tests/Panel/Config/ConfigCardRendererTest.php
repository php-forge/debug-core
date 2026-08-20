<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Config;

use PHPForge\Debug\Panel\Config\{ApplicationConfig, ConfigCardRenderer, ConfigSummary, PhpConfig};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see ConfigCardRenderer} covering readout grid composition, PHP-extension pills, application details
 * rows, the conditional installed-extensions section and the php-info call-to-action link.
 */
#[Group('panel')]
#[Group('config')]
final class ConfigCardRendererTest extends TestCase
{
    public function testRenderApplicationDetailsSectionShowsCharsetAndLanguageRows(): void
    {
        $summary = self::makeSummary(charset: 'UTF-8', language: 'en-US', sourceLanguage: 'en');

        $html = ConfigCardRenderer::renderApplicationDetailsSection($summary->application)->render();

        self::assertSame(
            <<<HTML
            <section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            UTF-8
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            en-US (English, United States)
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            en (English)
            </dd>
            </div>
            </dl>
            </section>
            HTML,
            $html,
            'Charset row must be labeled.',
        );
    }

    public function testRenderApplicationDetailsSectionShowsEmDashWhenCharsetIsEmpty(): void
    {
        $summary = self::makeSummary(charset: '', language: '', sourceLanguage: '');

        $html = ConfigCardRenderer::renderApplicationDetailsSection($summary->application)->render();

        self::assertSame(
            <<<HTML
            <section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">//</span> Application details
            </h2><dl class="yii-debug-dl">
            <div class="yii-debug-dl-row">
            <dt>
            Charset
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Current language
            </dt><dd>
            —
            </dd>
            </div><div class="yii-debug-dl-row">
            <dt>
            Source language
            </dt><dd>
            —
            </dd>
            </div>
            </dl>
            </section>
            HTML,
            $html,
            'Empty charset must render the em-dash placeholder.',
        );
        self::assertSame(
            3,
            substr_count($html, '—'),
            'Empty charset, current language, and source language must each render a placeholder.',
        );
    }

    public function testRenderInstalledExtensionsSectionListsEveryPackageWithVersionPrefix(): void
    {
        $summary = self::makeSummary(extensions: ['acme/foo' => '1.0.0', 'acme/bar' => '2.5.1']);

        $section = ConfigCardRenderer::renderInstalledExtensionsSection($summary);

        self::assertNotNull(
            $section,
            'Non-empty roster must produce a section.',
        );

        $html = $section->render();

        self::assertSame(
            <<<HTML
            <section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">&gt;_</span> Installed extensions <span class="yii-debug-section-count">2</span>
            </h2><div class="yii-debug-packages">
            <article class="yii-debug-package">
            <span class="yii-debug-package-glyph" aria-hidden="true">◆</span><span class="yii-debug-package-name">acme/foo</span><span class="yii-debug-package-version">v1.0.0</span>
            </article><article class="yii-debug-package">
            <span class="yii-debug-package-glyph" aria-hidden="true">◆</span><span class="yii-debug-package-name">acme/bar</span><span class="yii-debug-package-version">v2.5.1</span>
            </article>
            </div>
            </section>
            HTML,
            $html,
            'Section heading must be present.',
        );
    }

    public function testRenderInstalledExtensionsSectionReturnsNullWhenRosterIsEmpty(): void
    {
        $summary = self::makeSummary(extensions: []);

        self::assertNull(
            ConfigCardRenderer::renderInstalledExtensionsSection($summary),
            "Empty roster must return 'null' so the caller can omit the section.",
        );
    }

    public function testRenderPhpExtensionsSectionEmitsOneOnAndThreeOffPills(): void
    {
        $summary = self::makeSummary(xdebug: true);

        $html = ConfigCardRenderer::renderPhpExtensionsSection($summary->php)->render();

        self::assertSame(
            <<<HTML
            <section class="yii-debug-section">
            <h2 class="yii-debug-section-title">
            <span class="yii-debug-section-mark">::</span> PHP extensions
            </h2><div class="yii-debug-ext-strip">
            <span class="yii-debug-ext-pill is-on"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">Xdebug</span><span class="yii-debug-ext-pill-state">on</span></span><span class="yii-debug-ext-pill is-off"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">APCu</span><span class="yii-debug-ext-pill-state">off</span></span><span class="yii-debug-ext-pill is-off"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">Memcache</span><span class="yii-debug-ext-pill-state">off</span></span><span class="yii-debug-ext-pill is-off"><span class="yii-debug-ext-pill-dot" aria-hidden="true"></span><span class="yii-debug-ext-pill-label">Memcached</span><span class="yii-debug-ext-pill-state">off</span></span>
            </div>
            </section>
            HTML,
            $html,
            'Xdebug label must be present.',
        );
    }

    public function testRenderPhpInfoCtaProducesAnchorWithProvidedHref(): void
    {
        $html = ConfigCardRenderer::renderPhpInfoCta('/debug/default/php-info')->render();

        self::assertSame(
            <<<HTML
            <a class="yii-debug-cta" href="/debug/default/php-info" rel="noopener" target="_blank"><span class="yii-debug-cta-prompt" aria-hidden="true">→</span><span>View full phpinfo</span><span class="yii-debug-cta-external" aria-hidden="true">↗</span></a>
            HTML,
            $html,
            'CTA must carry the wrapper class.',
        );
    }

    public function testRenderReadoutGridShowsDebugOffMutedChipWhenDebugIsFalse(): void
    {
        $summary = self::makeSummary(debug: false);

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertSame(
            <<<HTML
            <div class="yii-debug-readout">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Yii</span><span class="yii-debug-readout-value">2.0.0</span><span class="yii-debug-readout-meta">framework</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">PHP</span><span class="yii-debug-readout-value">8.3.0</span><span class="yii-debug-readout-meta">runtime</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Environment</span><span class="yii-debug-readout-value">dev</span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip yii-debug-readout-chip-muted">debug off</span></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Application</span><span class="yii-debug-readout-value">Test</span><span class="yii-debug-readout-meta">instance</span>
            </article>
            </div>
            HTML,
            $html,
            'Disabled chip must use the muted modifier.',
        );

    }

    public function testRenderReadoutGridShowsDebugOnChipWhenDebugIsTrue(): void
    {
        $summary = self::makeSummary(debug: true);

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertSame(
            <<<HTML
            <div class="yii-debug-readout">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Yii</span><span class="yii-debug-readout-value">2.0.0</span><span class="yii-debug-readout-meta">framework</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">PHP</span><span class="yii-debug-readout-value">8.3.0</span><span class="yii-debug-readout-meta">runtime</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Environment</span><span class="yii-debug-readout-value">dev</span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip">debug on</span></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Application</span><span class="yii-debug-readout-value">Test</span><span class="yii-debug-readout-meta">instance</span>
            </article>
            </div>
            HTML,
            $html,
            'Debug chip text must be present.',
        );
    }

    public function testRenderReadoutGridShowsEmDashWhenApplicationNameIsEmpty(): void
    {
        $summary = self::makeSummary(name: '');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertSame(
            <<<HTML
            <div class="yii-debug-readout">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Yii</span><span class="yii-debug-readout-value">2.0.0</span><span class="yii-debug-readout-meta">framework</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">PHP</span><span class="yii-debug-readout-value">8.3.0</span><span class="yii-debug-readout-meta">runtime</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Environment</span><span class="yii-debug-readout-value">dev</span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip">debug on</span></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Application</span><span class="yii-debug-readout-value">—</span><span class="yii-debug-readout-meta">instance</span>
            </article>
            </div>
            HTML,
            $html,
            "Empty 'name' must render the em-dash placeholder.",
        );
    }

    public function testRenderReadoutGridShowsInstanceFallbackWhenApplicationVersionIsEmpty(): void
    {
        $summary = self::makeSummary(version: '');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertSame(
            <<<HTML
            <div class="yii-debug-readout">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Yii</span><span class="yii-debug-readout-value">2.0.0</span><span class="yii-debug-readout-meta">framework</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">PHP</span><span class="yii-debug-readout-value">8.3.0</span><span class="yii-debug-readout-meta">runtime</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Environment</span><span class="yii-debug-readout-value">dev</span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip">debug on</span></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Application</span><span class="yii-debug-readout-value">Test</span><span class="yii-debug-readout-meta">instance</span>
            </article>
            </div>
            HTML,
            $html,
            "Empty 'version' must fall back to 'instance' text.",
        );
    }

    public function testRenderReadoutGridShowsVersionChipWhenApplicationVersionIsPresent(): void
    {
        $summary = self::makeSummary(version: '1.2.3');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertSame(
            <<<HTML
            <div class="yii-debug-readout">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Yii</span><span class="yii-debug-readout-value">2.0.0</span><span class="yii-debug-readout-meta">framework</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">PHP</span><span class="yii-debug-readout-value">8.3.0</span><span class="yii-debug-readout-meta">runtime</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Environment</span><span class="yii-debug-readout-value">dev</span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip">debug on</span></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Application</span><span class="yii-debug-readout-value">Test</span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip yii-debug-readout-chip-muted">v1.2.3</span></span>
            </article>
            </div>
            HTML,
            $html,
            'Version chip must show the prefixed application version.',
        );

    }

    public function testRenderReadoutGridShowsYiiAndPhpAndEnvironmentAndApplicationCards(): void
    {
        $summary = self::makeSummary(yii: '2.0.50', phpVersion: '8.3.10', env: 'prod', name: 'Demo');

        $html = ConfigCardRenderer::renderReadoutGrid($summary)->render();

        self::assertSame(
            <<<HTML
            <div class="yii-debug-readout">
            <article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Yii</span><span class="yii-debug-readout-value">2.0.50</span><span class="yii-debug-readout-meta">framework</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">PHP</span><span class="yii-debug-readout-value">8.3.10</span><span class="yii-debug-readout-meta">runtime</span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Environment</span><span class="yii-debug-readout-value">prod</span><span class="yii-debug-readout-meta"><span class="yii-debug-readout-chip">debug on</span></span>
            </article><article class="yii-debug-readout-card">
            <span class="yii-debug-readout-corner" data-corner="tl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="tr" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="bl" aria-hidden="true"></span><span class="yii-debug-readout-corner" data-corner="br" aria-hidden="true"></span><span class="yii-debug-readout-label">Application</span><span class="yii-debug-readout-value">Demo</span><span class="yii-debug-readout-meta">instance</span>
            </article>
            </div>
            HTML,
            $html,
            'Outer wrapper class must be present.',
        );
    }

    /**
     * @param array<string, string> $extensions
     */
    private static function makeSummary(
        string $yii = '2.0.0',
        string $phpVersion = '8.3.0',
        string $name = 'Test',
        string $version = '',
        string $language = '',
        string $sourceLanguage = '',
        string $charset = '',
        string $env = 'dev',
        bool $debug = true,
        bool $xdebug = false,
        bool $apcu = false,
        bool $memcache = false,
        bool $memcached = false,
        array $extensions = [],
    ): ConfigSummary {
        return new ConfigSummary(
            application: new ApplicationConfig(
                yii: $yii,
                name: $name,
                version: $version,
                language: $language,
                sourceLanguage: $sourceLanguage,
                charset: $charset,
                env: $env,
                debug: $debug,
            ),
            php: new PhpConfig(
                version: $phpVersion,
                xdebug: $xdebug,
                apcu: $apcu,
                memcache: $memcache,
                memcached: $memcached,
            ),
            extensions: $extensions,
        );
    }
}

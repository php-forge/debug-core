<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Request;

use PHPForge\Debug\Panel\Request\{RequestHero, RequestSection, RequestSectionRenderer, RequestTab};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see RequestSectionRenderer} covering the hero header, section rendering (filter affordance,
 * empty-state fallback, name/value table rows) and the tab navigation / panel wiring.
 */
#[Group('panel')]
#[Group('request')]
final class RequestSectionRendererTest extends TestCase
{
    public function testRenderHeroEmitsFlagChipsForEachActiveFlag(): void
    {
        self::assertSame(
            <<<HTML
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span><span class="yii-debug-request-hero-url" title="http://example.test/">http://example.test/</span><span class="yii-debug-snapshot-status yii-debug-status-2xx">200</span>
            </div><div class="yii-debug-request-hero-meta">
            <span class="yii-debug-snapshot-tag">AJAX</span><span class="yii-debug-snapshot-tag">HTTPS</span>
            </div>
            </header>
            HTML,
            RequestSectionRenderer::renderHero(self::makeHero(flags: ['AJAX', 'HTTPS'])),
            'AJAX flag must surface in the meta strip.',
        );

    }

    public function testRenderHeroEmitsMetaSpansForEachNonEmptyMetaPiece(): void
    {
        self::assertSame(
            <<<HTML
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span><span class="yii-debug-request-hero-url" title="http://example.test/">http://example.test/</span><span class="yii-debug-snapshot-status yii-debug-status-2xx">200</span>
            </div><div class="yii-debug-request-hero-meta">
            <span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">IP</span><span class="yii-debug-request-hero-meta-value">127.0.0.1</span></span><span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">Time</span><span class="yii-debug-request-hero-meta-value">12:34:56</span></span><span class="yii-debug-request-hero-meta-item"><span class="yii-debug-request-hero-meta-label">Duration</span><span class="yii-debug-request-hero-meta-value">7.5 ms</span></span>
            </div>
            </header>
            HTML,
            RequestSectionRenderer::renderHero(self::makeHero(ip: '127.0.0.1', time: '12:34:56', durationMs: '7.5 ms')),
            'Request metadata must identify the IP address, capture time, and duration.',
        );
    }

    public function testRenderHeroOmitsMethodPillWhenMethodIsEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-url" title="http://example.test/">http://example.test/</span><span class="yii-debug-snapshot-status yii-debug-status-2xx">200</span>
            </div><div class="yii-debug-request-hero-meta">
            </div>
            </header>
            HTML,
            RequestSectionRenderer::renderHero(self::makeHero(method: '')),
            'Empty method must drop the method pill.',
        );
    }

    public function testRenderHeroOmitsStatusPillWhenStatusCodeIsZero(): void
    {
        self::assertSame(
            <<<HTML
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span><span class="yii-debug-request-hero-url" title="http://example.test/">http://example.test/</span>
            </div><div class="yii-debug-request-hero-meta">
            </div>
            </header>
            HTML,
            RequestSectionRenderer::renderHero(self::makeHero(statusCode: 0)),
            'Zero status must drop the status pill.',
        );
    }

    public function testRenderHeroRendersStatusPillWithVariantModifier(): void
    {
        self::assertSame(
            <<<HTML
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-get">GET</span><span class="yii-debug-request-hero-url" title="http://example.test/">http://example.test/</span><span class="yii-debug-snapshot-status yii-debug-status-5xx">500</span>
            </div><div class="yii-debug-request-hero-meta">
            </div>
            </header>
            HTML,
            RequestSectionRenderer::renderHero(self::makeHero(statusCode: 500, statusVariant: '5xx')),
            'Variant must surface as a vocabulary status class.',
        );

    }

    public function testRenderHeroTintsMethodPillWithVocabularyVerb(): void
    {
        self::assertSame(
            <<<HTML
            <header class="yii-debug-request-hero">
            <div class="yii-debug-request-hero-line">
            <span class="yii-debug-request-hero-method yii-debug-verb-post">POST</span><span class="yii-debug-request-hero-url" title="http://example.test/">http://example.test/</span><span class="yii-debug-snapshot-status yii-debug-status-2xx">200</span>
            </div><div class="yii-debug-request-hero-meta">
            </div>
            </header>
            HTML,
            RequestSectionRenderer::renderHero(self::makeHero(method: 'POST')),
            "POST must wear the 'post' verb class.",
        );
    }

    public function testRenderSectionEmitsFilterInputWhenFilterableAndNonEmpty(): void
    {
        $section = new RequestSection(caption: 'Server', entries: ['HTTP_HOST' => 'localhost'], filterable: true);

        self::assertSame(
            <<<HTML
            <header class="yii-debug-section-header">
            <h2>
            Server
            </h2><input class="yii-debug-filter-input" type="search" aria-label="Filter Server" data-yii-debug-filter="true" placeholder="Filter…">
            </header><div class="yii-debug-table-wrap" data-yii-debug-filter-target="true">
            <table class="yii-debug-table yii-debug-table-mono" style='table-layout: fixed;'>
            <thead>
            <tr>
            <th scope="col">
            Name
            </th><th scope="col">
            Value
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <th scope="row">
            HTTP_HOST
            </th><td>
            &#039;localhost&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            HTML,
            RequestSectionRenderer::renderSection($section),
            'Filterable section must expose a search input.',
        );
    }

    public function testRenderSectionPicksHtmlSpecialCharsEscapingForRowValues(): void
    {
        $section = new RequestSection(caption: 'Headers', entries: ['X-Custom' => "'quoted' <script>alert(1)</script>"]);

        self::assertSame(
            <<<HTML
            <header class="yii-debug-section-header">
            <h2>
            Headers
            </h2>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono" style='table-layout: fixed;'>
            <thead>
            <tr>
            <th scope="col">
            Name
            </th><th scope="col">
            Value
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <th scope="row">
            X-Custom
            </th><td>
            &#039;\&#039;quoted\&#039; &lt;script&gt;alert(1)&lt;/script&gt;&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            HTML,
            RequestSectionRenderer::renderSection($section),
            'Raw payload must never reach the rendered HTML.',
        );
    }

    public function testRenderSectionRendersOneRowPerEntry(): void
    {
        $section = new RequestSection(caption: 'Headers', entries: ['a' => 'A', 'b' => 'B', 'c' => 'C']);

        self::assertSame(
            3,
            substr_count(RequestSectionRenderer::renderSection($section), '<td>'),
            'Each entry must produce exactly one body row.',
        );
    }

    public function testRenderSectionUsesCollapsedDisclosureWhenSectionIsEmpty(): void
    {
        $section = new RequestSection(caption: 'Server', entries: [], filterable: true);

        self::assertSame(
            <<<HTML
            <details class="yii-debug-disclosure">
            <summary class="yii-debug-disclosure-summary">
            <span class="yii-debug-disclosure-title">Server</span><span class="yii-debug-disclosure-hint" aria-hidden="true"><span data-yii-debug-hint="collapsed">click to expand</span><span data-yii-debug-hint="expanded">click to collapse</span></span>
            </summary><div class="yii-debug-disclosure-body">
            <p class="yii-debug-table-empty">
            No data
            </p>
            </div>
            </details>
            HTML,
            RequestSectionRenderer::renderSection($section),
            'Empty section must use the shared collapsed disclosure without a filter input.',
        );

    }

    public function testRenderTabsMarksFirstTabActive(): void
    {
        $tabs = [
            new RequestTab(label: 'Parameters', sections: []),
            new RequestTab(label: 'Headers', sections: []),
        ];

        self::assertSame(
            <<<HTML
            <ul class="yii-debug-tabs" role="tablist" aria-label="Request data">
            <li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link is-active" id="request-tab-0" href="#request-panel-0" role="tab" tabindex="0" aria-controls="request-panel-0" aria-selected="true" data-yii-debug-toggle="tab">Parameters</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="request-tab-1" href="#request-panel-1" role="tab" tabindex="-1" aria-controls="request-panel-1" aria-selected="false" data-yii-debug-toggle="tab">Headers</a>
            </li>
            </ul><div class="yii-debug-tab-content">
            <div class="yii-debug-tab-panel is-active" id="request-panel-0" role="tabpanel" aria-labelledby="request-tab-0">
            </div><div class="yii-debug-tab-panel" id="request-panel-1" role="tabpanel" aria-labelledby="request-tab-1" hidden>
            </div>
            </div>
            HTML,
            RequestSectionRenderer::renderTabs($tabs),
            "First tab must carry the 'is-active' class.",
        );
    }

    public function testRenderTabsRendersNestedSections(): void
    {
        $tabs = [
            new RequestTab(
                label: 'Parameters',
                sections: [
                    new RequestSection(caption: 'Query parameters', entries: ['page' => 1]),
                    new RequestSection(caption: 'Body parameters', entries: ['name' => 'Ada']),
                ],
            ),
        ];

        self::assertSame(
            <<<'HTML'
            <ul class="yii-debug-tabs" role="tablist" aria-label="Request data">
            <li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link is-active" id="request-tab-0" href="#request-panel-0" role="tab" tabindex="0" aria-controls="request-panel-0" aria-selected="true" data-yii-debug-toggle="tab">Parameters</a>
            </li>
            </ul><div class="yii-debug-tab-content">
            <div class="yii-debug-tab-panel is-active" id="request-panel-0" role="tabpanel" aria-labelledby="request-tab-0">
            <header class="yii-debug-section-header">
            <h2>
            Query parameters
            </h2>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono" style='table-layout: fixed;'>
            <thead>
            <tr>
            <th scope="col">
            Name
            </th><th scope="col">
            Value
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <th scope="row">
            page
            </th><td>
            1
            </td>
            </tr>
            </tbody>
            </table>
            </div><header class="yii-debug-section-header">
            <h2>
            Body parameters
            </h2>
            </header><div class="yii-debug-table-wrap">
            <table class="yii-debug-table yii-debug-table-mono" style='table-layout: fixed;'>
            <thead>
            <tr>
            <th scope="col">
            Name
            </th><th scope="col">
            Value
            </th>
            </tr>
            </thead><tbody>
            <tr>
            <th scope="row">
            name
            </th><td>
            &#039;Ada&#039;
            </td>
            </tr>
            </tbody>
            </table>
            </div>
            </div>
            </div>
            HTML,
            RequestSectionRenderer::renderTabs($tabs),
            'Every nested section must be concatenated into the exact tab-panel HTML.',
        );
    }

    public function testRenderTabsWiresPanelIdsAndAriaControls(): void
    {
        $tabs = [
            new RequestTab(label: 'Parameters', sections: []),
            new RequestTab(label: 'Headers', sections: []),
        ];

        self::assertSame(
            <<<HTML
            <ul class="yii-debug-tabs" role="tablist" aria-label="Request data">
            <li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link is-active" id="request-tab-0" href="#request-panel-0" role="tab" tabindex="0" aria-controls="request-panel-0" aria-selected="true" data-yii-debug-toggle="tab">Parameters</a>
            </li><li class="yii-debug-tab" role="presentation">
            <a class="yii-debug-tab-link" id="request-tab-1" href="#request-panel-1" role="tab" tabindex="-1" aria-controls="request-panel-1" aria-selected="false" data-yii-debug-toggle="tab">Headers</a>
            </li>
            </ul><div class="yii-debug-tab-content">
            <div class="yii-debug-tab-panel is-active" id="request-panel-0" role="tabpanel" aria-labelledby="request-tab-0">
            </div><div class="yii-debug-tab-panel" id="request-panel-1" role="tabpanel" aria-labelledby="request-tab-1" hidden>
            </div>
            </div>
            HTML,
            RequestSectionRenderer::renderTabs($tabs),
            "First tab 'href' must point to 'request-panel-0'.",
        );


    }

    public function testRequestSectionDefaultsToNonFilterable(): void
    {
        $section = new RequestSection(caption: 'Headers', entries: ['Accept' => 'text/html']);

        self::assertFalse(
            $section->filterable,
            'Sections must opt in explicitly before the renderer exposes filtering controls.',
        );
    }

    /**
     * @param list<string> $flags
     */
    private static function makeHero(
        string $method = 'GET',
        string $url = 'http://example.test/',
        int $statusCode = 200,
        string $statusVariant = '2xx',
        string $ip = '',
        string $time = '',
        string $durationMs = '',
        array $flags = [],
    ): RequestHero {
        return new RequestHero(
            method: $method,
            url: $url,
            statusCode: $statusCode,
            statusVariant: $statusVariant,
            ip: $ip,
            time: $time,
            durationMs: $durationMs,
            flags: $flags,
        );
    }
}

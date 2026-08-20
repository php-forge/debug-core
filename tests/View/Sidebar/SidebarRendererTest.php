<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Sidebar;

use PHPForge\Debug\View\Sidebar\{SidebarNavItem, SidebarRenderer, SidebarSnapshot, SidebarView};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function substr_count;

/**
 * Unit tests for {@see SidebarRenderer} covering the snapshot card composition (method/URL/status/time/AJAX), the
 * cursor-mode vs navigation-mode branching of the navigator row, and the panel nav entry rendering.
 */
#[Group('view')]
#[Group('sidebar')]
final class SidebarRendererTest extends TestCase
{
    public function testRenderEmitsAriaCurrentOnActiveNavLink(): void
    {
        $view = new SidebarView(
            snapshot: null,
            navItems: [
                new SidebarNavItem(
                    label: 'History',
                    iconSvg: '',
                    url: '/debug/index',
                    tooltip: 'History',
                    isActive: true,
                ),
                new SidebarNavItem(
                    label: 'Request',
                    iconSvg: '',
                    url: '/debug/view?panel=request',
                    tooltip: 'Request',
                    isActive: false,
                ),
            ],
        );

        $html = SidebarRenderer::render($view);

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link is-active" href="/debug/index" title="History" aria-current="page">
            <span class="yii-debug-nav-link-label">
            History
            </span>
            </a>
            </li>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?panel=request" title="Request">
            <span class="yii-debug-nav-link-label">
            Request
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside>
            HTML,
            $html,
            'Active nav entry must carry aria-current.',
        );

        self::assertSame(
            1,
            substr_count($html, 'is-active'),
            'Only the active entry carries the modifier.',
        );
        self::assertMatchesRegularExpression(
            '/<a class="yii-debug-nav-link is-active"[^>]*title="History"[^>]*aria-current="page">/',
            $html,
            'The active link must preserve its base class, tooltip, and current-page marker.',
        );
    }

    public function testRenderEmitsCursorButtonsWhenSnapshotIsCursor(): void
    {
        $view = new SidebarView(snapshot: $this->snapshot(isCursor: true), navItems: []);

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Newest captured request" data-yii-debug-history-cursor="true">
            <header class="yii-debug-side-section-title">
            Newest request
            </header><div class="yii-debug-history-card" title="GET http://example.test/index.php">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="http://example.test/index.php" data-snapshot-field="url">/index.php</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">12:34:56</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax">AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request" data-yii-debug-cursor="newest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request" data-yii-debug-cursor="newer"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Older request" aria-label="Older captured request" data-yii-debug-cursor="older"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Oldest request" aria-label="Oldest captured request" data-yii-debug-cursor="oldest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></button>
            </div>
            </div>
            </section>
            </aside>
            HTML,
            SidebarRenderer::render($view),
            'Cursor mode must emit the Newest cursor button.',
        );
    }

    public function testRenderEmitsHistoryCursorMarkerWhenSnapshotIsCursor(): void
    {
        $view = new SidebarView(snapshot: $this->snapshot(isCursor: true, cursorInitTag: 'init-tag'), navItems: []);

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Newest captured request" data-yii-debug-history-cursor="true" data-yii-debug-cursor-init="init-tag">
            <header class="yii-debug-side-section-title">
            Newest request
            </header><div class="yii-debug-history-card" title="GET http://example.test/index.php">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="http://example.test/index.php" data-snapshot-field="url">/index.php</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">12:34:56</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax">AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request" data-yii-debug-cursor="newest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request" data-yii-debug-cursor="newer"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Older request" aria-label="Older captured request" data-yii-debug-cursor="older"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" type="button" title="Oldest request" aria-label="Oldest captured request" data-yii-debug-cursor="oldest"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></button>
            </div>
            </div>
            </section>
            </aside>
            HTML,
            SidebarRenderer::render($view),
            'Cursor mode must emit a true history-cursor marker.',
        );

    }

    public function testRenderEmitsIconSpanWhenNavItemDeclaresIconSvg(): void
    {
        $view = new SidebarView(
            snapshot: null,
            navItems: [
                new SidebarNavItem(
                    label: 'Request',
                    iconSvg: '<svg data-test="request-icon"></svg>',
                    url: '/debug/view?panel=request',
                    tooltip: 'Request',
                    isActive: false,
                ),
            ],
        );

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <nav class="yii-debug-nav yii-debug-nav-iconed" aria-label="Debug panels">
            <ul>
            <li>
            <a class="yii-debug-nav-link" href="/debug/view?panel=request" title="Request">
            <span class="yii-debug-nav-link-icon" aria-hidden="true">
            <svg data-test="request-icon"></svg>
            </span>
            <span class="yii-debug-nav-link-label">
            Request
            </span>
            </a>
            </li>
            </ul>
            </nav>
            </aside>
            HTML,
            SidebarRenderer::render($view),
            'Nav item with iconSvg must wrap the markup in the icon span.',
        );


    }

    public function testRenderHidesAjaxTagWhenNotAjax(): void
    {
        $view = new SidebarView(snapshot: $this->snapshot(isAjax: false), navItems: []);

        self::assertMatchesRegularExpression(
            '/yii-debug-snapshot-tag[^>]*hidden/',
            SidebarRenderer::render($view),
            'Non-AJAX snapshot must hide the AJAX tag.',
        );
    }

    public function testRenderHidesTimeChipWhenTimeEmpty(): void
    {
        $view = new SidebarView(snapshot: $this->snapshot(time: ''), navItems: []);

        self::assertMatchesRegularExpression(
            '/yii-debug-snapshot-time[^>]*hidden/',
            SidebarRenderer::render($view),
            'Empty time must hide the time chip.',
        );
    }

    public function testRenderShowsDashWhenStatusCodeIsZero(): void
    {
        $view = new SidebarView(snapshot: $this->snapshot(statusCode: 0), navItems: []);

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET http://example.test/index.php">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="http://example.test/index.php" data-snapshot-field="url">/index.php</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">–</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">12:34:56</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax">AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=older" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=oldest" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section>
            </aside>
            HTML,
            SidebarRenderer::render($view),
            'Status 0 must surface as an en-dash placeholder.',
        );
    }

    public function testRenderSkipsSnapshotSectionWhenSnapshotIsNull(): void
    {
        $view = new SidebarView(snapshot: null, navItems: []);

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            </aside>
            HTML,
            SidebarRenderer::render($view),
            'Null snapshot must skip the section entirely.',
        );
    }

    public function testRenderTintsSnapshotMethodAndStatusWithVocabularyClasses(): void
    {
        $html = SidebarRenderer::render(new SidebarView(snapshot: $this->snapshot(), navItems: []));

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET http://example.test/index.php">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="http://example.test/index.php" data-snapshot-field="url">/index.php</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">12:34:56</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax">AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=older" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=oldest" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section>
            </aside>
            HTML,
            $html,
            "GET must wear the 'get' verb class.",
        );

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET http://example.test/index.php">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="http://example.test/index.php" data-snapshot-field="url">/index.php</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-5xx" data-snapshot-field="status">500</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">12:34:56</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax">AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=older" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=oldest" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section>
            </aside>
            HTML,
            SidebarRenderer::render(new SidebarView(snapshot: $this->snapshot(statusCode: 500), navItems: [])),
            "Status '500' must wear the '5xx' status class.",
        );
    }

    public function testRenderWiresNavigationAnchorsInViewMode(): void
    {
        $view = new SidebarView(snapshot: $this->snapshot(isCursor: false), navItems: []);

        $html = SidebarRenderer::render($view);

        self::assertSame(
            <<<HTML
            <aside class="yii-debug-sidebar">
            <section class="yii-debug-side-section yii-debug-request-nav" aria-label="Current request">
            <header class="yii-debug-side-section-title">
            Current request
            </header><div class="yii-debug-history-card" title="GET http://example.test/index.php">
            <div class="yii-debug-snapshot-line">
            <span class="yii-debug-snapshot-method yii-debug-verb-get" data-snapshot-field="method">GET</span><span class="yii-debug-snapshot-url" title="http://example.test/index.php" data-snapshot-field="url">/index.php</span>
            </div><div class="yii-debug-snapshot-meta">
            <span class="yii-debug-snapshot-status yii-debug-status-2xx" data-snapshot-field="status">200</span><span class="yii-debug-snapshot-time" data-snapshot-field="time">12:34:56</span><span class="yii-debug-snapshot-tag" data-snapshot-field="ajax">AJAX</span>
            </div><div class="yii-debug-request-nav-row" role="group">
            <button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newest request" disabled aria-label="Newest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 11l5-5l5 5"/><path d="m7 17l5-5l5 5"/></g></svg></button><button class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon is-disabled" type="button" title="Newer request" disabled aria-label="Newer captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 15l6-6l6 6"/></g></svg></button><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=older" title="Older request" aria-label="Older captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m6 9l6 6l6-6"/></g></svg></a><a class="yii-debug-btn yii-debug-btn-ghost yii-debug-btn-icon" href="/debug/view?tag=oldest" title="Oldest request" aria-label="Oldest captured request"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m7 7l5 5l5-5"/><path d="m7 13l5 5l5-5"/></g></svg></a>
            </div>
            </div>
            </section>
            </aside>
            HTML,
            $html,
            "Navigation mode must use the long 'aria-label' for Newest.",
        );
        self::assertMatchesRegularExpression(
            '/<button class="[^"]*is-disabled"[^>]*aria-label="Newer captured request">/',
            $html,
            'A missing newer capture must render a disabled button.',
        );
        self::assertMatchesRegularExpression(
            '/<a[^>]*href="[^"]*tag=older"[^>]*aria-label="Older captured request">/',
            $html,
            'An available older capture must render an anchor to its tag.',
        );

    }

    private function snapshot(
        bool $isCursor = false,
        bool $isAjax = true,
        int $statusCode = 200,
        string $time = '12:34:56',
        string $cursorInitTag = '',
    ): SidebarSnapshot {
        return new SidebarSnapshot(
            title: $isCursor ? 'Newest request' : 'Current request',
            ariaLabel: $isCursor ? 'Newest captured request' : 'Current request',
            method: 'GET',
            path: '/index.php',
            fullUrl: 'http://example.test/index.php',
            statusCode: $statusCode,
            statusVariant: $statusCode >= 500 ? '5xx' : '2xx',
            time: $time,
            isAjax: $isAjax,
            isCursor: $isCursor,
            cursorInitTag: $cursorInitTag,
            newestUrl: '/debug/view',
            oldestUrl: '/debug/view?tag=oldest',
            newerUrl: '',
            olderUrl: '/debug/view?tag=older',
            isNewest: true,
            isOldest: false,
            hasNewer: false,
            hasOlder: true,
        );
    }
}

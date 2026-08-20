<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\User;

use PHPForge\Debug\Panel\User\{
    UserAttribute,
    UserDataNormalizer,
    UserIdentityHero,
    UserIdentityRenderer,
    UserIdentitySection,
    UserIdentityView,
};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see UserIdentityRenderer} covering the hero card composition, the per-attribute kind branches
 * (plain / security reveal button / timestamp / empty placeholder) and the section header chips.
 */
#[Group('panel')]
#[Group('user')]
final class UserIdentityRendererTest extends TestCase
{
    public function testRenderEmitsTimestampRelativeAndAbsoluteParts(): void
    {
        $view = new UserIdentityView(
            hero: $this->emptyHero(),
            sections: [
                new UserIdentitySection(
                    label: 'Timestamps',
                    icon: '<svg></svg>',
                    attributes: [
                        new UserAttribute(
                            key: 'created_at',
                            label: 'Created At',
                            displayValue: '1640000000',
                            kind: UserAttribute::KIND_TIMESTAMP,
                            timestampRel: '28 d ago',
                            timestampAbs: 'Apr 13, 2026 · 14:19',
                        ),
                    ],
                ),
            ],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            <<<HTML
            <section class="yii-debug-user">
            <header class="yii-debug-user-card">
            <span class="yii-debug-user-avatar" aria-hidden="true">A</span><div class="yii-debug-user-meta">
            <h2 class="yii-debug-user-name">
            admin
            </h2><div class="yii-debug-user-tags">
            </div>
            </div>
            </header><article class="yii-debug-user-section">
            <header>
            <span class="yii-debug-user-section-icon" aria-hidden="true"><svg></svg></span><span>Timestamps</span>
            </header><dl>
            <div class="yii-debug-user-row">
            <dt>
            Created At
            </dt><dd>
            <span class="yii-debug-user-time" title="1640000000"><span class="yii-debug-user-time-rel">28 d ago</span><span class="yii-debug-user-time-abs">Apr 13, 2026 · 14:19</span></span>
            </dd>
            </div>
            </dl>
            </article>
            </section>
            HTML,
            $html,
            'Relative time must surface in the row.',
        );
    }

    public function testRenderEmitsTwoButtonsForSecurityAttributes(): void
    {
        $view = new UserIdentityView(
            hero: $this->emptyHero(),
            sections: [
                new UserIdentitySection(
                    label: 'Security',
                    icon: '<svg></svg>',
                    attributes: [
                        new UserAttribute(
                            key: 'auth_key',
                            label: 'Auth Key',
                            displayValue: 'abc',
                            kind: UserAttribute::KIND_SECURITY,
                        ),
                        new UserAttribute(
                            key: 'password_hash',
                            label: 'Password Hash',
                            displayValue: 'def',
                            kind: UserAttribute::KIND_SECURITY,
                        ),
                    ],
                ),
            ],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            2,
            substr_count($html, '<button'),
            'Each security attribute must render its reveal button.',
        );
        self::assertSame(
            <<<HTML
            <section class="yii-debug-user">
            <header class="yii-debug-user-card">
            <span class="yii-debug-user-avatar" aria-hidden="true">A</span><div class="yii-debug-user-meta">
            <h2 class="yii-debug-user-name">
            admin
            </h2><div class="yii-debug-user-tags">
            </div>
            </div>
            </header><article class="yii-debug-user-section">
            <header>
            <span class="yii-debug-user-section-icon" aria-hidden="true"><svg></svg></span><span>Security</span>
            </header><dl>
            <div class="yii-debug-user-row">
            <dt>
            Auth Key
            </dt><dd>
            <button class="yii-debug-user-reveal" type="button" aria-label="Reveal Auth Key" data-yii-debug-reveal="true"><span class="yii-debug-user-mask">••••••••••••</span><span class="yii-debug-user-real">abc</span><span class="yii-debug-user-reveal-cta" aria-hidden="true"></span></button>
            </dd>
            </div><div class="yii-debug-user-row">
            <dt>
            Password Hash
            </dt><dd>
            <button class="yii-debug-user-reveal" type="button" aria-label="Reveal Password Hash" data-yii-debug-reveal="true"><span class="yii-debug-user-mask">••••••••••••</span><span class="yii-debug-user-real">def</span><span class="yii-debug-user-reveal-cta" aria-hidden="true"></span></button>
            </dd>
            </div>
            </dl>
            </article>
            </section>
            HTML,
            $html,
            'Reveal buttons must carry the JS hook attribute.',
        );
    }

    public function testRenderHeroEmitsAvatarMonogramAndStatusVariant(): void
    {
        $view = new UserIdentityView(
            hero: new UserIdentityHero(
                username: 'admin',
                email: 'admin@example.com',
                idValue: '1',
                monogram: 'A',
                statusLabel: 'Active',
                statusVariant: 'success',
            ),
            sections: [],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            <<<HTML
        <section class="yii-debug-user">
        <header class="yii-debug-user-card">
        <span class="yii-debug-user-avatar" aria-hidden="true">A</span><div class="yii-debug-user-meta">
        <h2 class="yii-debug-user-name">
        admin
        </h2><p class="yii-debug-user-handle">
        admin@example.com
        </p><div class="yii-debug-user-tags">
        <span class="yii-debug-user-status yii-debug-user-status-success">Active</span><span class="yii-debug-user-pill">ID #1</span>
        </div>
        </div>
        </header>
        </section>
        HTML,
            $html,
            'Monogram must render inside the avatar span.',
        );
    }

    public function testRenderHeroOmitsEmailWhenMissing(): void
    {
        $view = new UserIdentityView(
            hero: new UserIdentityHero(
                username: 'admin',
                email: '',
                idValue: '',
                monogram: 'A',
                statusLabel: '',
                statusVariant: 'muted',
            ),
            sections: [],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            <<<'HTML'
        <section class="yii-debug-user">
        <header class="yii-debug-user-card">
        <span class="yii-debug-user-avatar" aria-hidden="true">A</span><div class="yii-debug-user-meta">
        <h2 class="yii-debug-user-name">
        admin
        </h2><div class="yii-debug-user-tags">
        </div>
        </div>
        </header>
        </section>
        HTML,
            $html,
            'Empty email must drop the handle paragraph entirely.',
        );
    }

    public function testRenderHeroOmitsStatusPillWhenLabelEmpty(): void
    {
        $view = new UserIdentityView(
            hero: new UserIdentityHero(
                username: 'admin',
                email: '',
                idValue: '',
                monogram: 'A',
                statusLabel: '',
                statusVariant: 'muted',
            ),
            sections: [],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            <<<HTML
            <section class="yii-debug-user">
            <header class="yii-debug-user-card">
            <span class="yii-debug-user-avatar" aria-hidden="true">A</span><div class="yii-debug-user-meta">
            <h2 class="yii-debug-user-name">
            admin
            </h2><div class="yii-debug-user-tags">
            </div>
            </div>
            </header>
            </section>
            HTML,
            $html,
            'Empty status label must drop the status pill.',
        );
    }

    public function testRenderSurfacesEmptyDashForEmptyAttribute(): void
    {
        $view = new UserIdentityView(
            hero: $this->emptyHero(),
            sections: [
                new UserIdentitySection(
                    label: 'Security',
                    icon: '<svg></svg>',
                    attributes: [
                        new UserAttribute(
                            key: 'token',
                            label: 'Token',
                            displayValue: '',
                            kind: UserAttribute::KIND_EMPTY,
                        ),
                    ],
                ),
            ],
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            <<<HTML
            <section class="yii-debug-user">
            <header class="yii-debug-user-card">
            <span class="yii-debug-user-avatar" aria-hidden="true">A</span><div class="yii-debug-user-meta">
            <h2 class="yii-debug-user-name">
            admin
            </h2><div class="yii-debug-user-tags">
            </div>
            </div>
            </header><article class="yii-debug-user-section">
            <header>
            <span class="yii-debug-user-section-icon" aria-hidden="true"><svg></svg></span><span>Security</span>
            </header><dl>
            <div class="yii-debug-user-row">
            <dt>
            Token
            </dt><dd>
            <span class="yii-debug-user-empty">—</span>
            </dd>
            </div>
            </dl>
            </article>
            </section>
            HTML,
            $html,
            'Empty rows must surface the dedicated CSS class.',
        );
    }

    public function testRenderWiresFullPipelineThroughNormalizer(): void
    {
        $view = UserDataNormalizer::fromIdentity(
            [
                'id' => "'1'",
                'username' => "'admin'",
                'status' => '10',
                'email' => "'admin@example.com'",
                'auth_key' => "'authkey-12345'",
                'created_at' => '1640000000',
            ],
            null,
        );

        $html = UserIdentityRenderer::render($view);

        self::assertSame(
            <<<HTML
            <section class="yii-debug-user">
            <header class="yii-debug-user-card">
            <span class="yii-debug-user-avatar" aria-hidden="true">A</span><div class="yii-debug-user-meta">
            <h2 class="yii-debug-user-name">
            admin
            </h2><p class="yii-debug-user-handle">
            admin@example.com
            </p><div class="yii-debug-user-tags">
            <span class="yii-debug-user-status yii-debug-user-status-success">Active</span><span class="yii-debug-user-pill">ID #1</span>
            </div>
            </div>
            </header><article class="yii-debug-user-section">
            <header>
            <span class="yii-debug-user-section-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c1-4 4-6 7-6s6 2 7 6"/></svg></span><span>Identity</span>
            </header><dl>
            <div class="yii-debug-user-row">
            <dt>
            Id
            </dt><dd>
            <span class="yii-debug-user-value">1</span>
            </dd>
            </div><div class="yii-debug-user-row">
            <dt>
            Username
            </dt><dd>
            <span class="yii-debug-user-value">admin</span>
            </dd>
            </div><div class="yii-debug-user-row">
            <dt>
            Email
            </dt><dd>
            <span class="yii-debug-user-value">admin@example.com</span>
            </dd>
            </div>
            </dl>
            </article><article class="yii-debug-user-section">
            <header>
            <span class="yii-debug-user-section-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z"/><path d="M9.5 12l2 2 3.5-4"/></svg></span><span>Security</span>
            </header><dl>
            <div class="yii-debug-user-row">
            <dt>
            Auth Key
            </dt><dd>
            <button class="yii-debug-user-reveal" type="button" aria-label="Reveal Auth Key" data-yii-debug-reveal="true"><span class="yii-debug-user-mask">••••••••••••</span><span class="yii-debug-user-real">authkey-12345</span><span class="yii-debug-user-reveal-cta" aria-hidden="true"></span></button>
            </dd>
            </div>
            </dl>
            </article><article class="yii-debug-user-section">
            <header>
            <span class="yii-debug-user-section-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span>Timestamps</span>
            </header><dl>
            <div class="yii-debug-user-row">
            <dt>
            Created At
            </dt><dd>
            <span class="yii-debug-user-time" title="1640000000"><span class="yii-debug-user-time-rel">Dec 20, 2021 · 11:33</span><span class="yii-debug-user-time-abs">Dec 20, 2021 · 11:33</span></span>
            </dd>
            </div>
            </dl>
            </article>
            </section>
            HTML,
            $html,
            'End-to-end view must surface the user-name heading.',
        );
    }

    private function emptyHero(): UserIdentityHero
    {
        return new UserIdentityHero(
            username: 'admin',
            email: '',
            idValue: '',
            monogram: 'A',
            statusLabel: '',
            statusVariant: 'muted',
        );
    }
}

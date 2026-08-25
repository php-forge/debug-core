<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Mail;

use PHPForge\Debug\Panel\Mail\{MailCardRenderer, MailMessage};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Xepozz\InternalMocker\MockerState;

use function ini_set;

/**
 * Unit tests for {@see MailCardRenderer} covering the typed mail card composition: avatar / headline / meta line,
 * recipient pills, body block, status pill, time line, download link and the optional raw-headers details block.
 */
#[Group('panel')]
#[Group('mail')]
final class MailCardRendererTest extends TestCase
{
    private const int NOW = 1_700_000_000;

    public function testRenderItemAvatarFallsBackToFixedHueWhenSenderIsEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: ''),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Empty sender must fall back to hue '210'.",
        );
    }

    public function testRenderItemBodyPreviewUsesUnicodeCharacterBoundaries(): void
    {
        $exact = MailCardRenderer::renderItem(
            self::makeMessage(body: str_repeat('é', 140)),
            self::makeUrlBuilder(),
        );
        $long = MailCardRenderer::renderItem(
            self::makeMessage(body: 'É' . str_repeat('é', 140)),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé
            </div>
            </article>
            HTML,
            $exact,
            'Exactly 140 Unicode characters must remain complete and omit the ellipsis.',
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé…</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé
            </div>
            </article>
            HTML,
            $long,
            'Long Unicode previews must start at the first character and truncate on a character boundary.',
        );
    }

    public function testRenderItemDegradesToEmptyMarkupWhenPcreFails(): void
    {
        $previous = ini_set('pcre.backtrack_limit', '1');

        try {
            $html = MailCardRenderer::renderItem(
                self::makeMessage(),
                self::makeUrlBuilder(),
            );
        } finally {
            ini_set('pcre.backtrack_limit', (string) $previous);
        }

        self::assertSame(
            '',
            $html,
            'A PCRE failure must degrade the card to an empty string instead of erroring.',
        );
    }

    public function testRenderItemEscapesBodyContent(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: '<script>alert(1)</script>'),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">&lt;script&gt;alert(1)&lt;/script&gt;</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            &lt;script&gt;alert(1)&lt;/script&gt;
            </div>
            </article>
            HTML,
            $html,
            'Body must be HTML-escaped.',
        );
    }

    public function testRenderItemFormatsRelativeTimeForDaysAgoDelta(): void
    {
        self::freezeTime();

        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: self::NOW - (3 * 86400)),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><span class="yii-debug-mail-time" title="Nov 11, 2023 · 22:13:20">3 d ago</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Days delta must read 'X d ago'.",
        );
    }

    public function testRenderItemFormatsRelativeTimeForHoursAgoDelta(): void
    {
        self::freezeTime();

        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: self::NOW - 7200),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><span class="yii-debug-mail-time" title="Nov 14, 2023 · 20:13:20">2 h ago</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Hours delta must read 'X h ago'.",
        );
    }

    public function testRenderItemFormatsRelativeTimeForJustNowDelta(): void
    {
        self::freezeTime();

        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: self::NOW),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><span class="yii-debug-mail-time" title="Nov 14, 2023 · 22:13:20">just now</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Under-minute delta must read 'just now'.",
        );
    }

    public function testRenderItemFormatsRelativeTimeForMinutesAgoDelta(): void
    {
        self::freezeTime();

        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: self::NOW - 600),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><span class="yii-debug-mail-time" title="Nov 14, 2023 · 22:03:20">10 min ago</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Minutes delta must read 'X min ago'.",
        );
    }

    public function testRenderItemOmitsBodyPreviewWhenBodyIsEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body yii-debug-mail-body-empty">
            (empty body)
            </div>
            </article>
            HTML,
            MailCardRenderer::renderItem(self::makeMessage(body: ''), self::makeUrlBuilder()),
            'Empty body must omit the preview span.',
        );
    }

    public function testRenderItemOmitsDownloadLinkWhenFileIsEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            MailCardRenderer::renderItem(self::makeMessage(file: ''), self::makeUrlBuilder()),
            'Empty file must omit the download link.',
        );
    }

    public function testRenderItemOmitsRecipientBlockWhenAllListsAreEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            MailCardRenderer::renderItem(self::makeMessage(), self::makeUrlBuilder()),
            'Empty recipient lists must omit the block.',
        );
    }

    public function testRenderItemOmitsTechDetailsWhenBothHeadersAndCharsetAreEmpty(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            MailCardRenderer::renderItem(self::makeMessage(headers: '', charset: ''), self::makeUrlBuilder()),
            'Empty headers and charset must omit the tech details.',
        );
    }

    public function testRenderItemOmitsTimeWhenNull(): void
    {
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            MailCardRenderer::renderItem(self::makeMessage(time: null), self::makeUrlBuilder()),
            "'null' time must omit the time span.",
        );
    }

    public function testRenderItemRendersAvatarHueDeterministicallyFromSender(): void
    {
        $first = MailCardRenderer::renderItem(
            self::makeMessage(from: 'a@example.com'),
            self::makeUrlBuilder(),
        );
        $second = MailCardRenderer::renderItem(
            self::makeMessage(from: 'a@example.com'),
            self::makeUrlBuilder(),
        );
        $third = MailCardRenderer::renderItem(
            self::makeMessage(from: 'b@example.com'),
            self::makeUrlBuilder(),
        );

        self::assertMatchesRegularExpression(
            '/--mail-hue: \d+/',
            $first,
            'Avatar must carry an inline hue style.',
        );
        self::assertSame(
            self::extractHue($first),
            self::extractHue($second),
            'Same sender must produce the same hue.',
        );
        self::assertNotSame(
            self::extractHue($first),
            self::extractHue($third),
            'Different senders must produce different hues.',
        );
    }

    public function testRenderItemRendersBodyPreviewTruncatedAt140Characters(): void
    {
        $longBody = str_repeat('Lorem ipsum dolor sit amet, ', 20);

        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: $longBody),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Lorem ipsum dolor sit amet, Lorem ipsum dolor sit amet, Lorem ipsum dolor sit amet, Lorem ipsum dolor sit amet, Lorem ipsum dolor sit amet, …</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            {$longBody}
            </div>
            </article>
            HTML,
            $html,
            'Preview span must be present when body is non-empty.',
        );

    }

    public function testRenderItemRendersDownloadLinkWhenFileIsSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(file: '/tmp/mail.eml'),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><a class="yii-debug-mail-download" href="/debug/download-mail?file=/tmp/mail.eml" title="Download .eml" aria-label="Download .eml"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v12"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/></svg></a>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            'Download link must carry the dedicated class.',
        );
    }

    public function testRenderItemRendersEachRecipientGroupWhenItIsTheOnlyPopulatedList(): void
    {
        $cases = [
            'to' => [
                ['only-to@example.com'],
                [],
                [],
                [],
                'to',
                'TO',
                'only-to@example.com',
            ],
            'cc' => [
                [],
                ['only-cc@example.com'],
                [],
                [],
                'cc',
                'CC',
                'only-cc@example.com',
            ],
            'bcc' => [
                [],
                [],
                ['only-bcc@example.com'],
                [],
                'bcc',
                'BCC',
                'only-bcc@example.com',
            ],
            'reply' => [
                [],
                [],
                [],
                ['only-reply@example.com'],
                'reply',
                'REPLY-TO',
                'only-reply@example.com',
            ],
        ];

        foreach ($cases as $name => [$to, $cc, $bcc, $replyTo, $role, $label, $address]) {
            $html = MailCardRenderer::renderItem(
                self::makeMessage(to: $to, cc: $cc, bcc: $bcc, replyTo: $replyTo),
                self::makeUrlBuilder(),
            );

            self::assertSame(
                <<<HTML
                <article class="yii-debug-mail-card">
                <header class="yii-debug-mail-card-head">
                <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
                <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
                Test subject
                </h2><span class="yii-debug-mail-preview">Test body</span>
                </div><div class="yii-debug-mail-meta">
                <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
                </div>
                </header><div class="yii-debug-mail-recipients">
                <div class="yii-debug-mail-recipient-group">
                <span class="yii-debug-mail-recipient-label" data-role="{$role}">{$label}</span><span class="yii-debug-mail-recipient-pills"><span class="yii-debug-mail-recipient-pill" title="{$address}">{$address}</span></span>
                </div>
                </div><div class="yii-debug-mail-body">
                Test body
                </div>
                </article>
                HTML,
                $html,
                "{$name} alone must render the exact recipient card markup.",
            );
        }
    }

    public function testRenderItemRendersEmptyBodyPlaceholderWhenBodyIsEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: ''),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body yii-debug-mail-body-empty">
            (empty body)
            </div>
            </article>
            HTML,
            $html,
            'Empty body must use the empty-body modifier.',
        );

    }

    public function testRenderItemRendersFallbackPlaceholdersWhenFromOrSubjectAreEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: '', subject: ''),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            (no subject)
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Empty from must fall back to '(no sender)'.",
        );
    }

    public function testRenderItemRendersFromAndSubject(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: 'sender@example.com', subject: 'Welcome'),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 191' aria-hidden="true">S</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">sender@example.com</span><h2 class="yii-debug-mail-subject">
            Welcome
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            'Sender address must be visible.',
        );
    }

    public function testRenderItemRendersRecipientGroupsWithLabelsAndPills(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(
                to: [
                    'a@example.com',
                    'b@example.com',
                ],
                cc: ['carbon@example.com'],
                bcc: ['bcc@example.com'],
                replyTo: ['reply@example.com'],
            ),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-recipients">
            <div class="yii-debug-mail-recipient-group">
            <span class="yii-debug-mail-recipient-label" data-role="to">TO</span><span class="yii-debug-mail-recipient-pills"><span class="yii-debug-mail-recipient-pill" title="a@example.com">a@example.com</span><span class="yii-debug-mail-recipient-pill" title="b@example.com">b@example.com</span></span>
            </div><div class="yii-debug-mail-recipient-group">
            <span class="yii-debug-mail-recipient-label" data-role="cc">CC</span><span class="yii-debug-mail-recipient-pills"><span class="yii-debug-mail-recipient-pill" title="carbon@example.com">carbon@example.com</span></span>
            </div><div class="yii-debug-mail-recipient-group">
            <span class="yii-debug-mail-recipient-label" data-role="bcc">BCC</span><span class="yii-debug-mail-recipient-pills"><span class="yii-debug-mail-recipient-pill" title="bcc@example.com">bcc@example.com</span></span>
            </div><div class="yii-debug-mail-recipient-group">
            <span class="yii-debug-mail-recipient-label" data-role="reply">REPLY-TO</span><span class="yii-debug-mail-recipient-pills"><span class="yii-debug-mail-recipient-pill" title="reply@example.com">reply@example.com</span></span>
            </div>
            </div><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            'Recipients wrapper must be present.',
        );
        self::assertSame(
            5,
            substr_count($html, 'class="yii-debug-mail-recipient-pill"'),
            'Every declared recipient must be wrapped in its own pill.',
        );
    }

    public function testRenderItemRendersStatusFailWhenIsSuccessfulIsFalse(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(isSuccessful: false),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-fail" title="Mailer reported failure"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Failed</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Failed messages must use the 'fail' variant.",
        );
    }

    public function testRenderItemRendersStatusOkWhenIsSuccessfulIsTrue(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(isSuccessful: true),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Successful messages must use the 'ok' variant.",
        );
    }

    public function testRenderItemRendersTechDetailsWhenHeadersOrCharsetSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(headers: 'X-Foo: bar', charset: 'UTF-8'),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div><details class="yii-debug-mail-tech">
            <summary>
            <span class="yii-debug-mail-tech-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg></span><span class="yii-debug-mail-tech-label">Raw headers</span><span class="yii-debug-mail-tech-charset" title="Charset">UTF-8</span><span class="yii-debug-mail-tech-chevron" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
            </summary><pre class="yii-debug-mail-headers">
            X-Foo: bar
            </pre>
            </details>
            </article>
            HTML,
            $html,
            'Tech details wrapper must be present.',
        );
    }

    public function testRenderItemRendersTechDetailsWhenOnlyOneTechnicalFieldIsSet(): void
    {
        $headersOnly = MailCardRenderer::renderItem(
            self::makeMessage(headers: 'X-Only: header', charset: ''),
            self::makeUrlBuilder(),
        );
        $charsetOnly = MailCardRenderer::renderItem(
            self::makeMessage(headers: '', charset: 'UTF-16'),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div><details class="yii-debug-mail-tech">
            <summary>
            <span class="yii-debug-mail-tech-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg></span><span class="yii-debug-mail-tech-label">Raw headers</span><span class="yii-debug-mail-tech-chevron" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
            </summary><pre class="yii-debug-mail-headers">
            X-Only: header
            </pre>
            </details>
            </article>
            HTML,
            $headersOnly,
            'Headers alone must render details.',
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div><details class="yii-debug-mail-tech">
            <summary>
            <span class="yii-debug-mail-tech-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6L4 12l5 6"/><path d="M15 6l5 6-5 6"/></svg></span><span class="yii-debug-mail-tech-label">Raw headers</span><span class="yii-debug-mail-tech-charset" title="Charset">UTF-16</span><span class="yii-debug-mail-tech-chevron" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></span>
            </summary><pre class="yii-debug-mail-headers">
            </pre>
            </details>
            </article>
            HTML,
            $charsetOnly,
            'Charset alone must render details.',
        );
    }

    public function testRenderItemRendersTimeWhenSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: 1_700_000_000),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><span class="yii-debug-mail-time" title="Nov 14, 2023 · 22:13:20">Nov 14, 2023 · 22:13:20</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            'Time span must carry the dedicated class.',
        );
        self::assertMatchesRegularExpression(
            '/title="[A-Z][a-z]{2} \d/',
            $html,
            'Time tooltip must contain the formatted absolute date.'
        );
    }

    public function testRenderItemRendersUnicodeAndEmptyLocalPartInitials(): void
    {
        $unicode = MailCardRenderer::renderItem(
            self::makeMessage(from: 'élise@example.com'),
            self::makeUrlBuilder(),
        );
        $emptyLocal = MailCardRenderer::renderItem(
            self::makeMessage(from: '@example.com'),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 76' aria-hidden="true">É</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">élise@example.com</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $unicode,
            'Unicode initials must be sliced and uppercased safely.',
        );
        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 82' aria-hidden="true">@</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">@example.com</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $emptyLocal,
            'An empty local part must fall back to the full address.',
        );
    }

    public function testRenderItemRendersUppercasedFirstLetterOfLocalPartAsInitial(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: 'wilmer@example.com'),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 204' aria-hidden="true">W</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">wilmer@example.com</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            'Initial must be the uppercased first letter of the local part.',
        );
    }

    public function testRenderItemSkipsRecipientGroupsThatAreEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(to: ['user@example.com'], cc: [], bcc: [], replyTo: []),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-recipients">
            <div class="yii-debug-mail-recipient-group">
            <span class="yii-debug-mail-recipient-label" data-role="to">TO</span><span class="yii-debug-mail-recipient-pills"><span class="yii-debug-mail-recipient-pill" title="user@example.com">user@example.com</span></span>
            </div>
            </div><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            "Populated 'TO' group must be rendered.",
        );

    }

    public function testRenderItemUsesExactRelativeTimeBoundariesAndUnits(): void
    {
        self::freezeTime();

        $cases = [
            ['1 min ago', 60, 'the minute boundary'],
            ['1 h ago', 3600, 'the hour boundary'],
            ['1 d ago', 86400, 'the day boundary'],
            ['1 min ago', 118, 'a non-divisible minute delta'],
            ['1 h ago', 7198, 'a non-divisible hour delta'],
            ['1 d ago', 172798, 'a non-divisible day delta'],
        ];

        foreach ($cases as [$expected, $diff, $description]) {
            $time = self::NOW - $diff;

            $absoluteTime = date('M j, Y · H:i:s', $time);

            $html = MailCardRenderer::renderItem(
                self::makeMessage(time: $time),
                self::makeUrlBuilder(),
            );

            self::assertSame(
                <<<HTML
                <article class="yii-debug-mail-card">
                <header class="yii-debug-mail-card-head">
                <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
                <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
                Test subject
                </h2><span class="yii-debug-mail-preview">Test body</span>
                </div><div class="yii-debug-mail-meta">
                <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><span class="yii-debug-mail-time" title="{$absoluteTime}">{$expected}</span>
                </div>
                </header><div class="yii-debug-mail-body">
                Test body
                </div>
                </article>
                HTML,
                $html,
                "Relative time must render the exact card markup for {$description}.",
            );
        }

        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: self::NOW - 2_592_000),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span><span class="yii-debug-mail-time" title="Oct 15, 2023 · 22:13:20">Oct 15, 2023 · 22:13:20</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            'Thirty days must switch to the absolute label.',
        );
    }

    public function testRenderItemWrapsContentInArticleWithMailCardClass(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(),
            self::makeUrlBuilder(),
        );

        self::assertSame(
            <<<HTML
            <article class="yii-debug-mail-card">
            <header class="yii-debug-mail-card-head">
            <span class="yii-debug-mail-avatar" style='--mail-hue: 210' aria-hidden="true">?</span><div class="yii-debug-mail-headline">
            <span class="yii-debug-mail-from">(no sender)</span><h2 class="yii-debug-mail-subject">
            Test subject
            </h2><span class="yii-debug-mail-preview">Test body</span>
            </div><div class="yii-debug-mail-meta">
            <span class="yii-debug-mail-status yii-debug-mail-status-ok" title="Mailer reported success"><span class="yii-debug-mail-status-dot" aria-hidden="true"></span> Sent</span>
            </div>
            </header><div class="yii-debug-mail-body">
            Test body
            </div>
            </article>
            HTML,
            $html,
            'Outer wrapper class must be present.',
        );
    }

    /**
     * Extracts the avatar hue value from rendered HTML for hue-stability assertions.
     */
    private static function extractHue(string $html): int
    {
        if (preg_match('/--mail-hue: (\d+)/', $html, $m) === 1) {
            return (int) $m[1];
        }

        self::fail('No avatar hue found in rendered HTML.');
    }

    private static function freezeTime(): void
    {
        MockerState::addCondition(
            'PHPForge\\Debug\\Panel\\Mail',
            'time',
            [],
            self::NOW,
            true,
        );
    }

    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<string> $replyTo
     */
    private static function makeMessage(
        string $from = '',
        array $to = [],
        array $cc = [],
        array $bcc = [],
        array $replyTo = [],
        string $subject = 'Test subject',
        string $body = 'Test body',
        string $headers = '',
        string $charset = '',
        string $file = '',
        bool $isSuccessful = true,
        int|null $time = null,
    ): MailMessage {
        return new MailMessage(
            from: $from,
            to: $to,
            cc: $cc,
            bcc: $bcc,
            replyTo: $replyTo,
            subject: $subject,
            body: $body,
            headers: $headers,
            charset: $charset,
            file: $file,
            isSuccessful: $isSuccessful,
            time: $time,
        );
    }

    /**
     * Builds a deterministic download-URL builder so tests can assert the rendered href without needing an active
     * controller context.
     *
     * @return callable(string): string
     */
    private static function makeUrlBuilder(): callable
    {
        return static fn(string $file): string => "/debug/download-mail?file={$file}";
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Mail;

use PHPForge\Debug\Panel\Mail\{MailCardRenderer, MailMessage};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Xepozz\InternalMocker\MockerState;

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

        self::assertStringContainsString(
            '--mail-hue: 210',
            $html,
            "Empty sender must fall back to hue '210'.",
        );
        self::assertStringContainsString(
            '>?<',
            $html,
            "Empty sender must render '?' as the initial.",
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

        self::assertStringContainsString(
            'class="yii-debug-mail-preview">' . str_repeat('é', 140) . '</span>',
            $exact,
            'Exactly 140 Unicode characters must remain complete and omit the ellipsis.',
        );
        self::assertStringNotContainsString('…', $exact, 'The exact preview limit must not be treated as overflow.');
        self::assertStringContainsString(
            'class="yii-debug-mail-preview">É' . str_repeat('é', 139) . '…</span>',
            $long,
            'Long Unicode previews must start at the first character and truncate on a character boundary.',
        );
    }

    public function testRenderItemEscapesBodyContent(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: '<script>alert(1)</script>'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Body must be HTML-escaped.',
        );
        self::assertStringNotContainsString(
            '<script>alert',
            $html,
            'Raw script tags must not leak into the output.',
        );
    }

    public function testRenderItemFormatsRelativeTimeForDaysAgoDelta(): void
    {
        self::freezeTime();

        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: self::NOW - (3 * 86400)),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '3 d ago',
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

        self::assertStringContainsString(
            '2 h ago',
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

        self::assertStringContainsString(
            'just now',
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

        self::assertStringContainsString(
            '10 min ago',
            $html,
            "Minutes delta must read 'X min ago'.",
        );
    }

    public function testRenderItemOmitsBodyPreviewWhenBodyIsEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-preview',
            MailCardRenderer::renderItem(self::makeMessage(body: ''), self::makeUrlBuilder()),
            'Empty body must omit the preview span.',
        );
    }

    public function testRenderItemOmitsDownloadLinkWhenFileIsEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-download',
            MailCardRenderer::renderItem(self::makeMessage(file: ''), self::makeUrlBuilder()),
            'Empty file must omit the download link.',
        );
    }

    public function testRenderItemOmitsRecipientBlockWhenAllListsAreEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-recipients',
            MailCardRenderer::renderItem(self::makeMessage(), self::makeUrlBuilder()),
            'Empty recipient lists must omit the block.',
        );
    }

    public function testRenderItemOmitsTechDetailsWhenBothHeadersAndCharsetAreEmpty(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-tech',
            MailCardRenderer::renderItem(self::makeMessage(headers: '', charset: ''), self::makeUrlBuilder()),
            'Empty headers and charset must omit the tech details.',
        );
    }

    public function testRenderItemOmitsTimeWhenNull(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-mail-time',
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

        self::assertStringContainsString(
            'class="yii-debug-mail-preview"',
            $html,
            'Preview span must be present when body is non-empty.',
        );
        self::assertStringContainsString(
            '…',
            $html,
            'Long previews must end with an ellipsis.',
        );
    }

    public function testRenderItemRendersDownloadLinkWhenFileIsSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(file: '/tmp/mail.eml'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-download"',
            $html,
            'Download link must carry the dedicated class.',
        );
        self::assertStringContainsString(
            'href="/debug/download-mail?file=/tmp/mail.eml"',
            $html,
            'Download href must round-trip the URL builder output.',
        );
    }

    public function testRenderItemRendersEachRecipientGroupWhenItIsTheOnlyPopulatedList(): void
    {
        $cases = [
            'to' => [['only-to@example.com'], [], [], [], 'data-role="to"'],
            'cc' => [[], ['only-cc@example.com'], [], [], 'data-role="cc"'],
            'bcc' => [[], [], ['only-bcc@example.com'], [], 'data-role="bcc"'],
            'reply' => [[], [], [], ['only-reply@example.com'], 'data-role="reply"'],
        ];

        foreach ($cases as $name => [$to, $cc, $bcc, $replyTo, $role]) {
            $html = MailCardRenderer::renderItem(
                self::makeMessage(to: $to, cc: $cc, bcc: $bcc, replyTo: $replyTo),
                self::makeUrlBuilder(),
            );

            self::assertStringContainsString('yii-debug-mail-recipients', $html, "{$name} alone must render recipients.");
            self::assertStringContainsString($role, $html, "{$name} alone must render its role label.");
            self::assertSame(
                1,
                substr_count($html, 'class="yii-debug-mail-recipient-pill"'),
                "{$name} must render one pill.",
            );
        }
    }

    public function testRenderItemRendersEmptyBodyPlaceholderWhenBodyIsEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(body: ''),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'yii-debug-mail-body-empty',
            $html,
            'Empty body must use the empty-body modifier.',
        );
        self::assertStringContainsString(
            '(empty body)',
            $html,
            'Empty body placeholder must be visible.',
        );
    }

    public function testRenderItemRendersFallbackPlaceholdersWhenFromOrSubjectAreEmpty(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: '', subject: ''),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '(no sender)',
            $html,
            "Empty from must fall back to '(no sender)'.",
        );
        self::assertStringContainsString(
            '(no subject)',
            $html,
            "Empty subject must fall back to '(no subject)'.",
        );
    }

    public function testRenderItemRendersFromAndSubject(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: 'sender@example.com', subject: 'Welcome'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'sender@example.com',
            $html,
            'Sender address must be visible.',
        );
        self::assertStringContainsString(
            'Welcome',
            $html,
            'Subject must be visible.',
        );
    }

    public function testRenderItemRendersRecipientGroupsWithLabelsAndPills(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(
                to: ['a@example.com', 'b@example.com'],
                cc: ['carbon@example.com'],
                bcc: ['bcc@example.com'],
                replyTo: ['reply@example.com'],
            ),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-recipients"',
            $html,
            'Recipients wrapper must be present.',
        );
        self::assertStringContainsString(
            'TO',
            $html,
            'TO label must be present.',
        );
        self::assertStringContainsString(
            'CC',
            $html,
            'CC label must be present.',
        );
        self::assertStringContainsString(
            'BCC',
            $html,
            'BCC label must be present.',
        );
        self::assertStringContainsString(
            'REPLY-TO',
            $html,
            'REPLY-TO label must be present.',
        );
        self::assertStringContainsString(
            'a@example.com',
            $html,
            'TO pill must include the address.',
        );
        self::assertStringContainsString(
            'title="carbon@example.com">carbon@example.com</span>',
            $html,
            'CC pill must include the address.',
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

        self::assertStringContainsString(
            'yii-debug-mail-status-fail',
            $html,
            "Failed messages must use the 'fail' variant.",
        );
        self::assertStringContainsString(
            'Failed',
            $html,
            "Status label must read 'Failed'.",
        );
        self::assertStringContainsString(
            'title="Mailer reported failure"',
            $html,
            'Failed status must retain its failure tooltip.',
        );
    }

    public function testRenderItemRendersStatusOkWhenIsSuccessfulIsTrue(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(isSuccessful: true),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'yii-debug-mail-status-ok',
            $html,
            "Successful messages must use the 'ok' variant.",
        );
        self::assertStringContainsString(
            'Sent',
            $html,
            "Status label must read 'Sent'.",
        );
        self::assertStringContainsString(
            'title="Mailer reported success"',
            $html,
            'Successful status must retain its success tooltip.',
        );
    }

    public function testRenderItemRendersTechDetailsWhenHeadersOrCharsetSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(headers: 'X-Foo: bar', charset: 'UTF-8'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-tech"',
            $html,
            'Tech details wrapper must be present.',
        );
        self::assertStringContainsString(
            'class="yii-debug-mail-tech-icon"',
            $html,
            'Technical details must retain the code icon.',
        );
        self::assertStringContainsString(
            'Raw headers',
            $html,
            'Tech summary label must be present.',
        );
        self::assertStringContainsString(
            'X-Foo: bar',
            $html,
            'Header content must be visible.',
        );
        self::assertStringContainsString(
            'UTF-8',
            $html,
            'Charset must be visible in the summary.',
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

        self::assertStringContainsString('yii-debug-mail-tech', $headersOnly, 'Headers alone must render details.');
        self::assertStringContainsString('X-Only: header', $headersOnly, 'Header-only details must retain the value.');
        self::assertStringContainsString('yii-debug-mail-tech', $charsetOnly, 'Charset alone must render details.');
        self::assertStringContainsString('UTF-16', $charsetOnly, 'Charset-only details must retain the value.');
    }

    public function testRenderItemRendersTimeWhenSet(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: 1_700_000_000),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-time"',
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

        self::assertStringContainsString('>É<', $unicode, 'Unicode initials must be sliced and uppercased safely.');
        self::assertStringContainsString('>@<', $emptyLocal, 'An empty local part must fall back to the full address.');
    }

    public function testRenderItemRendersUppercasedFirstLetterOfLocalPartAsInitial(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(from: 'wilmer@example.com'),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            '>W<',
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

        self::assertStringContainsString(
            'user@example.com',
            $html,
            "Populated 'TO' group must be rendered.",
        );
        self::assertStringNotContainsString(
            'CC',
            $html,
            "Empty 'CC' group must be skipped.",
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
            $html = MailCardRenderer::renderItem(
                self::makeMessage(time: self::NOW - $diff),
                self::makeUrlBuilder(),
            );

            self::assertStringContainsString(
                ">{$expected}<",
                $html,
                "Relative time must use the canonical unit for {$description}.",
            );
        }

        $absolute = date('M j, Y · H:i:s', self::NOW - 2_592_000);
        $html = MailCardRenderer::renderItem(
            self::makeMessage(time: self::NOW - 2_592_000),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(">{$absolute}<", $html, 'Thirty days must switch to the absolute label.');
        self::assertStringNotContainsString('30 d ago', $html, 'The thirty-day boundary must not stay relative.');
    }

    public function testRenderItemWrapsContentInArticleWithMailCardClass(): void
    {
        $html = MailCardRenderer::renderItem(
            self::makeMessage(),
            self::makeUrlBuilder(),
        );

        self::assertStringContainsString(
            'class="yii-debug-mail-card"',
            $html,
            'Outer wrapper class must be present.',
        );
        self::assertStringContainsString(
            'class="yii-debug-mail-card-head"',
            $html,
            'Head wrapper class must be present.',
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

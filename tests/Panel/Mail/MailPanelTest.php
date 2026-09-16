<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Mail;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\Mail\{MailPanel, MailSnapshot};
use PHPForge\Debug\Presenter\{DisclosureBlock, ParagraphBlock, ToolbarMetric};
use PHPForge\Debug\Tests\Support\PanelViewAccessors;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function date;

/**
 * Unit tests for {@see MailPanel} covering metadata, the summary table, and the per-message detail groups.
 */
#[Group('panel')]
#[Group('mail')]
final class MailPanelTest extends TestCase
{
    use PanelViewAccessors;

    /**
     * @var int Capture timestamp shared by the populated messages.
     */
    private const int TIME = 1_757_700_000;

    public function testEmptyCaptureDeactivatesThePanelAndExplainsTheMissingMessages(): void
    {
        $view = self::present();

        self::assertFalse(
            $view->isActive(),
            'An empty inbox must not activate navigation.',
        );
        self::assertSame(
            [],
            $view->summaryMetrics(),
            'An empty inbox must carry no summary metric.',
        );
        self::assertSame(
            [],
            $view->toolbarMetrics(),
            'An empty inbox must carry no toolbar metric.',
        );

        $state = self::emptyState(self::blockAt($view, 0));

        self::assertCount(
            1,
            $view->blocks(),
            'The empty state must replace the table and the groups.',
        );
        self::assertSame(
            'No emails sent in this request',
            $state->title,
            'The empty state must keep its heading.',
        );
        self::assertSame(
            ['This request did not dispatch any messages through the mailer, so the inbox is empty.'],
            self::inlineValues($state->paragraphs[0] ?? self::fail('The empty state must explain itself.')),
            'The first paragraph must describe the empty inbox.',
        );
        self::assertSame(
            [
                'BaseMailer::EVENT_AFTER_SEND',
                ' is the capture hook; only requests that call ',
                '$mailer->send()',
                ' populate this view.',
            ],
            self::inlineValues($state->paragraphs[1] ?? self::fail('The empty state must name the capture hook.')),
            'The capture hook explanation must stay complete and ordered.',
        );
    }

    public function testEmptyEnvelopeFieldsFallBackToPlaceholders(): void
    {
        $view = self::present(
            [
                'from' => '',
                'to' => '',
                'subject' => '',
                'body' => '',
                'headers' => '',
                'isSuccessful' => true,
                'time' => null,
            ],
        );

        $row = self::table(self::blockAt($view, 0))->rows[0] ?? self::fail('The table must describe every message.');

        self::assertSame(
            '—',
            self::textValue($row[1] ?? self::fail('The sender column must exist.')),
            'A missing sender must show the placeholder.',
        );
        self::assertSame(
            '(no subject)',
            self::textValue($row[2] ?? self::fail('The subject column must exist.')),
            'A missing subject must show its own fallback.',
        );
        self::assertSame(
            '—',
            self::textValue($row[3] ?? self::fail('The recipient column must exist.')),
            'An empty recipient group must show the placeholder.',
        );
        self::assertSame(
            '—',
            self::textValue($row[5] ?? self::fail('The time column must exist.')),
            'A message without time must show the placeholder.',
        );
        self::assertSame(
            '1. (no subject)',
            self::heading(self::blockAt($view, 1))->title,
            'The heading must reuse the subject fallback.',
        );

        $fields = self::fields(self::overview(self::childBlockAt(self::group(self::blockAt($view, 2)), 0)));

        self::assertSame(
            '—',
            self::textValue($fields['From'] ?? self::fail('The envelope must keep the sender row.')),
            'A missing sender must show the placeholder.',
        );
        self::assertSame(
            '—',
            self::textValue($fields['Sent at'] ?? self::fail('The envelope must keep the time row.')),
            'A message without time must show the placeholder.',
        );
    }

    public function testFailedDeliveryIsCountedInTheSummaryAndBadgedAsDanger(): void
    {
        $view = self::present(self::message(['isSuccessful' => false]), self::message());

        self::assertSame(
            [' emails', ' failed'],
            self::metricLabels($view->summaryMetrics()),
            'The failed count must follow the total.',
        );
        self::assertSame(
            ['2', '1'],
            self::metricValues($view->summaryMetrics()),
            'Only the rejected message must be counted as failed.',
        );

        $rows = self::table(self::blockAt($view, 0))->rows;
        $failed = self::badge($rows[0][4] ?? self::fail('The status column must exist.'));

        self::assertSame(
            'Failed',
            $failed->label,
            'A rejected message must be labeled as failed.',
        );
        self::assertSame(
            Tone::DANGER,
            $failed->tone,
            'A rejected message must use the danger tone.',
        );

        $sent = self::badge($rows[1][4] ?? self::fail('The status column must exist.'));

        self::assertSame(
            'Sent',
            $sent->label,
            'A delivered message must be labeled as sent.',
        );
        self::assertSame(
            Tone::SUCCESS,
            $sent->tone,
            'A delivered message must use the success tone.',
        );
    }

    public function testMetadataMatchesTheBuiltInMailPanel(): void
    {
        $panel = new MailPanel();

        self::assertSame(
            'mail',
            $panel->id(),
            'The persisted panel identifier must stay stable.',
        );
        self::assertSame(
            'Mail',
            $panel->name(),
            'The navigation title must stay stable.',
        );
        self::assertSame(
            'mail',
            $panel->icon(),
            'The panel must reuse the existing icon.',
        );
    }

    public function testMultipleMessagesUseThePluralSummaryAndOneGroupPerMessage(): void
    {
        $view = self::present(self::message(), self::message(['subject' => 'Second notice']));

        self::assertSame(
            [' emails'],
            self::metricLabels($view->summaryMetrics()),
            'A delivered inbox must carry only the total.',
        );
        self::assertEquals(
            [new ToolbarMetric('Emails', '2')],
            $view->toolbarMetrics(),
            'The toolbar must report the captured count.',
        );
        self::assertCount(
            5,
            $view->blocks(),
            'Each message must add its own heading and group.',
        );
        self::assertSame(
            '2. Second notice',
            self::heading(self::blockAt($view, 3))->title,
            'Headings must be numbered in capture order.',
        );
        self::assertSame(
            'Message 2',
            self::group(self::blockAt($view, 4))->label,
            'Groups must be numbered in capture order.',
        );
    }

    public function testOptionalEnvelopeFieldsAreOmittedWhenTheCaptureLacksThem(): void
    {
        $view = self::present(
            [
                'from' => 'alice@example.com',
                'to' => 'bob@example.com',
                'subject' => 'Welcome aboard',
                'headers' => '',
                'isSuccessful' => true,
                'time' => self::TIME,
            ],
        );
        $content = self::group(self::blockAt($view, 2));

        self::assertSame(
            ['From', 'To', 'Subject', 'Status', 'Sent at'],
            array_keys(self::fields(self::overview(self::childBlockAt($content, 0)))),
            'Uncaptured envelope rows must be dropped, not filled with placeholders.',
        );
        self::assertInstanceOf(
            ParagraphBlock::class,
            self::childBlockAt($content, 1),
            'A message without body must state it instead of opening a disclosure.',
        );
        self::assertCount(
            2,
            $content->content->blocks(),
            'Uncaptured headers must not open a disclosure.',
        );
    }

    public function testSingleMessageProducesTheSummaryTableAndItsDetailGroup(): void
    {
        $view = self::present(self::message());

        self::assertTrue(
            $view->isActive(),
            'A captured message must activate navigation.',
        );
        self::assertSame(
            [' email'],
            self::metricLabels($view->summaryMetrics()),
            'A single message must use the singular label.',
        );

        $table = self::table(self::blockAt($view, 0));

        self::assertSame(
            ['#', 'From', 'Subject', 'To', 'Status', 'Time'],
            $table->headers,
            'The column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                4 => ColumnStyle::PILL,
                5 => ColumnStyle::IDENTIFIER,
            ],
            $table->styles,
            'Each style must stay attached to the column it formats.',
        );

        $row = $table->rows[0] ?? self::fail('The table must describe every message.');

        self::assertSame(
            '1',
            self::textValue($row[0] ?? self::fail('The position column must exist.')),
            'Rows must be numbered from one.',
        );
        self::assertSame(
            'bob@example.com, carol@example.com',
            self::textValue($row[3] ?? self::fail('The recipient column must exist.')),
            'Every primary recipient must stay visible.',
        );
        self::assertSame(
            date('H:i:s', self::TIME),
            self::textValue($row[5] ?? self::fail('The time column must exist.')),
            'The table must show the clock-only timestamp.',
        );

        $heading = self::heading(self::blockAt($view, 1));

        self::assertSame(
            '1. Welcome aboard',
            $heading->title,
            'The heading must number the message and repeat its subject.',
        );
        self::assertTrue(
            $heading->section,
            'Each message must open a section-level heading.',
        );

        $content = self::group(self::blockAt($view, 2));

        self::assertSame(
            'Message 1',
            $content->label,
            'The group must identify the message it describes.',
        );

        $overview = self::overview(self::childBlockAt($content, 0));

        self::assertTrue(
            $overview->compact,
            'The envelope must use the compact presentation.',
        );

        $fields = self::fields($overview);

        self::assertSame(
            ['From', 'To', 'Cc', 'Bcc', 'Reply-To', 'Subject', 'Status', 'Sent at', 'Charset', 'Stored file'],
            array_keys($fields),
            'The envelope must keep every captured recipient group.',
        );
        self::assertSame(
            'dave@example.com',
            self::textValue($fields['Cc'] ?? self::fail('The envelope must keep the copy row.')),
            'Carbon copies must survive the migration.',
        );
        self::assertSame(
            'erin@example.com',
            self::textValue($fields['Bcc'] ?? self::fail('The envelope must keep the blind copy row.')),
            'Blind carbon copies must survive the migration.',
        );
        self::assertSame(
            'noreply@example.com',
            self::textValue($fields['Reply-To'] ?? self::fail('The envelope must keep the reply row.')),
            'Reply addresses must survive the migration.',
        );
        self::assertSame(
            'UTF-8',
            self::textValue($fields['Charset'] ?? self::fail('The envelope must keep the charset row.')),
            'The declared charset must survive the migration.',
        );
        self::assertSame(
            '2026-09-12-welcome.eml',
            self::textValue($fields['Stored file'] ?? self::fail('The envelope must keep the file row.')),
            'The persisted file must survive the migration.',
        );
        self::assertSame(
            date('M j, Y · H:i:s', self::TIME),
            self::textValue($fields['Sent at'] ?? self::fail('The envelope must keep the time row.')),
            'The envelope must show the absolute timestamp.',
        );
        self::assertInstanceOf(
            DisclosureBlock::class,
            self::childBlockAt($content, 1),
            'A captured body must open a disclosure.',
        );
        self::assertInstanceOf(
            DisclosureBlock::class,
            self::childBlockAt($content, 2),
            'Captured headers must open their own disclosure.',
        );
    }

    /**
     * Builds a fully populated capture payload, overriding the requested fields.
     *
     * @param array<string, mixed> $overrides Capture fields replacing the defaults.
     *
     * @return array<string, mixed> Capture payload accepted by {@see MailSnapshot::capture()}.
     */
    private static function message(array $overrides = []): array
    {
        return [
            'from' => 'alice@example.com',
            'to' => 'bob@example.com, carol@example.com',
            'cc' => 'dave@example.com',
            'bcc' => 'erin@example.com',
            'reply' => 'noreply@example.com',
            'subject' => 'Welcome aboard',
            'body' => 'Hello Bob, welcome aboard.',
            'headers' => "Content-Type: text/plain\nX-Mailer: test",
            'charset' => 'UTF-8',
            'file' => '2026-09-12-welcome.eml',
            'isSuccessful' => true,
            'time' => self::TIME,
            ...$overrides,
        ];
    }

    /**
     * Presents the given capture payloads through the panel under test.
     *
     * @param array<string, mixed> ...$messages Capture payloads in send order.
     *
     * @return PanelView Description built by the panel.
     */
    private static function present(array ...$messages): PanelView
    {
        return (new MailPanel())->present(MailSnapshot::capture($messages)->jsonSerialize());
    }

}

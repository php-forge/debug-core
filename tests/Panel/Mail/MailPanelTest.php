<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Mail;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\Mail\{MailPanel, MailSnapshot};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function date;

/**
 * Unit tests for {@see MailPanel} covering metadata, the summary table, and the per-message detail groups.
 *
 * @phpstan-import-type BadgeInline from PanelView
 * @phpstan-import-type Block from PanelView
 * @phpstan-import-type EmptyStateBlock from PanelView
 * @phpstan-import-type GroupBlock from PanelView
 * @phpstan-import-type Inline from PanelView
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type Pair from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type TableBlock from PanelView
 */
#[Group('panel')]
#[Group('mail')]
final class MailPanelTest extends TestCase
{
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
            $state['title'],
            'The empty state must keep its heading.',
        );
        self::assertSame(
            ['This request did not dispatch any messages through the mailer, so the inbox is empty.'],
            self::inlineValues($state['paragraphs'][0] ?? self::fail('The empty state must explain itself.')),
            'The first paragraph must describe the empty inbox.',
        );
        self::assertSame(
            [
                'BaseMailer::EVENT_AFTER_SEND',
                ' is the capture hook; only requests that call ',
                '$mailer->send()',
                ' populate this view.',
            ],
            self::inlineValues($state['paragraphs'][1] ?? self::fail('The empty state must name the capture hook.')),
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

        $row = self::table(self::blockAt($view, 0))['rows'][0] ?? self::fail('The table must describe every message.');

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
            self::heading(self::blockAt($view, 1))['title'],
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
            '1',
            self::metricValue($view->summaryMetrics(), 1),
            'Only the rejected message must be counted as failed.',
        );

        $rows = self::table(self::blockAt($view, 0))['rows'];
        $failed = self::badge($rows[0][4] ?? self::fail('The status column must exist.'));

        self::assertSame(
            'Failed',
            $failed['label'],
            'A rejected message must be labeled as failed.',
        );
        self::assertSame(
            Tone::DANGER,
            $failed['tone'],
            'A rejected message must use the danger tone.',
        );

        $sent = self::badge($rows[1][4] ?? self::fail('The status column must exist.'));

        self::assertSame(
            'Sent',
            $sent['label'],
            'A delivered message must be labeled as sent.',
        );
        self::assertSame(
            Tone::SUCCESS,
            $sent['tone'],
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
        self::assertSame(
            '2',
            self::metricValue($view->toolbarMetrics(), 0),
            'The toolbar must report the captured count.',
        );
        self::assertCount(
            5,
            $view->blocks(),
            'Each message must add its own heading and group.',
        );
        self::assertSame(
            '2. Second notice',
            self::heading(self::blockAt($view, 3))['title'],
            'Headings must be numbered in capture order.',
        );
        self::assertSame(
            'Message 2',
            self::group(self::blockAt($view, 4))['label'],
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
        self::assertSame(
            'paragraph',
            self::childBlockAt($content, 1)['kind'],
            'A message without body must state it instead of opening a disclosure.',
        );
        self::assertCount(
            2,
            $content['content']->blocks(),
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
            $table['headers'],
            'The column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                4 => ColumnStyle::PILL,
                5 => ColumnStyle::IDENTIFIER,
            ],
            $table['styles'],
            'Each style must stay attached to the column it formats.',
        );

        $row = $table['rows'][0] ?? self::fail('The table must describe every message.');

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
            $heading['title'],
            'The heading must number the message and repeat its subject.',
        );
        self::assertTrue(
            $heading['section'],
            'Each message must open a section-level heading.',
        );

        $content = self::group(self::blockAt($view, 2));

        self::assertSame(
            'Message 1',
            $content['label'],
            'The group must identify the message it describes.',
        );

        $overview = self::overview(self::childBlockAt($content, 0));

        self::assertTrue(
            $overview['compact'],
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
        self::assertSame(
            'disclosure',
            self::childBlockAt($content, 1)['kind'],
            'A captured body must open a disclosure.',
        );
        self::assertSame(
            'disclosure',
            self::childBlockAt($content, 2)['kind'],
            'Captured headers must open their own disclosure.',
        );
    }

    /**
     * @param Inline $inline Cell or field value to narrow.
     *
     * @return BadgeInline Narrowed status badge.
     */
    private static function badge(array $inline): array
    {
        return match ($inline['kind']) {
            'badge' => $inline,
            default => self::fail('The delivery status must be a badge.'),
        };
    }

    /**
     * @param PanelView $view View to read.
     * @param int $index Position of the block in display order.
     *
     * @return Block Block declared at the requested position.
     */
    private static function blockAt(PanelView $view, int $index): array
    {
        return $view->blocks()[$index] ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * @param GroupBlock $block Group whose child view is read.
     * @param int $index Position of the block inside the group.
     *
     * @return Block Block declared at the requested position.
     */
    private static function childBlockAt(array $block, int $index): array
    {
        return $block['content']->blocks()[$index]
            ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return EmptyStateBlock Narrowed empty state.
     */
    private static function emptyState(array $block): array
    {
        return match ($block['kind']) {
            'emptyState' => $block,
            default => self::fail('An empty inbox must be explained by an empty state.'),
        };
    }

    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, Inline> Field values keyed by their label, in display order.
     */
    private static function fields(array $block): array
    {
        $fields = [];

        foreach ($block['fields'] as $field) {
            $fields[$field['label']] = $field['value'];
        }

        return $fields;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return GroupBlock Narrowed message group.
     */
    private static function group(array $block): array
    {
        return match ($block['kind']) {
            'group' => $block,
            default => self::fail('Each message must have an accessible group.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return array{kind: 'heading', title: string, section: bool} Narrowed message heading.
     */
    private static function heading(array $block): array
    {
        return match ($block['kind']) {
            'heading' => $block,
            default => self::fail('Each message must have a visible heading.'),
        };
    }

    /**
     * @param ParagraphBlock $block Paragraph whose inline content is read.
     *
     * @return list<string> Text carried by each inline value, in display order.
     */
    private static function inlineValues(array $block): array
    {
        $values = [];

        foreach ($block['content'] as $inline) {
            $values[] = self::textValue($inline);
        }

        return $values;
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
     * @param list<Pair> $metrics Metrics in display order.
     *
     * @return list<string> Metric labels in display order.
     */
    private static function metricLabels(array $metrics): array
    {
        $labels = [];

        foreach ($metrics as $metric) {
            $labels[] = $metric['label'];
        }

        return $labels;
    }

    /**
     * @param list<Pair> $metrics Metrics in display order.
     * @param int $index Position of the metric in display order.
     *
     * @return string Metric value as plain text.
     */
    private static function metricValue(array $metrics, int $index): string
    {
        $metric = $metrics[$index] ?? self::fail('The declared presentation structure must be complete.');

        return self::textValue($metric['value']);
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return OverviewBlock Narrowed envelope overview.
     */
    private static function overview(array $block): array
    {
        return match ($block['kind']) {
            'overview' => $block,
            default => self::fail('Each message must keep an inspectable envelope.'),
        };
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

    /**
     * @param Block $block Block to narrow.
     *
     * @return TableBlock Narrowed summary table.
     */
    private static function table(array $block): array
    {
        return match ($block['kind']) {
            'table' => $block,
            default => self::fail('The summary must use the shared table contract.'),
        };
    }

    /**
     * @param Inline $inline Cell or field value to read.
     *
     * @return string Text carried by the value.
     */
    private static function textValue(array $inline): string
    {
        return match ($inline['kind']) {
            'text' => $inline['value'],
            default => self::fail('The value must be plain text.'),
        };
    }
}

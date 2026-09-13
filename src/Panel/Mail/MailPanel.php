<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Mail;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};

use function count;
use function date;
use function implode;
use function sprintf;

/**
 * Presents captured mail messages as a status table followed by one detail group per message.
 *
 * @phpstan-import-type BadgeInline from PanelView
 */
final class MailPanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Mail navigation entry.
     */
    protected const string ICON = 'mail';

    /**
     * @var string Stable identifier associating the panel with the captured mail payload.
     */
    protected const string ID = 'mail';

    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = 'Mail';

    /**
     * @var string Absolute timestamp format of the detail overview.
     */
    private const string DATE_FORMAT = 'M j, Y · H:i:s';

    /**
     * @var string Placeholder shown wherever the capture left a field empty.
     */
    private const string PLACEHOLDER = '—';

    /**
     * @var string Subject shown when the mailer captured none.
     */
    private const string SUBJECT_FALLBACK = '(no subject)';

    /**
     * @var string Clock-only timestamp format of the summary table.
     */
    private const string TIME_FORMAT = 'H:i:s';

    /**
     * Builds the panel view from the decoded mail capture.
     *
     * @param array<string, mixed> $data Decoded panel payload with an `entries` key holding the captured messages.
     *
     * @return PanelView Summary table, per-message detail groups, and toolbar metric.
     */
    public function present(array $data): PanelView
    {
        $messages = MailSnapshot::fromArray($data, '$.mail')->entries();

        $count = count($messages);

        $view = PanelView::create()->active($count > 0);

        if ($count === 0) {
            return $view->emptyState(
                'No emails sent in this request',
                'This request did not dispatch any messages through the mailer, so the inbox is empty.',
                [
                    PanelView::code('BaseMailer::EVENT_AFTER_SEND'),
                    ' is the capture hook; only requests that call ',
                    PanelView::code('$mailer->send()'),
                    ' populate this view.',
                ],
            );
        }

        $failed = MailMessage::failedCount($messages);

        $view = $view
            ->summary($count === 1 ? ' email' : ' emails', $count)
            ->toolbar('Emails', $count);

        if ($failed > 0) {
            $view = $view->summary(' failed', $failed);
        }

        $view = $view->table(
            ['#', 'From', 'Subject', 'To', 'Status', 'Time'],
            self::rows($messages),
            styles: [
                0 => ColumnStyle::NUMBER,
                4 => ColumnStyle::PILL,
                5 => ColumnStyle::IDENTIFIER,
            ],
        );

        foreach ($messages as $index => $message) {
            $position = $index + 1;

            $view = $view
                ->heading(sprintf('%d. %s', $position, self::subject($message)), true)
                ->group(sprintf('Message %d', $position), self::detail($message));
        }

        return $view;
    }

    /**
     * Joins a recipient group into a single line, falling back to the placeholder when the group is empty.
     *
     * @param list<string> $addresses Recipient addresses in capture order.
     *
     * @return string Comma-separated addresses, or the placeholder when the group is empty.
     */
    private static function addresses(array $addresses): string
    {
        return $addresses === [] ? self::PLACEHOLDER : implode(', ', $addresses);
    }

    /**
     * Builds the detail group of one message: envelope overview, body, and raw headers.
     *
     * @param MailMessage $message Captured message to describe.
     *
     * @return PanelView Child view holding only the detail blocks of the message.
     */
    private static function detail(MailMessage $message): PanelView
    {
        $fields = [
            'From' => $message->from === '' ? self::PLACEHOLDER : $message->from,
            'To' => self::addresses($message->to),
        ];

        if ($message->cc !== []) {
            $fields['Cc'] = self::addresses($message->cc);
        }

        if ($message->bcc !== []) {
            $fields['Bcc'] = self::addresses($message->bcc);
        }

        if ($message->replyTo !== []) {
            $fields['Reply-To'] = self::addresses($message->replyTo);
        }

        $fields['Subject'] = self::subject($message);
        $fields['Status'] = self::status($message);
        $fields['Sent at'] = self::timestamp($message, self::DATE_FORMAT);

        if ($message->charset !== '') {
            $fields['Charset'] = $message->charset;
        }

        if ($message->file !== '') {
            $fields['Stored file'] = PanelView::code($message->file);
        }

        $view = PanelView::create()->overview($fields, true);

        $view = $message->body === ''
            ? $view->callout(Tone::MUTED, 'The mailer captured no body for this message.')
            : $view->disclosure('Body', $message->body);

        return $message->headers === '' ? $view : $view->disclosure('Raw headers', $message->headers);
    }

    /**
     * Builds the summary table rows in capture order.
     *
     * @param list<MailMessage> $messages Captured messages in send order.
     *
     * @return list<list<mixed>> One row per message, matching the declared column order.
     */
    private static function rows(array $messages): array
    {
        $rows = [];

        foreach ($messages as $index => $message) {
            $rows[] = [
                $index + 1,
                $message->from === '' ? self::PLACEHOLDER : $message->from,
                PanelView::strong(self::subject($message)),
                self::addresses($message->to),
                self::status($message),
                self::timestamp($message, self::TIME_FORMAT),
            ];
        }

        return $rows;
    }

    /**
     * Builds the delivery badge reported by the mailer.
     *
     * @param MailMessage $message Captured message to describe.
     *
     * @return BadgeInline Success badge for a delivered message, danger badge for a rejected one.
     */
    private static function status(MailMessage $message): array
    {
        return $message->isSuccessful
            ? PanelView::badge('Sent', Tone::SUCCESS)
            : PanelView::badge('Failed', Tone::DANGER);
    }

    /**
     * Returns the captured subject, falling back to an explicit placeholder when the mailer captured none.
     *
     * @param MailMessage $message Captured message to describe.
     *
     * @return string Captured subject, or the subject fallback when empty.
     */
    private static function subject(MailMessage $message): string
    {
        return $message->subject === '' ? self::SUBJECT_FALLBACK : $message->subject;
    }

    /**
     * Formats the capture time, falling back to the placeholder when the payload carried no parseable time.
     *
     * @param MailMessage $message Captured message to describe.
     * @param string $format Date format applied to the capture timestamp.
     *
     * @return string Formatted timestamp, or the placeholder when the message has no time.
     */
    private static function timestamp(MailMessage $message, string $format): string
    {
        return $message->time === null ? self::PLACEHOLDER : date($format, $message->time);
    }
}

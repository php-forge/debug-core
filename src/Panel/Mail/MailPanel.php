<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Mail;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};
use PHPForge\Debug\Presenter\BadgeInline;

use function count;
use function date;
use function implode;
use function sprintf;

/**
 * Presents captured mail messages as a status table followed by one detail group per message.
 */
final class MailPanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Mail navigation entry.
     */
    protected const string ICON = MailMessage::ID->value;
    /**
     * @var string Stable identifier associating the panel with the captured mail payload.
     */
    protected const string ID = MailMessage::ID->value;
    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = MailMessage::TITLE->value;

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
                MailMessage::EMPTY_HEADLINE->value,
                MailMessage::EMPTY_EXPLANATION->value,
                [
                    PanelView::code(MailMessage::EMPTY_HOOK->value),
                    MailMessage::EMPTY_CAPTURE->value,
                    PanelView::code(MailMessage::EMPTY_SEND->value),
                    MailMessage::EMPTY_POPULATE->value,
                ],
            );
        }

        $failed = MailEntry::failedCount($messages);

        $view = $view
            ->summary(
                $count === 1 ? MailMessage::EMAIL_SUFFIX->value : MailMessage::EMAILS_SUFFIX->value,
                $count,
            )
            ->toolbar(MailMessage::TOOLBAR->value, $count);

        if ($failed > 0) {
            $view = $view->summary(MailMessage::FAILED_SUFFIX->value, $failed);
        }

        $view = $view->table(
            [
                MailMessage::NUMBER->value,
                MailMessage::FROM->value,
                MailMessage::SUBJECT->value,
                MailMessage::TO->value,
                MailMessage::STATUS->value,
                MailMessage::TIME->value,
            ],
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
                ->heading(
                    sprintf(MailMessage::MESSAGE_HEADING->value, $position, self::subject($message)),
                    true,
                )
                ->group(
                    sprintf(MailMessage::MESSAGE_GROUP->value, $position),
                    self::detail($message),
                );
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
        return $addresses === [] ? MailMessage::PLACEHOLDER->value : implode(', ', $addresses);
    }

    /**
     * Builds the detail group of one message: envelope overview, body, and raw headers.
     *
     * @param MailEntry $message Captured message to describe.
     *
     * @return PanelView Child view holding only the detail blocks of the message.
     */
    private static function detail(MailEntry $message): PanelView
    {
        $fields = [
            MailMessage::FROM->value => $message->getFrom() === ''
                ? MailMessage::PLACEHOLDER->value
                : $message->getFrom(),
            MailMessage::TO->value => self::addresses($message->getTo()),
        ];

        if ($message->getCc() !== []) {
            $fields[MailMessage::CC->value] = self::addresses($message->getCc());
        }

        if ($message->getBcc() !== []) {
            $fields[MailMessage::BCC->value] = self::addresses($message->getBcc());
        }

        if ($message->getReplyTo() !== []) {
            $fields[MailMessage::REPLY_TO->value] = self::addresses($message->getReplyTo());
        }

        $fields[MailMessage::SUBJECT->value] = self::subject($message);
        $fields[MailMessage::STATUS->value] = self::status($message);
        $fields[MailMessage::SENT_AT->value] = self::timestamp($message, MailMessage::DATE_FORMAT);

        if ($message->getCharset() !== '') {
            $fields[MailMessage::CHARSET->value] = $message->getCharset();
        }

        if ($message->getFile() !== '') {
            $fields[MailMessage::STORED_FILE->value] = PanelView::code($message->getFile());
        }

        $view = PanelView::create()->overview($fields, true);

        $view = $message->getBody() === ''
            ? $view->callout(Tone::MUTED, MailMessage::NO_BODY->value)
            : $view->disclosure(MailMessage::BODY->value, $message->getBody());

        return $message->getHeaders() === ''
            ? $view
            : $view->disclosure(MailMessage::HEADERS->value, $message->getHeaders());
    }

    /**
     * Builds the summary table rows in capture order.
     *
     * @param list<MailEntry> $messages Captured messages in send order.
     *
     * @return list<list<mixed>> One row per message, matching the declared column order.
     */
    private static function rows(array $messages): array
    {
        $rows = [];

        foreach ($messages as $index => $message) {
            $rows[] = [
                $index + 1,
                $message->getFrom() === '' ? MailMessage::PLACEHOLDER->value : $message->getFrom(),
                PanelView::strong(self::subject($message)),
                self::addresses($message->getTo()),
                self::status($message),
                self::timestamp($message, MailMessage::TIME_FORMAT),
            ];
        }

        return $rows;
    }

    /**
     * Builds the delivery badge reported by the mailer.
     *
     * @param MailEntry $message Captured message to describe.
     *
     * @return BadgeInline Success badge for a delivered message, danger badge for a rejected one.
     */
    private static function status(MailEntry $message): BadgeInline
    {
        return $message->isSuccessful()
            ? PanelView::badge(MailMessage::STATUS_SENT->value, Tone::SUCCESS)
            : PanelView::badge(MailMessage::STATUS_FAILED->value, Tone::DANGER);
    }

    /**
     * Returns the captured subject, falling back to an explicit placeholder when the mailer captured none.
     *
     * @param MailEntry $message Captured message to describe.
     *
     * @return string Captured subject, or the subject fallback when empty.
     */
    private static function subject(MailEntry $message): string
    {
        return $message->getSubject() === '' ? MailMessage::SUBJECT_FALLBACK->value : $message->getSubject();
    }

    /**
     * Formats the capture time, falling back to the placeholder when the payload carried no parseable time.
     *
     * @param MailEntry $message Captured message to describe.
     * @param MailMessage $format Date format applied to the capture timestamp.
     *
     * @return string Formatted timestamp, or the placeholder when the message has no time.
     */
    private static function timestamp(MailEntry $message, MailMessage $format): string
    {
        return $message->getTime() === null
            ? MailMessage::PLACEHOLDER->value
            : date($format->value, $message->getTime());
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Mail;

/**
 * Identity, formats, and presentation text of the Mail panel, shared by every adapter that renders it.
 */
enum MailMessage: string
{
    /**
     * Detail field label of the blind carbon copy recipients.
     */
    case BCC = 'Bcc';

    /**
     * Disclosure label of the message body.
     */
    case BODY = 'Body';

    /**
     * Detail field label of the carbon copy recipients.
     */
    case CC = 'Cc';

    /**
     * Detail field label of the message charset.
     */
    case CHARSET = 'Charset';

    /**
     * `date()` format of the absolute timestamp in the detail overview.
     */
    case DATE_FORMAT = 'M j, Y · H:i:s';

    /**
     * Suffix appended to a single captured message in the summary header.
     */
    case EMAIL_SUFFIX = ' email';

    /**
     * Suffix appended to the captured message count in the summary header.
     */
    case EMAILS_SUFFIX = ' emails';

    /**
     * Middle sentence of the empty-state call to action, preceding the mailer call.
     */
    case EMPTY_CAPTURE = ' is the capture hook; only requests that call ';

    /**
     * Explanation of the empty state when the request dispatched no message.
     */
    case EMPTY_EXPLANATION = 'This request did not dispatch any messages through the mailer, so the inbox is empty.';

    /**
     * Headline of the empty state when the mailer captured no message.
     */
    case EMPTY_HEADLINE = 'No emails sent in this request';

    /**
     * Capture hook named in the empty state, which records a sent message.
     */
    case EMPTY_HOOK = 'BaseMailer::EVENT_AFTER_SEND';

    /**
     * Closing sentence of the empty-state call to action, following the mailer call.
     */
    case EMPTY_POPULATE = ' populate this view.';

    /**
     * Mailer call named in the empty state, whose invocation populates the panel.
     */
    case EMPTY_SEND = '$mailer->send()';

    /**
     * Suffix appended to the rejected message count in the summary header.
     */
    case FAILED_SUFFIX = ' failed';

    /**
     * Header of the sender column, also used as its detail field label.
     */
    case FROM = 'From';

    /**
     * Disclosure label of the raw message headers.
     */
    case HEADERS = 'Raw headers';

    /**
     * Stable identifier associating the panel with the captured payload, also used as its icon key.
     */
    case ID = 'mail';

    /**
     * `sprintf()` template of the detail group label of one message.
     */
    case MESSAGE_GROUP = 'Message %d';

    /**
     * `sprintf()` template of the heading preceding each detail group.
     */
    case MESSAGE_HEADING = '%d. %s';

    /**
     * Note of the detail group when the mailer captured no body.
     */
    case NO_BODY = 'The mailer captured no body for this message.';

    /**
     * Header of the position column, which sorts by send order.
     */
    case NUMBER = '#';

    /**
     * Placeholder shown wherever the capture left a field empty.
     */
    case PLACEHOLDER = '—';

    /**
     * Detail field label of the reply-to recipients.
     */
    case REPLY_TO = 'Reply-To';

    /**
     * Detail field label of the absolute send time.
     */
    case SENT_AT = 'Sent at';

    /**
     * Header of the delivery status column, also used as its detail field label.
     */
    case STATUS = 'Status';

    /**
     * Badge label of a message the mailer rejected.
     */
    case STATUS_FAILED = 'Failed';

    /**
     * Badge label of a message the mailer delivered.
     */
    case STATUS_SENT = 'Sent';

    /**
     * Detail field label of the stored message file.
     */
    case STORED_FILE = 'Stored file';

    /**
     * Header of the subject column, also used as its detail field label.
     */
    case SUBJECT = 'Subject';

    /**
     * Subject shown when the mailer captured none.
     */
    case SUBJECT_FALLBACK = '(no subject)';

    /**
     * Header of the send-time column.
     */
    case TIME = 'Time';

    /**
     * `date()` format of the clock-only timestamp in the summary table.
     */
    case TIME_FORMAT = 'H:i:s';

    /**
     * Panel title used in the debugger navigation.
     */
    case TITLE = 'Mail';

    /**
     * Header of the recipient column, also used as its detail field label.
     */
    case TO = 'To';

    /**
     * Label of the toolbar metric counting the captured messages.
     */
    case TOOLBAR = 'Emails';
}

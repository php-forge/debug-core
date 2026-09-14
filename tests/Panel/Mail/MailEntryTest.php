<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Mail;

use DateTimeImmutable;
use PHPForge\Debug\Panel\Mail\MailEntry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Stringable;

/**
 * Unit tests for {@see MailEntry} covering payload narrowing, address splitting, time parsing, the scalar-to-string
 * coercion of header/body fields, and the failed-send aggregate.
 */
#[Group('panel')]
#[Group('mail')]
final class MailEntryTest extends TestCase
{
    public function testFailedCountCountsUnsuccessfulMessages(): void
    {
        $count = MailEntry::failedCount(
            [
                MailEntry::fromCapture(['isSuccessful' => true]),
                MailEntry::fromCapture(['isSuccessful' => false]),
                MailEntry::fromCapture(['no-flag' => 'missing counts as failed']),
            ],
        );

        self::assertSame(
            2,
            $count,
            'Only strictly-`true` flags must count as sent.',
        );
    }

    public function testFailedCountReturnsZeroForEmptyList(): void
    {
        self::assertSame(
            0,
            MailEntry::failedCount([]),
            'Empty list must yield zero.',
        );
    }

    public function testFromCaptureCoercesScalarHeaderFieldsToStrings(): void
    {
        $message = MailEntry::fromCapture(['from' => 42, 'subject' => true, 'charset' => 1.5]);

        self::assertSame(
            '42',
            $message->getFrom(),
            'Int sender must coerce to string.',
        );
        self::assertSame(
            '1',
            $message->getSubject(),
            'Bool subject must coerce to string.',
        );
        self::assertSame(
            '1.5',
            $message->getCharset(),
            'Float charset must coerce to string.',
        );
    }

    public function testFromCaptureCoercesStringableHeaderFieldsToStrings(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'rendered';
            }
        };

        $message = MailEntry::fromCapture(['subject' => $stringable]);

        self::assertSame(
            'rendered',
            $message->getSubject(),
            "Stringable subject must coerce via '__toString()'.",
        );
    }

    public function testFromCaptureCollapsesNonStringFileToEmpty(): void
    {
        self::assertSame(
            '',
            MailEntry::fromCapture(['file' => 42])->getFile(),
            "Non-string `file` must collapse to ''.",
        );
    }

    public function testFromCaptureCollapsesUnparseableTimeToNull(): void
    {
        self::assertNull(
            MailEntry::fromCapture(['time' => 'not a date'])->getTime(),
            "Garbage string must collapse to 'null'.",
        );
        self::assertNull(
            MailEntry::fromCapture(['time' => ''])->getTime(),
            "Empty string must collapse to 'null'.",
        );
        self::assertNull(
            MailEntry::fromCapture(['time' => null])->getTime(),
            "'null' must collapse to 'null'.",
        );
        self::assertNull(
            MailEntry::fromCapture(['time' => ['nested']])->getTime(),
            "Array must collapse to 'null'.",
        );
    }

    public function testFromCaptureDropsEmptySegmentsBetweenCommas(): void
    {
        $message = MailEntry::fromCapture(['to' => 'a@example.com,, ,b@example.com,']);

        self::assertSame(
            ['a@example.com', 'b@example.com'],
            $message->getTo(),
            'Empty segments must be dropped.',
        );
    }

    public function testFromCaptureFallsBackToEmptyWhenStringFieldsAreNonScalar(): void
    {
        $message = MailEntry::fromCapture(['from' => ['nested'], 'subject' => null]);

        self::assertSame(
            '',
            $message->getFrom(),
            'Array `from` must collapse to `\'\'`.',
        );
        self::assertSame(
            '',
            $message->getSubject(),
            'Null `subject` must collapse to `\'\'`.',
        );
    }

    public function testFromCaptureKeepsIntTimeAsIs(): void
    {
        self::assertSame(
            1_700_000_000,
            MailEntry::fromCapture(['time' => 1_700_000_000])->getTime(),
            'Int time must round-trip unchanged.',
        );
    }

    public function testFromCaptureMapsTruthyIsSuccessfulOnlyWhenStrictlyTrue(): void
    {
        self::assertTrue(
            MailEntry::fromCapture(['isSuccessful' => true])->isSuccessful(),
            "'true' must round-trip.",
        );
        self::assertFalse(
            MailEntry::fromCapture(['isSuccessful' => 1])->isSuccessful(),
            "'1' must not be accepted (strict comparison)."
        );
        self::assertFalse(
            MailEntry::fromCapture(['isSuccessful' => 'true'])->isSuccessful(),
            "'true' must not be accepted."
        );
        self::assertFalse(
            MailEntry::fromCapture(['isSuccessful' => false])->isSuccessful(),
            "'false' must yield 'false'."
        );
        self::assertFalse(
            MailEntry::fromCapture([])->isSuccessful(),
            "Missing flag must default to 'false'."
        );
    }

    public function testFromCaptureParsesDateTimeInterfaceAsUnixTimestamp(): void
    {
        $datetime = new DateTimeImmutable('2024-06-15T12:34:56+00:00');

        $message = MailEntry::fromCapture(['time' => $datetime]);

        self::assertSame(
            $datetime->getTimestamp(),
            $message->getTime(),
            'DateTimeInterface must yield its Unix timestamp.',
        );
    }

    public function testFromCaptureParsesStringTimeViaStrtotime(): void
    {
        $message = MailEntry::fromCapture(['time' => '2024-06-15T12:34:56+00:00']);

        self::assertSame(
            strtotime('2024-06-15T12:34:56+00:00'),
            $message->getTime(),
            'Parseable string must coerce via `strtotime`.',
        );
    }

    public function testFromCaptureReturnsAllEmptyDefaultsForAnEmptyPayload(): void
    {
        $message = MailEntry::fromCapture([]);

        self::assertSame(
            '',
            $message->getFrom(),
            "Non-array input must yield empty 'from'.",
        );
        self::assertSame(
            [],
            $message->getTo(),
            "Non-array input must yield empty 'to'.",
        );
        self::assertSame(
            [],
            $message->getCc(),
            "Non-array input must yield empty 'cc'.",
        );
        self::assertSame(
            [],
            $message->getBcc(),
            "Non-array input must yield empty 'bcc'.",
        );
        self::assertSame(
            [],
            $message->getReplyTo(),
            "Non-array input must yield empty 'replyTo'.",
        );
        self::assertSame(
            '',
            $message->getSubject(),
            "Non-array input must yield empty 'subject'.",
        );
        self::assertSame(
            '',
            $message->getBody(),
            "Non-array input must yield empty 'body'.",
        );
        self::assertSame(
            '',
            $message->getHeaders(),
            "Non-array input must yield empty 'headers'.",
        );
        self::assertSame(
            '',
            $message->getCharset(),
            "Non-array input must yield empty 'charset'.",
        );
        self::assertSame(
            '',
            $message->getFile(),
            "Non-array input must yield empty 'file'.",
        );
        self::assertFalse(
            $message->isSuccessful(),
            "Non-array input must yield 'isSuccessful = false'.",
        );
        self::assertNull(
            $message->getTime(),
            "Non-array input must yield 'null' 'time'.",
        );
    }

    public function testFromCaptureRoundTripsTypedFields(): void
    {
        $message = MailEntry::fromCapture(
            [
                'from' => 'sender@example.com',
                'subject' => 'Hello',
                'body' => 'Body content.',
                'headers' => 'X-Foo: bar',
                'charset' => 'UTF-8',
                'file' => '/tmp/mail.eml',
                'isSuccessful' => true,
            ],
        );

        self::assertSame(
            'sender@example.com',
            $message->getFrom(),
            'From must round-trip.',
        );
        self::assertSame(
            'Hello',
            $message->getSubject(),
            'Subject must round-trip.',
        );
        self::assertSame(
            'Body content.',
            $message->getBody(),
            'Body must round-trip.',
        );
        self::assertSame(
            'X-Foo: bar',
            $message->getHeaders(),
            'Headers must round-trip.',
        );
        self::assertSame(
            'UTF-8',
            $message->getCharset(),
            'Charset must round-trip.',
        );
        self::assertSame(
            '/tmp/mail.eml',
            $message->getFile(),
            'File path must round-trip.',
        );
        self::assertTrue(
            $message->isSuccessful(),
            '`isSuccessful = true` must round-trip.',
        );
    }

    public function testFromCaptureSplitsCommaSeparatedRecipients(): void
    {
        $message = MailEntry::fromCapture(
            [
                'to' => 'a@example.com, b@example.com,c@example.com',
                'cc' => 'cc@example.com',
                'bcc' => '',
                'reply' => 'reply1@example.com,reply2@example.com',
            ],
        );

        self::assertSame(
            ['a@example.com', 'b@example.com', 'c@example.com'],
            $message->getTo(),
            'TO must split on commas and trim.',
        );
        self::assertSame(
            ['cc@example.com'],
            $message->getCc(),
            'Single CC must yield a one-element list.',
        );
        self::assertSame(
            [],
            $message->getBcc(),
            'Empty BCC string must yield `[]`.',
        );
        self::assertSame(
            ['reply1@example.com', 'reply2@example.com'],
            $message->getReplyTo(),
            'Reply-to must split on commas.',
        );
    }
}

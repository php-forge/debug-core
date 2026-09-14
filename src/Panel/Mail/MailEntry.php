<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Mail;

use DateTimeInterface;
use PHPForge\Debug\Helper\Coerce;
use PHPForge\Debug\Storage\{PanelRow, Payload};

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_int;
use function is_string;
use function strtotime;

/**
 * Typed view-model for a single mail message rendered in the Mail panel detail view.
 */
final class MailEntry implements PanelRow
{
    /**
     * @var list<string> Blind carbon-copy recipients split out of the comma-separated `bcc` field.
     */
    private array $bcc = [];
    /**
     * Plain-text body as captured, or `''` when the message had no body.
     */
    private string $body = '';
    /**
     * @var list<string> Carbon-copy recipients split out of the comma-separated `cc` field.
     */
    private array $cc = [];
    /**
     * Charset declared on the message, or `''` when none was set.
     */
    private string $charset = '';
    /**
     * Path to the persisted `.eml` file, or `''` when the mailer does not expose one.
     */
    private string $file = '';
    /**
     * Raw RFC-5322 headers as captured by the mailer, joined with line breaks.
     */
    private string $headers = '';
    /**
     * @var list<string> Reply-to addresses split out of the comma-separated `reply` field.
     */
    private array $replyTo = [];
    /**
     * Capture timestamp as a Unix-epoch second, or `null` when the original payload had no parseable time.
     */
    private int|null $time = null;

    /**
     * @param string $from Sender address as captured, typically `name@example.com` or `Name <name@example.com>`.
     * @param list<string> $to Primary recipients, with empty entries dropped.
     * @param string $subject Subject line as captured.
     * @param bool $isSuccessful `true` when the mailer reported the message as sent, `false` on a reported failure.
     */
    private function __construct(
        private string $from,
        private array $to,
        private string $subject,
        private bool $isSuccessful,
    ) {}

    /**
     * Creates a captured message from the envelope fields every mailer reports.
     *
     * @param string $from Sender address as captured.
     * @param list<string> $to Primary recipients, with empty entries dropped.
     * @param string $subject Subject line as captured.
     * @param bool $isSuccessful Whether the mailer reported the message as sent.
     *
     * @return self Message carrying the envelope, without body, headers, or capture time.
     */
    public static function create(string $from, array $to, string $subject, bool $isSuccessful): self
    {
        return new self($from, $to, $subject, $isSuccessful);
    }

    /**
     * Counts the messages the mailer rejected.
     *
     * @param list<self> $models Captured messages.
     *
     * @return int Number of messages reported as failed.
     */
    public static function failedCount(array $models): int
    {
        $failed = 0;

        foreach ($models as $model) {
            if ($model->isSuccessful === false) {
                $failed++;
            }
        }

        return $failed;
    }

    /**
     * Narrows one persisted message of the mail payload into a typed entry.
     *
     * @param mixed $data Persisted message, expected to be an object carrying the declared shape.
     * @param string $path JSON path of the message, used to report a malformed payload.
     *
     * @return self Message carrying every persisted field.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'from',
                    'to',
                    'cc',
                    'bcc',
                    'replyTo',
                    'subject',
                    'body',
                    'headers',
                    'charset',
                    'file',
                    'isSuccessful',
                    'time',
                ],
            );

        return self::create(
            $payload->string('from'),
            Coerce::stringList($payload->list('to')),
            $payload->string('subject'),
            $payload->bool('isSuccessful'),
        )
        ->withBcc(Coerce::stringList($payload->list('bcc')))
        ->withBody($payload->string('body'))
        ->withCc(Coerce::stringList($payload->list('cc')))
        ->withCharset($payload->string('charset'))
        ->withFile($payload->string('file'))
        ->withHeaders($payload->string('headers'))
        ->withReplyTo(Coerce::stringList($payload->list('replyTo')))
        ->withTime($payload->nullableInt('time'));
    }

    /**
     * Narrows one captured `EVENT_AFTER_SEND` payload into a typed message.
     *
     * @param array<array-key, mixed> $row Captured payload.
     *
     * @return self Message carrying every field the mailer exposed.
     */
    public static function fromCapture(array $row): self
    {
        return self::create(
            self::scalar($row, 'from'),
            self::splitAddresses(self::scalar($row, 'to')),
            self::scalar($row, 'subject'),
            ($row['isSuccessful'] ?? false) === true,
        )
        ->withBcc(self::splitAddresses(self::scalar($row, 'bcc')))
        ->withBody(self::scalar($row, 'body'))
        ->withCc(self::splitAddresses(self::scalar($row, 'cc')))
        ->withCharset(self::scalar($row, 'charset'))
        ->withFile(Coerce::string($row['file'] ?? null))
        ->withHeaders(self::scalar($row, 'headers'))
        ->withReplyTo(self::splitAddresses(self::scalar($row, 'reply')))
        ->withTime(self::normalizeTime($row['time'] ?? null));
    }

    /**
     * Returns the blind carbon-copy recipients.
     *
     * @return list<string> Blind carbon-copy recipients in capture order.
     */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /**
     * Returns the captured plain-text body.
     *
     * @return string Plain-text body, or `''` when the message had none.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Returns the carbon-copy recipients.
     *
     * @return list<string> Carbon-copy recipients in capture order.
     */
    public function getCc(): array
    {
        return $this->cc;
    }

    /**
     * Returns the charset declared on the message.
     *
     * @return string Declared charset, or `''` when none was set.
     */
    public function getCharset(): string
    {
        return $this->charset;
    }

    /**
     * Returns the path of the message the mailer persisted.
     *
     * @return string Path to the persisted `.eml` file, or `''` when the mailer exposes none.
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Returns the sender address as captured.
     *
     * @return string Sender address as captured.
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Returns the raw message headers as captured.
     *
     * @return string Raw RFC-5322 headers, joined with line breaks.
     */
    public function getHeaders(): string
    {
        return $this->headers;
    }

    /**
     * Returns the reply-to addresses.
     *
     * @return list<string> Reply-to addresses in capture order.
     */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    /**
     * Returns the subject line as captured.
     *
     * @return string Subject line as captured.
     */
    public function getSubject(): string
    {
        return $this->subject;
    }

    /**
     * Returns the capture timestamp.
     *
     * @return int|null Capture timestamp as a Unix-epoch second, or `null` when the payload carried no parseable time.
     */
    public function getTime(): int|null
    {
        return $this->time;
    }

    /**
     * Returns the primary recipients.
     *
     * @return list<string> Primary recipients in capture order.
     */
    public function getTo(): array
    {
        return $this->to;
    }

    /**
     * Reports whether the mailer delivered the message.
     *
     * @return bool `true` when the mailer reported the message as sent.
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    /**
     * Serializes the message into its persisted payload.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'replyTo' => $this->replyTo,
            'subject' => $this->subject,
            'body' => $this->body,
            'headers' => $this->headers,
            'charset' => $this->charset,
            'file' => $this->file,
            'isSuccessful' => $this->isSuccessful,
            'time' => $this->time,
        ];
    }

    /**
     * Returns a copy with the blind carbon-copy recipients.
     *
     * @param list<string> $bcc Recipients in capture order.
     *
     * @return self New instance carrying the requested recipients.
     */
    public function withBcc(array $bcc): self
    {
        $clone = clone $this;
        $clone->bcc = $bcc;

        return $clone;
    }

    /**
     * Returns a copy with the captured body.
     *
     * @param string $body Plain-text body, or `''` when the message had none.
     *
     * @return self New instance carrying the requested body.
     */
    public function withBody(string $body): self
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /**
     * Returns a copy with the carbon-copy recipients.
     *
     * @param list<string> $cc Recipients in capture order.
     *
     * @return self New instance carrying the requested recipients.
     */
    public function withCc(array $cc): self
    {
        $clone = clone $this;
        $clone->cc = $cc;

        return $clone;
    }

    /**
     * Returns a copy with the declared charset.
     *
     * @param string $charset Charset declared on the message, or `''` when none was set.
     *
     * @return self New instance carrying the requested charset.
     */
    public function withCharset(string $charset): self
    {
        $clone = clone $this;
        $clone->charset = $charset;

        return $clone;
    }

    /**
     * Returns a copy with the persisted message file.
     *
     * @param string $file Path to the `.eml` file, or `''` when the mailer exposes none.
     *
     * @return self New instance carrying the requested file.
     */
    public function withFile(string $file): self
    {
        $clone = clone $this;
        $clone->file = $file;

        return $clone;
    }

    /**
     * Returns a copy with the raw message headers.
     *
     * @param string $headers Raw RFC-5322 headers, joined with line breaks.
     *
     * @return self New instance carrying the requested headers.
     */
    public function withHeaders(string $headers): self
    {
        $clone = clone $this;
        $clone->headers = $headers;

        return $clone;
    }

    /**
     * Returns a copy with the reply-to addresses.
     *
     * @param list<string> $replyTo Addresses in capture order.
     *
     * @return self New instance carrying the requested addresses.
     */
    public function withReplyTo(array $replyTo): self
    {
        $clone = clone $this;
        $clone->replyTo = $replyTo;

        return $clone;
    }

    /**
     * Returns a copy with the capture timestamp.
     *
     * @param int|null $time Unix-epoch second, or `null` when the payload carried no parseable time.
     *
     * @return self New instance carrying the requested timestamp.
     */
    public function withTime(int|null $time): self
    {
        $clone = clone $this;
        $clone->time = $time;

        return $clone;
    }

    /**
     * Narrows a captured time into a Unix-epoch second.
     *
     * @param mixed $value Captured time: a `DateTimeInterface`, an `int`, or a `strtotime()`-parseable `string`.
     *
     * @return int|null Unix-epoch second, or `null` when the value carried no parseable time.
     */
    private static function normalizeTime(mixed $value): int|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $parsed = strtotime($value);

            return $parsed === false ? null : $parsed;
        }

        return null;
    }

    /**
     * Returns a captured field coerced to a string.
     *
     * @param array<array-key, mixed> $row Captured payload holding the field.
     * @param string $key Field to read.
     *
     * @return string Captured value, or `''` when missing or not coercible.
     */
    private static function scalar(array $row, string $key): string
    {
        return Coerce::stringOrNull($row[$key] ?? null) ?? '';
    }

    /**
     * Splits a comma-separated address list, dropping the empty segments.
     *
     * @param string $raw Address list as captured.
     *
     * @return list<string> Trimmed addresses in capture order.
     */
    private static function splitAddresses(string $raw): array
    {
        $parts = array_map(trim(...), explode(',', $raw));

        return array_values(array_filter($parts, static fn(string $address): bool => $address !== ''));
    }
}

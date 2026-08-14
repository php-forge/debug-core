<?php

declare(strict_types=1);

namespace PHPForge\Debug\Storage;

use JsonSerializable;

use function is_string;

/**
 * Represents canonical metadata for one captured request and its manifest row.
 */
final readonly class RequestSummary implements JsonSerializable
{
    /**
     * Creates request metadata for a snapshot and manifest row.
     *
     * @param string $tag Unique request tag.
     * @param string $url Requested URL.
     * @param bool $ajax Whether the request used AJAX.
     * @param string $method HTTP request method.
     * @param string $ip Client IP address.
     * @param float $time Request start timestamp.
     * @param int $statusCode HTTP response status code.
     * @param int $sqlCount Number of SQL queries.
     * @param int $excessiveCallersCount Number of excessive log callers.
     * @param int $mailCount Number of captured mail messages.
     * @param list<string> $mailFiles Captured mail file paths.
     * @param float|null $processingTime Processing duration in seconds or `null` when unavailable.
     * @param int|null $peakMemory Peak memory in bytes or `null` when unavailable.
     */
    public function __construct(
        public string $tag,
        public string $url,
        public bool $ajax,
        public string $method,
        public string $ip,
        public float $time,
        public int $statusCode,
        public int $sqlCount,
        public int $excessiveCallersCount,
        public int $mailCount,
        public array $mailFiles,
        public float|null $processingTime,
        public int|null $peakMemory,
    ) {}

    /**
     * Hydrates request metadata from decoded JSON data.
     *
     * Usage example:
     *
     * ```php
     * $summary = \PHPForge\Debug\Storage\RequestSummary::fromArray($data);
     * ```
     *
     * @param mixed $data Decoded request metadata.
     * @param string $path Payload path used in hydration errors.
     *
     * @return self Hydrated request metadata.
     */
    public static function fromArray(mixed $data, string $path = '$.summary'): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'tag',
                    'url',
                    'ajax',
                    'method',
                    'ip',
                    'time',
                    'statusCode',
                    'sqlCount',
                    'excessiveCallersCount',
                    'mailCount',
                    'mailFiles',
                    'processingTime',
                    'peakMemory',
                ],
            );

        $mailFiles = [];

        foreach ($payload->list('mailFiles') as $index => $file) {
            if (!is_string($file)) {
                throw HydrationException::at(
                    "{$path}.mailFiles[{$index}]",
                    'a string',
                );
            }

            $mailFiles[] = $file;
        }

        return new self(
            tag: $payload->string('tag'),
            url: $payload->string('url'),
            ajax: $payload->bool('ajax'),
            method: $payload->string('method'),
            ip: $payload->string('ip'),
            time: $payload->number('time'),
            statusCode: $payload->int('statusCode'),
            sqlCount: $payload->int('sqlCount'),
            excessiveCallersCount: $payload->int('excessiveCallersCount'),
            mailCount: $payload->int('mailCount'),
            mailFiles: $mailFiles,
            processingTime: $payload->nullableNumber('processingTime'),
            peakMemory: $payload->nullableInt('peakMemory'),
        );
    }

    /**
     * Returns the request metadata for JSON serialization.
     *
     * Usage example:
     *
     * ```php
     * $data = $summary->jsonSerialize();
     * ```
     *
     * @return array<string, mixed> Serialized request metadata.
     */
    public function jsonSerialize(): array
    {
        return [
            'tag' => $this->tag,
            'url' => $this->url,
            'ajax' => $this->ajax,
            'method' => $this->method,
            'ip' => $this->ip,
            'time' => $this->time,
            'statusCode' => $this->statusCode,
            'sqlCount' => $this->sqlCount,
            'excessiveCallersCount' => $this->excessiveCallersCount,
            'mailCount' => $this->mailCount,
            'mailFiles' => $this->mailFiles,
            'processingTime' => $this->processingTime,
            'peakMemory' => $this->peakMemory,
        ];
    }

    /**
     * Returns a copy enriched with processing time and peak memory usage.
     *
     * Usage example:
     *
     * ```php
     * $profiledSummary = $summary->withProfiling(0.015, 2_097_152);
     * ```
     *
     * @param float $processingTime Processing duration in seconds.
     * @param int $peakMemory Peak memory in bytes.
     *
     * @return self Request metadata enriched with profiling values.
     */
    public function withProfiling(float $processingTime, int $peakMemory): self
    {
        return new self(
            tag: $this->tag,
            url: $this->url,
            ajax: $this->ajax,
            method: $this->method,
            ip: $this->ip,
            time: $this->time,
            statusCode: $this->statusCode,
            sqlCount: $this->sqlCount,
            excessiveCallersCount: $this->excessiveCallersCount,
            mailCount: $this->mailCount,
            mailFiles: $this->mailFiles,
            processingTime: $processingTime,
            peakMemory: $peakMemory,
        );
    }
}

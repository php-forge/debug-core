<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Immutable Request identity with optional response, timing, and display metadata.
 */
final class RequestHero
{
    /**
     * Formatted processing time, in milliseconds; empty when it was not captured.
     */
    private string $durationMs = '';
    /**
     * Boolean flags surfaced as chips on the meta strip, in display order.
     *
     * @var list<string>
     */
    private array $flags = [];
    /**
     * Client address of the request; empty when it was not captured.
     */
    private string $ip = '';
    /**
     * Response status code; `0` when no response was captured.
     */
    private int $statusCode = 0;
    /**
     * Status-pill CSS modifier derived from the status code: `'2xx'` to `'5xx'`, or `'none'` when uncaptured.
     */
    private string $statusVariant = 'none';
    /**
     * Formatted wall-clock time of the request; empty when it was not captured.
     */
    private string $time = '';

    /**
     * @param string $method HTTP method of the request.
     * @param string $url Full URL of the request.
     */
    public function __construct(private string $method, private string $url) {}

    /**
     * Creates a hero carrying the request identity, ready for immutable enrichment.
     *
     * @param string $method HTTP method of the request.
     * @param string $url Full URL of the request.
     *
     * @return self Hero carrying only the request identity.
     */
    public static function create(string $method, string $url): self
    {
        return new self($method, $url);
    }

    /**
     * Returns the processing time of the request.
     *
     * @return string Formatted processing time, in milliseconds; empty when it was not captured.
     */
    public function getDurationMs(): string
    {
        return $this->durationMs;
    }

    /**
     * Returns the flags shown on the meta strip.
     *
     * @return list<string> Boolean flags surfaced as chips on the meta strip, in display order.
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * Returns the client address of the request.
     *
     * @return string Client address of the request; empty when it was not captured.
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * Returns the HTTP method of the request.
     *
     * @return string HTTP method of the request.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Returns the response status code.
     *
     * @return int Response status code; `0` when no response was captured.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Returns the status-pill modifier of the response.
     *
     * @return string Status-pill CSS modifier derived from the status code: `'2xx'` to `'5xx'`, or
     * `'none'` when uncaptured.
     */
    public function getStatusVariant(): string
    {
        return $this->statusVariant;
    }

    /**
     * Returns the wall-clock time of the request.
     *
     * @return string Formatted wall-clock time of the request; empty when it was not captured.
     */
    public function getTime(): string
    {
        return $this->time;
    }

    /**
     * Returns the full URL of the request.
     *
     * @return string Full URL of the request.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns a copy carrying another set of flags.
     *
     * @param list<string> $flags Boolean flags surfaced as chips on the meta strip, in display order.
     *
     * @return self Hero with the flags applied.
     */
    public function withFlags(array $flags): self
    {
        $clone = clone $this;
        $clone->flags = $flags;

        return $clone;
    }

    /**
     * Returns a copy carrying the client address.
     *
     * @param string $ip Client address of the request; empty when it was not captured.
     *
     * @return self Hero with the address applied.
     */
    public function withIp(string $ip): self
    {
        $clone = clone $this;
        $clone->ip = $ip;

        return $clone;
    }

    /**
     * Returns a copy carrying the captured response status.
     *
     * @param int $statusCode Response status code; `0` when no response was captured.
     * @param string $statusVariant Status-pill CSS modifier derived from the status code: `'2xx'` to
     * `'5xx'`, or `'none'` when uncaptured.
     *
     * @return self Hero with the status applied.
     */
    public function withStatus(int $statusCode, string $statusVariant): self
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;
        $clone->statusVariant = $statusVariant;

        return $clone;
    }

    /**
     * Returns a copy carrying the captured timing.
     *
     * @param string $time Formatted wall-clock time of the request; empty when it was not captured.
     * @param string $durationMs Formatted processing time, in milliseconds; empty when it was not captured.
     *
     * @return self Hero with the timing applied.
     */
    public function withTiming(string $time, string $durationMs): self
    {
        $clone = clone $this;
        $clone->time = $time;
        $clone->durationMs = $durationMs;

        return $clone;
    }
}

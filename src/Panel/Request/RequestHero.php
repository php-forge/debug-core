<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Request;

/**
 * Immutable Request identity with optional response, timing, and display metadata.
 */
final class RequestHero
{
    private string $durationMs = '';

    /**
     * @var list<string>
     */
    private array $flags = [];

    private string $ip = '';
    private int $statusCode = 0;

    private string $statusVariant = 'none';

    private string $time = '';

    public function __construct(private string $method, private string $url) {}

    public static function create(string $method, string $url): self
    {
        return new self($method, $url);
    }

    public function getDurationMs(): string
    {
        return $this->durationMs;
    }

    /**
     * @return list<string>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getStatusVariant(): string
    {
        return $this->statusVariant;
    }

    public function getTime(): string
    {
        return $this->time;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param list<string> $flags
     */
    public function withFlags(array $flags): self
    {
        $clone = clone $this;
        $clone->flags = $flags;

        return $clone;
    }

    public function withIp(string $ip): self
    {
        $clone = clone $this;
        $clone->ip = $ip;

        return $clone;
    }

    public function withStatus(int $statusCode, string $statusVariant): self
    {
        $clone = clone $this;
        $clone->statusCode = $statusCode;
        $clone->statusVariant = $statusVariant;

        return $clone;
    }

    public function withTiming(string $time, string $durationMs): self
    {
        $clone = clone $this;
        $clone->time = $time;
        $clone->durationMs = $durationMs;

        return $clone;
    }
}

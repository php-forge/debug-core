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
        $copy = clone $this;
        $copy->flags = $flags;

        return $copy;
    }

    public function withIp(string $ip): self
    {
        $copy = clone $this;
        $copy->ip = $ip;

        return $copy;
    }

    public function withStatus(int $statusCode, string $statusVariant): self
    {
        $copy = clone $this;
        $copy->statusCode = $statusCode;
        $copy->statusVariant = $statusVariant;

        return $copy;
    }

    public function withTiming(string $time, string $durationMs): self
    {
        $copy = clone $this;
        $copy->time = $time;
        $copy->durationMs = $durationMs;

        return $copy;
    }
}

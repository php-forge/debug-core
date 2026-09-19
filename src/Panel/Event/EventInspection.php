<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Event;

use PHPForge\Debug\Storage\{HydrationException, PanelRow, Payload};

use function count;
use function in_array;
use function is_string;
use function strlen;

/**
 * Immutable optional diagnostics; correlation identifies an observed scope, never a listener execution.
 */
final class EventInspection implements PanelRow
{
    /**
     * Monotonic observation time in seconds, or `null` when no clock reading was captured.
     */
    private float|null $clock = null;

    /**
     * Explicit adapter-selected scalar fields observed with the event.
     *
     * @var array<string, string>
     */
    private array $context = [];

    /**
     * Context capture state: `disabled`, `captured`, `unsupported`, or `failed`.
     */
    private string $contextStatus = 'disabled';

    /**
     * Observed nesting depth of the scope the event belongs to.
     */
    private int $depth = 0;

    /**
     * Request-local scope identity correlating lifecycle markers, or `null` when no correlation was observed.
     */
    private int|null $pairId = null;

    /**
     * Lifecycle marker: `enter`, `leave`, or empty for ordinary events.
     */
    private string $phase = '';

    /**
     * Argument-free source frames observed when the event fired.
     *
     * @var list<string>
     */
    private array $trace = [];

    /**
     * Trace capture state: `disabled`, `captured`, or `failed`.
     */
    private string $traceStatus = 'disabled';

    /**
     * Hydrates event diagnostics from decoded JSON data.
     *
     * @param mixed $data Decoded event diagnostics payload.
     * @param string $path Payload path used in hydration errors.
     *
     * @throws HydrationException When a value exceeds its bound or a capture state is unknown.
     *
     * @return self Hydrated event diagnostics.
     */
    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)->shape(
            [
                'context',
                'trace',
                'contextStatus',
                'traceStatus',
                'pairId',
                'phase',
                'depth',
                'clock',
            ],
        );

        $context = [];

        foreach ($payload->map('context') as $key => $value) {
            if (!is_string($value) || strlen($value) > 2048 || strlen($key) > 128) {
                throw HydrationException::at(
                    "{$path}.context.{$key}",
                    'bounded text',
                );
            }

            $context[$key] = $value;
        }

        $trace = [];

        foreach ($payload->list('trace') as $index => $value) {
            if (!is_string($value) || strlen($value) > 2048) {
                throw HydrationException::at(
                    "{$path}.trace[{$index}]",
                    'bounded text',
                );
            }

            $trace[] = $value;
        }

        $contextStatus = $payload->string('contextStatus');
        $traceStatus = $payload->string('traceStatus');
        $phase = $payload->string('phase');
        $depth = $payload->int('depth');
        $pairId = $payload->nullableInt('pairId');
        $clock = $payload->nullableNumber('clock');

        if (
            count($context) > 16 || count($trace) > 16
            || !in_array($contextStatus, ['disabled', 'captured', 'unsupported', 'failed'], true)
            || !in_array($traceStatus, ['disabled', 'captured', 'failed'], true)
            || !in_array($phase, ['', 'enter', 'leave'], true)
            || $depth < 0 || ($pairId !== null && $pairId < 1) || ($clock !== null && $clock < 0)
        ) {
            throw HydrationException::at(
                $path,
                'bounded event diagnostics with valid capture states',
            );
        }

        return (new self())
            ->withContext($context, $contextStatus)
            ->withTrace($trace, $traceStatus)
            ->withLifecycle($pairId, $phase, $depth, $clock);
    }

    /**
     * Returns the monotonic observation time.
     *
     * @return float|null Observation time in seconds, or `null` when no clock reading was captured.
     */
    public function getClock(): float|null
    {
        return $this->clock;
    }

    /**
     * Returns the selected context fields.
     *
     * @return array<string, string> Explicit adapter-selected scalar fields.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Returns the context capture state.
     *
     * @return string One of `disabled`, `captured`, `unsupported`, or `failed`.
     */
    public function getContextStatus(): string
    {
        return $this->contextStatus;
    }

    /**
     * Returns the observed nesting depth.
     *
     * @return int Nesting depth of the scope the event belongs to.
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Returns the request-local scope identity.
     *
     * @return int|null Scope identity correlating lifecycle markers, or `null` when none was observed.
     */
    public function getPairId(): int|null
    {
        return $this->pairId;
    }

    /**
     * Returns the lifecycle marker.
     *
     * @return string `enter`, `leave`, or empty for ordinary events.
     */
    public function getPhase(): string
    {
        return $this->phase;
    }

    /**
     * Returns the captured source frames.
     *
     * @return list<string> Argument-free source frames.
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * Returns the trace capture state.
     *
     * @return string One of `disabled`, `captured`, or `failed`.
     */
    public function getTraceStatus(): string
    {
        return $this->traceStatus;
    }

    /**
     * Returns the diagnostics for JSON serialization.
     *
     * @return array<string, mixed> Serialized context, trace, capture states, and lifecycle correlation.
     */
    public function jsonSerialize(): array
    {
        return [
            'context' => $this->context,
            'trace' => $this->trace,
            'contextStatus' => $this->contextStatus,
            'traceStatus' => $this->traceStatus,
            'pairId' => $this->pairId,
            'phase' => $this->phase,
            'depth' => $this->depth,
            'clock' => $this->clock,
        ];
    }

    /**
     * Returns a copy with selected context and its capture state replaced together.
     *
     * @param array<string, string> $context Explicit adapter-selected scalar fields, not an object dump.
     * @param string $contextStatus One of `disabled`, `captured`, `unsupported`, or `failed`.
     *
     * @return self Diagnostics with the context and its capture state applied.
     */
    public function withContext(array $context, string $contextStatus): self
    {
        $clone = clone $this;
        $clone->context = $context;
        $clone->contextStatus = $contextStatus;

        return $clone;
    }

    /**
     * Returns a copy with the complete observed lifecycle correlation replaced.
     *
     * @param int|null $pairId Request-local scope identity; `null` means no correlation was observed.
     * @param string $phase Empty for ordinary events; `enter` or `leave` for lifecycle markers.
     * @param int $depth Observed nesting depth.
     * @param float|null $clock Monotonic observation time in seconds, not a wall-clock timestamp.
     *
     * @return self Diagnostics with the lifecycle correlation applied.
     */
    public function withLifecycle(int|null $pairId, string $phase, int $depth, float|null $clock): self
    {
        $clone = clone $this;
        $clone->pairId = $pairId;
        $clone->phase = $phase;
        $clone->depth = $depth;
        $clone->clock = $clock;

        return $clone;
    }

    /**
     * Returns a copy with the source trace and its capture state replaced together.
     *
     * @param list<string> $trace Argument-free source frames.
     * @param string $traceStatus One of `disabled`, `captured`, or `failed`.
     *
     * @return self Diagnostics with the trace and its capture state applied.
     */
    public function withTrace(array $trace, string $traceStatus): self
    {
        $clone = clone $this;
        $clone->trace = $trace;
        $clone->traceStatus = $traceStatus;

        return $clone;
    }
}

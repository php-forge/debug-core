<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use PHPForge\Debug\Storage\{PanelRow, Payload};

use function array_values;
use function hash;
use function in_array;
use function json_encode;
use function max;
use function preg_match;
use function str_contains;
use function strtoupper;

use const JSON_THROW_ON_ERROR;

/**
 * Typed view-model for a single database query row consumed by the queries grid.
 */
final readonly class QueryRow implements PanelRow
{
    public function __construct(
        /**
         * Uppercase SQL command verb (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, ...).
         */
        public string $type,
        /**
         * Full SQL statement as emitted by the profile log.
         */
        public string $query,
        /**
         * Statement execution time in milliseconds.
         */
        public float $duration,
        /**
         * @var list<array<string, mixed>> Backtrace frames captured for the statement.
         */
        public array $trace,
        /**
         * Stable hash of the backtrace, used to count caller duplicates.
         */
        public string $traceHash,
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        public float $timestamp,
        /**
         * Zero-based sequence index assigned by the panel.
         */
        public int $seq,
        /**
         * Number of times the exact same query was emitted in this request (`>= 1`).
         */
        public int $duplicate,
        /**
         * Number of rows returned/affected, or `null` when the driver did not report it.
         */
        public int|null $rows,
    ) {}

    /**
     * Creates a captured statement with milliseconds as the common time unit.
     */
    public static function create(string $query, float $duration, float $timestamp): self
    {
        preg_match('/^\\s*([a-zA-Z]+)/', $query, $matches);

        return new self(
            strtoupper($matches[1] ?? ''),
            $query,
            $duration,
            [],
            '',
            $timestamp,
            0,
            1,
            null,
        );
    }

    public static function fromArray(mixed $data, string $path): self
    {
        $payload = Payload::object($data, $path)
            ->shape(
                [
                    'type',
                    'query',
                    'duration',
                    'trace',
                    'traceHash',
                    'timestamp',
                    'seq',
                    'duplicate',
                    'rows',
                ],
            );

        return new self(
            type: $payload->string('type'),
            query: $payload->string('query'),
            duration: $payload->number('duration'),
            trace: $payload->rows('trace'),
            traceHash: $payload->string('traceHash'),
            timestamp: $payload->number('timestamp'),
            seq: $payload->int('seq'),
            duplicate: max(1, $payload->int('duplicate')),
            rows: $payload->nullableInt('rows'),
        );
    }

    /**
     * Builds a typed row from one resolved logger timing.
     *
     * @param string $type Uppercase SQL command verb extracted from the statement.
     * @param int $seq Zero-based sequence index.
     * @param int $duplicate Number of times the same statement was emitted in this request.
     * @param int|null $rows Rows reported by the driver, or `null` when it reported none.
     * @param array{
     *   info: string,
     *   category: string,
     *   timestamp: float,
     *   trace: array<int, array<string, mixed>>,
     *   level: int,
     *   duration: float,
     *   memory: int,
     *   memoryDiff: int,
     *   traceHash: string
     * } $timing Resolved timing.
     */
    public static function fromTiming(array $timing, string $type, int $seq, int $duplicate, int|null $rows): self
    {
        return new self(
            type: $type,
            query: $timing['info'],
            duration: $timing['duration'] * 1000,
            trace: array_values($timing['trace']),
            traceHash: $timing['traceHash'],
            timestamp: $timing['timestamp'] * 1000,
            seq: $seq,
            duplicate: max(1, $duplicate),
            rows: $rows,
        );
    }

    /**
     * Returns whether the statement produces a useful EXPLAIN plan.
     *
     * Only single table-touching DML verbs (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `WITH`) qualify;
     * multi-statement text and other verbs either error or return noise under EXPLAIN.
     */
    public function isExplainable(): bool
    {
        return in_array(strtoupper($this->type), ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'WITH'], true)
            && !str_contains($this->query, ';');
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'query' => $this->query,
            'duration' => $this->duration,
            'trace' => $this->trace,
            'traceHash' => $this->traceHash,
            'timestamp' => $this->timestamp,
            'seq' => $this->seq,
            'duplicate' => $this->duplicate,
            'rows' => $this->rows,
        ];
    }

    /**
     * Returns a copy with the given duplicate count.
     *
     * @param int $duplicate Number of times the exact same statement was emitted in this request (`>= 1`).
     */
    public function withDuplicate(int $duplicate): self
    {
        return new self(
            type: $this->type,
            query: $this->query,
            duration: $this->duration,
            trace: $this->trace,
            traceHash: $this->traceHash,
            timestamp: $this->timestamp,
            seq: $this->seq,
            duplicate: $duplicate,
            rows: $this->rows,
        );
    }

    /**
     * Returns a copy with the driver-reported row count, or `null` when the driver did not report one.
     */
    public function withRows(int|null $rows): self
    {
        return new self(
            type: $this->type,
            query: $this->query,
            duration: $this->duration,
            trace: $this->trace,
            traceHash: $this->traceHash,
            timestamp: $this->timestamp,
            seq: $this->seq,
            duplicate: $this->duplicate,
            rows: $rows,
        );
    }

    /**
     * Returns a copy with the given sequence index.
     */
    public function withSequence(int $sequence): self
    {
        return new self(
            type: $this->type,
            query: $this->query,
            duration: $this->duration,
            trace: $this->trace,
            traceHash: $this->traceHash,
            timestamp: $this->timestamp,
            seq: $sequence,
            duplicate: $this->duplicate,
            rows: $this->rows,
        );
    }

    /**
     * Returns a copy with the given source frames and the caller hash derived from them.
     *
     * @param list<array<string, mixed>> $trace Argument-free source frames.
     */
    public function withTrace(array $trace): self
    {
        return new self(
            type: $this->type,
            query: $this->query,
            duration: $this->duration,
            trace: $trace,
            traceHash: $trace === [] ? '' : hash('sha256', json_encode($trace, JSON_THROW_ON_ERROR)),
            timestamp: $this->timestamp,
            seq: $this->seq,
            duplicate: $this->duplicate,
            rows: $this->rows,
        );
    }
}

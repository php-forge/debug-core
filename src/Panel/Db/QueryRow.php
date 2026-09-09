<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Db;

use PHPForge\Debug\Helper\Format;
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
final class QueryRow implements PanelRow
{
    /**
     * Single table-touching verbs that produce a useful EXPLAIN plan.
     */
    private const array EXPLAINABLE_TYPES = [
        'SELECT',
        'INSERT',
        'UPDATE',
        'DELETE',
        'REPLACE',
        'WITH',
    ];

    /**
     * Number of times the exact same query was emitted in this request (`>= 1`).
     */
    private int $duplicate = 1;
    /**
     * Number of rows returned/affected, or `null` when the driver did not report it.
     */
    private int|null $rows = null;
    /**
     * Zero-based sequence index assigned by the panel.
     */
    private int $seq = 0;
    /**
     * @var list<array<string, mixed>> Backtrace frames captured for the statement.
     */
    private array $trace = [];
    /**
     * Stable hash of the backtrace, used to count caller duplicates.
     */
    private string $traceHash = '';
    /**
     * SQL command verb extracted from the statement or explicitly supplied by the adapter.
     */
    private string $type;

    private function __construct(
        /**
         * Full SQL statement as emitted by the profile log.
         */
        private string $query,
        /**
         * Statement execution time in milliseconds.
         */
        private float $duration,
        /**
         * Capture timestamp in milliseconds since the Unix epoch.
         */
        private float $timestamp,
    ) {
        $this->type = self::extractType($query);
    }

    /**
     * Creates a captured statement with milliseconds as the common time unit.
     *
     * @param string $query Full SQL statement.
     * @param float $duration Execution time in milliseconds.
     * @param float $timestamp Capture timestamp in milliseconds since the Unix epoch.
     *
     * @return self Row carrying the extracted command verb, without trace, sequence, or row count.
     */
    public static function create(string $query, float $duration, float $timestamp): self
    {
        return new self($query, $duration, $timestamp);
    }

    /**
     * Returns the uppercase leading command verb of a statement, or `''` when it starts with no letter.
     *
     * @param string $sql SQL statement to inspect.
     *
     * @return string Uppercase command verb, or `''`.
     */
    public static function extractType(string $sql): string
    {
        preg_match('/^\\s*([a-zA-Z]+)/', $sql, $matches);

        return strtoupper($matches[1] ?? '');
    }

    /**
     * Returns the row whose sequence matches the requested one exactly, or `null` when none does.
     *
     * The sequence is compared as a string, so a padded request such as `'02'` never matches sequence `2`.
     *
     * @param list<self> $rows Captured rows to search.
     * @param string $seq Requested sequence number.
     *
     * @return self|null Matching row, or `null` when the sequence was not captured.
     */
    public static function findBySequence(array $rows, string $seq): self|null
    {
        foreach ($rows as $row) {
            if ((string) $row->seq === $seq) {
                return $row;
            }
        }

        return null;
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

        $type = $payload->string('type');
        $query = $payload->string('query');
        $duration = $payload->number('duration');
        $trace = $payload->rows('trace');
        $traceHash = $payload->string('traceHash');
        $timestamp = $payload->number('timestamp');
        $seq = $payload->int('seq');
        $duplicate = max(1, $payload->int('duplicate'));
        $rows = $payload->nullableInt('rows');

        $row = new self(
            $query,
            $duration,
            $timestamp,
        );

        $row->type = $type;
        $row->trace = $trace;
        $row->traceHash = $traceHash;
        $row->seq = $seq;
        $row->duplicate = $duplicate;
        $row->rows = $rows;

        return $row;
    }

    /**
     * Builds a typed row from one resolved logger timing.
     *
     * @param string $type Uppercase SQL command verb extracted from the statement.
     * @param int $seq Zero-based sequence index.
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
     *
     * The duplicate count starts at `1`; {@see DbSnapshot::capture()} resolves the real occurrences across the request.
     *
     * @return self Row with the timing scaled to milliseconds.
     */
    public static function fromTiming(array $timing, string $type, int $seq, int|null $rows): self
    {
        $row = new self(
            $timing['info'],
            $timing['duration'] * Format::MILLISECONDS_PER_SECOND,
            $timing['timestamp'] * Format::MILLISECONDS_PER_SECOND,
        );

        $row->type = $type;
        $row->trace = array_values($timing['trace']);
        $row->traceHash = $timing['traceHash'];
        $row->seq = $seq;
        $row->rows = $rows;

        return $row;
    }

    /**
     * Returns the number of occurrences of this exact query.
     */
    public function getDuplicate(): int
    {
        return $this->duplicate;
    }

    /**
     * Returns the execution time in milliseconds.
     */
    public function getDuration(): float
    {
        return $this->duration;
    }

    /**
     * Returns the captured SQL statement.
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * Returns the driver-reported row count, or `null` when unavailable.
     */
    public function getRows(): int|null
    {
        return $this->rows;
    }

    /**
     * Returns the zero-based capture sequence.
     */
    public function getSequence(): int
    {
        return $this->seq;
    }

    /**
     * Returns the capture timestamp in milliseconds since the Unix epoch.
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Returns the captured source frames.
     *
     * @return list<array<string, mixed>> Captured backtrace frames.
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * Returns the captured or derived caller hash.
     */
    public function getTraceHash(): string
    {
        return $this->traceHash;
    }

    /**
     * Returns the extracted or explicitly captured command verb.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns whether the statement produces a useful EXPLAIN plan.
     *
     * Only single table-touching DML verbs (`SELECT`, `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `WITH`) qualify;
     * multi-statement text and other verbs either error or return noise under EXPLAIN.
     *
     * @return bool `true` when the statement can be explained; `false` otherwise.
     */
    public function isExplainable(): bool
    {
        return in_array(strtoupper($this->type), self::EXPLAINABLE_TYPES, true)
            && !str_contains($this->query, ';');
    }

    /**
     * Returns the persisted row contract.
     *
     * @return array<string, mixed> Row fields in their persisted order.
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
     *
     * @return self Copy carrying the duplicate count.
     */
    public function withDuplicate(int $duplicate): self
    {
        $clone = clone $this;
        $clone->duplicate = $duplicate;

        return $clone;
    }

    /**
     * Returns a copy with the driver-reported row count, or `null` when the driver did not report one.
     *
     * @param int|null $rows Rows returned or affected, or `null` when the driver reported none.
     *
     * @return self Copy carrying the row count.
     */
    public function withRows(int|null $rows): self
    {
        $clone = clone $this;
        $clone->rows = $rows;

        return $clone;
    }

    /**
     * Returns a copy with the given sequence index.
     *
     * @param int $sequence Zero-based sequence index assigned by the panel.
     *
     * @return self Copy carrying the sequence index.
     */
    public function withSequence(int $sequence): self
    {
        $clone = clone $this;
        $clone->seq = $sequence;

        return $clone;
    }

    /**
     * Returns a copy with the given source frames and the caller hash derived from them.
     *
     * @param list<array<string, mixed>> $trace Argument-free source frames.
     *
     * @return self Copy carrying the frames and their derived caller hash.
     */
    public function withTrace(array $trace): self
    {
        $clone = clone $this;
        $clone->trace = $trace;
        $clone->traceHash = $trace === [] ? '' : hash('sha256', json_encode($trace, JSON_THROW_ON_ERROR));

        return $clone;
    }

    /**
     * Returns a copy with an explicitly captured caller hash without changing the source frames.
     *
     * Call after {@see self::withTrace()} to preserve a logger-provided hash instead of the derived hash.
     * A subsequent `withTrace()` call derives the hash again.
     *
     * @param string $traceHash Captured caller hash, or `''` when no caller was captured.
     *
     * @return self Copy carrying the hash unchanged.
     */
    public function withTraceHash(string $traceHash): self
    {
        $clone = clone $this;
        $clone->traceHash = $traceHash;

        return $clone;
    }

    /**
     * Returns a copy with an explicitly captured SQL command verb without changing the statement.
     *
     * @param string $type Captured command verb, preserved without normalization.
     *
     * @return self Copy carrying the verb unchanged.
     */
    public function withType(string $type): self
    {
        $clone = clone $this;
        $clone->type = $type;

        return $clone;
    }
}

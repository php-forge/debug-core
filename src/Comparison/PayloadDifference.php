<?php

declare(strict_types=1);

namespace PHPForge\Debug\Comparison;

use function array_diff_key;
use function array_key_exists;
use function count;
use function hash;
use function is_array;
use function serialize;
use function str_replace;

/**
 * Counts structural payload differences without retaining diagnostic values or their fingerprints.
 */
final readonly class PayloadDifference
{
    /**
     * @param int $added Leaves present only in the target payload.
     * @param int $removed Leaves present only in the baseline payload.
     * @param int $changed Leaves present in both payloads under a different value.
     * @param int $unchanged Leaves present in both payloads under the same value.
     */
    private function __construct(
        public int $added,
        public int $removed,
        public int $changed,
        public int $unchanged,
    ) {}

    /**
     * Compares typed leaves at escaped paths, treating an empty array as a leaf and `null` as an absent payload.
     *
     * @param array<string, mixed>|null $baseline Baseline payload, or `null` when not captured.
     * @param array<string, mixed>|null $target Target payload, or `null` when not captured.
     *
     * @return self Structural difference counts for the two payloads.
     */
    public static function between(array|null $baseline, array|null $target): self
    {
        $baselineLeaves = self::flatten($baseline);
        $targetLeaves = self::flatten($target);

        $changed = 0;
        $unchanged = 0;

        foreach ($baselineLeaves as $path => $baselineValue) {
            if (!array_key_exists($path, $targetLeaves)) {
                continue;
            }

            if ($baselineValue === $targetLeaves[$path]) {
                ++$unchanged;
            } else {
                ++$changed;
            }
        }

        return new self(
            added: count(array_diff_key($targetLeaves, $baselineLeaves)),
            removed: count(array_diff_key($baselineLeaves, $targetLeaves)),
            changed: $changed,
            unchanged: $unchanged,
        );
    }

    /**
     * Reduces a payload to its typed leaves indexed by escaped path.
     *
     * @param array<string, mixed>|null $payload Payload to flatten, or `null` when not captured.
     *
     * @return array<string, string> Leaf fingerprints indexed by escaped path.
     */
    private static function flatten(array|null $payload): array
    {
        $leaves = [];

        if ($payload !== null) {
            self::flattenValue($payload, '$', $leaves);
        }

        return $leaves;
    }

    /**
     * Records the typed fingerprint of every leaf reachable from the value.
     *
     * @param mixed $value Value to traverse.
     * @param string $path Escaped path of the value within the payload.
     * @param array<string, string> $leaves Accumulator receiving leaf fingerprints indexed by escaped path.
     */
    private static function flattenValue(mixed $value, string $path, array &$leaves): void
    {
        if (is_array($value)) {
            if ($value === []) {
                $leaves[$path] = 'array:[]';
            }

            foreach ($value as $key => $child) {
                $segment = str_replace(['~', '/'], ['~0', '~1'], (string) $key);

                self::flattenValue($child, "{$path}/{$segment}", $leaves);
            }

            return;
        }

        $leaves[$path] = hash('sha256', serialize($value));
    }
}

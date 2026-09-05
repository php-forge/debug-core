<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Comparison;

use PHPForge\Debug\Comparison\PayloadDifference;
use PHPUnit\Framework\Attributes\{DataProvider, Group};
use PHPUnit\Framework\TestCase;

use function get_object_vars;

/**
 * Unit tests for typed structural comparisons without retaining or modifying diagnostic values.
 */
#[Group('history')]
final class PayloadDifferenceTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>|null, array<string, mixed>|null, array{int, int, int, int}}>
     */
    public static function payloads(): iterable
    {
        yield 'binary strings' => [
            ['value' => "\xFF\0"],
            ['value' => "\xFE\0"],
            [0, 0, 1, 0],
        ];
        yield 'both absent' => [
            null,
            null,
            [0, 0, 0, 0],
        ];
        yield 'both empty' => [
            [],
            [],
            [0, 0, 0, 1],
        ];
        yield 'empty array versus null' => [
            ['value' => []],
            ['value' => null],
            [0, 0, 1, 0],
        ];
        yield 'empty array versus string' => [
            ['value' => []],
            ['value' => 'array:[]'],
            [0, 0, 1, 0],
        ];
        yield 'empty captured' => [
            null,
            [],
            [1, 0, 0, 0],
        ];
        yield 'empty key versus root' => [
            ['' => []],
            [],
            [1, 1, 0, 0],
        ];
        yield 'empty removed' => [
            [],
            null,
            [0, 1, 0, 0],
        ];
        yield 'false versus zero' => [
            ['value' => false],
            ['value' => 0],
            [0, 0, 1, 0],
        ];
        yield 'integer versus float' => [
            ['value' => 0],
            ['value' => 0.0],
            [0, 0, 1, 0],
        ];
        yield 'list order' => [
            ['items' => [1, 2]],
            ['items' => [2, 1]],
            [0, 0, 2, 0],
        ];
        yield 'map order' => [
            ['a' => 1, 'b' => 2],
            ['b' => 2, 'a' => 1],
            [0, 0, 0, 2],
        ];
        yield 'mixed counters' => [
            ['removed' => 'raw', 'items' => [1, 2], 'value' => false],
            ['added' => 'raw', 'items' => [1, 2, 3], 'value' => null],
            [2, 1, 1, 2],
        ];
        yield 'nested empty expanded' => [
            ['value' => []],
            ['value' => [false]],
            [1, 1, 0, 0],
        ];
        yield 'null leaf captured' => [
            null,
            ['value' => null],
            [1, 0, 0, 0],
        ];
        yield 'null leaf unchanged' => [
            ['value' => null],
            ['value' => null],
            [0, 0, 0, 1],
        ];
        yield 'null versus false' => [
            ['value' => null],
            ['value' => false],
            [0, 0, 1, 0],
        ];
        yield 'slash versus escaped tilde' => [
            ['a/b' => 1],
            ['a~0b' => 1],
            [1, 1, 0, 0],
        ];
        yield 'slash versus nesting' => [
            ['a/b' => 1],
            ['a' => ['b' => 1]],
            [1, 1, 0, 0],
        ];
        yield 'slash versus plain key' => [
            ['a/b' => 1],
            ['ab' => 1],
            [1, 1, 0, 0],
        ];
        yield 'tilde escape collision' => [
            ['a~0b' => 1],
            ['a~b' => 1],
            [1, 1, 0, 0],
        ];
        yield 'tilde versus escaped slash' => [
            ['a~1b' => 1],
            ['a/b' => 1],
            [1, 1, 0, 0],
        ];
        yield 'zero versus string' => [
            ['value' => 0],
            ['value' => '0'],
            [0, 0, 1, 0],
        ];
    }

    /**
     * @param array<string, mixed>|null $baseline
     * @param array<string, mixed>|null $target
     * @param array{int, int, int, int} $expected
     */
    #[DataProvider('payloads')]
    public function testBetweenPreservesTypedLeafSemantics(array|null $baseline, array|null $target, array $expected): void
    {
        $originalBaseline = $baseline;
        $originalTarget = $target;

        $difference = PayloadDifference::between($baseline, $target);

        self::assertSame(
            $expected,
            [$difference->added, $difference->removed, $difference->changed, $difference->unchanged],
            'Structural counters must preserve typed values, paths, and capture presence.',
        );
        self::assertSame(
            $originalBaseline,
            $baseline,
            'Comparison must not modify the baseline.',
        );
        self::assertSame(
            $originalTarget,
            $target,
            'Comparison must not modify the target.',
        );
    }

    public function testResultRetainsOnlyCounters(): void
    {
        $difference = PayloadDifference::between(
            ['secret' => 'original-value'],
            ['secret' => 'other-value'],
        );

        self::assertSame(
            ['added' => 0, 'removed' => 0, 'changed' => 1, 'unchanged' => 0],
            get_object_vars($difference),
            'The result must expose counters rather than diagnostic values or fingerprints.',
        );
    }
}

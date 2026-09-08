<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Comparison;

use PHPForge\Debug\Comparison\PayloadDifference;
use PHPForge\Debug\Tests\Provider\PayloadDifferenceProvider;
use PHPUnit\Framework\Attributes\{DataProviderExternal, Group};
use PHPUnit\Framework\TestCase;

use function get_object_vars;

/**
 * Unit tests for typed structural comparisons without retaining or modifying diagnostic values.
 *
 * {@see PayloadDifferenceProvider} for test case data providers.
 */
#[Group('history')]
final class PayloadDifferenceTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $baseline
     * @param array<string, mixed>|null $target
     * @param array{int, int, int, int} $expected
     */
    #[DataProviderExternal(PayloadDifferenceProvider::class, 'payloads')]
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

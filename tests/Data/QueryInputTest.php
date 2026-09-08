<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Data;

use PHPForge\Debug\Data\QueryInput;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see QueryInput} covering `Prefix[attribute]` group extraction and scalar top-level reads from parsed
 * query parameters.
 */
#[Group('data')]
#[Group('filter')]
final class QueryInputTest extends TestCase
{
    public function testGroupDropsEmptyNonScalarAndNonStringKeyedEntries(): void
    {
        $filters = QueryInput::group(
            [
                'Debug' => [
                    'statusCode' => '404',
                    'url' => '',
                    'nested' => ['x' => '1'],
                    0 => 'indexed',
                    'count' => 7,
                ],
            ],
            'Debug',
        );

        self::assertSame(
            [
                'statusCode' => '404',
                'count' => '7',
            ],
            $filters,
            'Empty strings, arrays, and integer keys must be dropped; numeric values must stringify.',
        );
    }

    public function testGroupReturnsEmptyArrayWhenPrefixIsAbsentOrNotAnArray(): void
    {
        self::assertSame(
            [],
            QueryInput::group([], 'Debug'),
            'A missing prefix must yield no filters.',
        );
        self::assertSame(
            [],
            QueryInput::group(['Debug' => 'scalar'], 'Debug'),
            'A scalar under the prefix must yield no filters.',
        );
    }

    public function testMinimumBoundAcceptsFiniteNonNegativeNumbers(): void
    {
        self::assertSame(
            12.5,
            QueryInput::minimumBound('12.5'),
            'A decimal bound must be returned as a float.',
        );
        self::assertSame(
            0.0,
            QueryInput::minimumBound('0'),
            'Zero must remain an accepted lower bound.',
        );
        self::assertSame(
            1.0E+3,
            QueryInput::minimumBound('1e3'),
            'Scientific notation must be accepted.',
        );
    }

    public function testMinimumBoundRejectsUnusableValues(): void
    {
        self::assertNull(
            QueryInput::minimumBound(''),
            'Empty input must be rejected.',
        );
        self::assertNull(
            QueryInput::minimumBound('12ms'),
            'Non-numeric input must be rejected.',
        );
        self::assertNull(
            QueryInput::minimumBound('-0.5'),
            'Negative bounds must be rejected.',
        );
        self::assertNull(
            QueryInput::minimumBound('1e400'),
            'Overflowing input must be rejected.',
        );
    }

    public function testScalarReadsTopLevelStringsAndNumbers(): void
    {
        self::assertSame(
            'all',
            QueryInput::scalar(['per-page' => 'all'], 'per-page'),
            'Strings must pass through.',
        );
        self::assertSame(
            '25',
            QueryInput::scalar(['per-page' => 25], 'per-page'),
            'Integers must stringify.',
        );
        self::assertNull(
            QueryInput::scalar([], 'per-page'),
            "Missing parameters must yield 'null'.",
        );
        self::assertNull(
            QueryInput::scalar(['per-page' => ['25']], 'per-page'),
            "Array parameters must yield 'null'.",
        );
    }
}

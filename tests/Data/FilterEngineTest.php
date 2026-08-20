<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Data;

use PHPForge\Debug\Data\FilterEngine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Unit tests for {@see FilterEngine} covering the exact/partial/numeric operator grammar shared by every debug-panel
 * search model.
 */
#[Group('data')]
#[Group('filter')]
final class FilterEngineTest extends TestCase
{
    public function testAddConditionIgnoresEmptyAndNonScalarValues(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', '');
        $engine->addCondition('level', null);
        $engine->addCondition('level', ['error']);

        $rows = [
            ['level' => 'info'],
            ['level' => 'error'],
        ];

        self::assertSame(
            $engine->filter($rows),
            $rows,
            'Empty and non-scalar values must register no condition.',
        );
    }

    public function testAddMinimumConditionKeepsRowsAtOrAboveTheBound(): void
    {
        $engine = new FilterEngine();

        $engine->addMinimumCondition('duration', 0.5);

        self::assertSame(
            [['duration' => 0.5], ['duration' => 2]],
            $engine->filter([['duration' => 0.1], ['duration' => 0.5], ['duration' => 2]]),
            'Inclusive lower bound must keep the boundary row.',
        );
    }

    public function testFilterAppliesConditionsAfterNumericAndPartialMatches(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('duration', '>5');
        $engine->addCondition('category', 'db');

        self::assertSame(
            [
                [
                    'duration' => 6,
                    'category' => 'db',
                ],
            ],
            $engine->filter(
                [
                    ['duration' => 6, 'category' => 'app'],
                    ['duration' => 6, 'category' => 'db'],
                ],
            ),
            'A numeric match must not skip later conditions.',
        );

        $engine->addCondition('message', 'query', partial: true);
        $engine->addCondition('category', 'db');

        self::assertSame(
            [
                [
                    'message' => 'query complete',
                    'category' => 'db',
                ],
            ],
            $engine->filter(
                [
                    ['message' => 'query complete', 'category' => 'app'],
                    ['message' => 'query complete', 'category' => 'db'],
                ],
            ),
            'A partial match must not skip later conditions.',
        );
    }

    public function testFilterComparesNonScalarCandidatesThroughDumpExport(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('payload', 'alpha', partial: true);

        self::assertCount(
            1,
            $engine->filter(
                [
                    ['payload' => ['alpha' => 1]],
                    ['payload' => ['beta' => 2]],
                ],
            ),
            'Array candidates must match through their exported representation.',
        );
    }

    public function testFilterDropsRowsMissingTheAttribute(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', 'error');

        self::assertSame(
            [],
            $engine->filter([['category' => 'app']]),
            'Rows without the filtered attribute must be dropped.',
        );
    }

    public function testFilterMatchesCaseInsensitiveSubstringWhenPartial(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('message', 'SESSION', partial: true);

        self::assertSame(
            [['message' => 'Session started']],
            $engine->filter(
                [
                    ['message' => 'Session started'],
                    ['message' => 'Connection opened'],
                ],
            ),
            'Partial conditions must match case-insensitive substrings.',
        );

        $engine->addCondition('message', 'ÁRBOL', partial: true);

        self::assertSame(
            [['message' => 'El árbol crece']],
            $engine->filter(
                [
                    ['message' => 'El árbol crece'],
                    ['message' => 'Tree'],
                ],
            ),
            'Partial conditions must apply Unicode-aware case folding.',
        );
    }

    public function testFilterMatchesCaseInsensitiveWholeValueByDefault(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', 'ERROR');

        self::assertSame(
            [['level' => 'error']],
            $engine->filter(
                [
                    ['level' => 'error'],
                    ['level' => 'error-handler'],
                ],
            ),
            'Default conditions must match the whole value case-insensitively.',
        );

        $engine->addCondition('label', 'ÜBER');

        self::assertSame(
            [['label' => 'über']],
            $engine->filter(
                [
                    ['label' => 'über'],
                    ['label' => 'uber'],
                ],
            ),
            'Whole-value conditions must apply Unicode-aware case folding to both operands.',
        );

        $engine->addCondition('label', 'über');

        self::assertSame(
            [['label' => 'ÜBER']],
            $engine->filter(
                [
                    ['label' => 'ÜBER'],
                    ['label' => 'uber'],
                ],
            ),
            'Whole-value conditions must apply Unicode-aware case folding to the candidate.',
        );
    }

    public function testFilterParsesLeadingComparisonOperators(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('sqlCount', '> 5');

        self::assertSame(
            [['sqlCount' => 9]],
            $engine->filter(
                [
                    ['sqlCount' => 3],
                    ['sqlCount' => 5],
                    ['sqlCount' => 9],
                ],
            ),
            'A leading `>` must compare numerically.',
        );

        $engine->addCondition('duration', '<0.5');

        self::assertSame(
            [['duration' => '0.25']],
            $engine->filter(
                [
                    ['duration' => '0.25'],
                    ['duration' => '0.5'],
                    ['duration' => '0.75'],
                ],
            ),
            'A leading `<` must compare numeric strings numerically.',
        );
    }

    public function testFilterReadsPublicPropertiesFromObjectRows(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('method', 'get');

        $match = new class {
            public string $method = 'GET';
        };
        $miss = new class {
            public string $method = 'POST';
        };

        self::assertSame(
            [$match],
            $engine->filter([$match, $miss]),
            'Object rows must be matched through their public properties.',
        );
    }

    public function testFilterRejectsMalformedInternalConditions(): void
    {
        $engine = new FilterEngine();
        $property = new ReflectionProperty($engine, 'conditions');

        $property->setValue($engine, [['attribute' => 'value', 'operator' => '>', 'value' => '5']]);

        self::assertSame(
            [],
            $engine->filter([['value' => 6]]),
            'Numeric conditions with a non-float boundary must reject the row.',
        );

        $property->setValue($engine, [['attribute' => 'value', 'operator' => 'same', 'value' => 5.0]]);

        self::assertSame(
            [],
            $engine->filter([['value' => '5']]),
            'Text conditions with a non-string boundary must reject the row.',
        );
    }

    public function testFilterRejectsNonNumericCandidatesForNumericOperators(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('sqlCount', '>5');

        self::assertSame(
            [],
            $engine->filter(
                [
                    ['sqlCount' => 'many'],
                    ['sqlCount' => null],
                ],
            ),
            'Numeric operators must drop rows whose candidate is not numeric.',
        );
    }

    public function testFilterRequiresTheEntireComparisonGrammarToMatch(): void
    {
        foreach (['prefix >5', '>5 suffix'] as $value) {
            $engine = new FilterEngine();

            $engine->addCondition('value', $value);

            self::assertSame(
                [['value' => $value]],
                $engine->filter(
                    [
                        ['value' => $value],
                        ['value' => 9],
                    ],
                ),
                'Comparison syntax with leading or trailing data must remain a text condition.',
            );
        }

        $engine = new FilterEngine();

        $engine->addCondition('value', ">5\n");

        self::assertSame(
            [['value' => 9]],
            $engine->filter(
                [
                    ['value' => 3],
                    ['value' => 9],
                ],
            ),
            'Trailing whitespace, including a final newline, must remain valid comparison syntax.',
        );
    }

    public function testFilterResetsConditionsAfterEachRun(): void
    {
        $engine = new FilterEngine();

        $engine->addCondition('level', 'error');
        $engine->filter([['level' => 'info']]);

        $rows = [['level' => 'info']];

        self::assertSame(
            $rows,
            $engine->filter($rows),
            'Conditions must reset after each filter run.',
        );
    }
}

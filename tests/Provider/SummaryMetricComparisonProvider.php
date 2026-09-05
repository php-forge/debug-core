<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Storage\RequestSummary;

use function array_replace;

/**
 * Regression cases recorded from the adapters before sharing summary metric calculations.
 */
final class SummaryMetricComparisonProvider
{
    /**
     * @return iterable<string, array{RequestSummary, RequestSummary, int, array{string, string, string, string, string, string|null}}>
     */
    public static function metrics(): iterable
    {
        yield 'processingTime pair 13' => [
            self::summary(['processingTime' => null]),
            self::summary(['processingTime' => 1234.567895]),
            3,
            [
                'Duration',
                'Not captured',
                '1,234,567.90 ms',
                'Not comparable',
                'neutral',
                'profiling',
            ],
        ];
        yield 'processingTime pair 182' => [
            self::summary(['processingTime' => 1234.567895]),
            self::summary(['processingTime' => null]),
            3,
            [
                'Duration',
                '1,234,567.90 ms',
                'Not captured',
                'Not comparable',
                'neutral',
                'profiling',
            ],
        ];
        yield 'processingTime pair 18' => [
            self::summary(['processingTime' => 0.0]),
            self::summary(['processingTime' => 5e-06]),
            3,
            [
                'Duration',
                '0.00 ms',
                '0.01 ms',
                '+0.01 ms',
                'up',
                'profiling',
            ],
        ];
        yield 'processingTime pair 57' => [
            self::summary(['processingTime' => 5e-06]),
            self::summary(['processingTime' => 0.0]),
            3,
            [
                'Duration',
                '0.01 ms',
                '0.00 ms',
                '-0.01 ms (-100.0%)',
                'down',
                'profiling',
            ],
        ];
        yield 'processingTime pair 26' => [
            self::summary(['processingTime' => 0.0]),
            self::summary(['processingTime' => -0.001]),
            3,
            [
                'Duration',
                '0.00 ms',
                '-1.00 ms',
                '-1.00 ms',
                'down',
                'profiling',
            ],
        ];
        yield 'processingTime pair 173' => [
            self::summary(['processingTime' => -0.001]),
            self::summary(['processingTime' => 0.001]),
            3,
            [
                'Duration',
                '-1.00 ms',
                '1.00 ms',
                '+2.00 ms (+-200.0%)',
                'up',
                'profiling',
            ],
        ];
        yield 'processingTime pair 169' => [
            self::summary(['processingTime' => -0.001]),
            self::summary(['processingTime' => 0.0]),
            3,
            [
                'Duration',
                '-1.00 ms',
                '0.00 ms',
                '+1.00 ms (+-100.0%)',
                'up',
                'profiling',
            ],
        ];
        yield 'processingTime pair 135' => [
            self::summary(['processingTime' => 0.015]),
            self::summary(['processingTime' => 0.015]),
            3,
            [
                'Duration',
                '15.00 ms',
                '15.00 ms',
                'No change',
                'neutral',
                'profiling',
            ],
        ];
        yield 'processingTime pair 131' => [
            self::summary(['processingTime' => 0.015]),
            self::summary(['processingTime' => 0.001]),
            3,
            [
                'Duration',
                '15.00 ms',
                '1.00 ms',
                '-14.00 ms (-93.3%)',
                'down',
                'profiling',
            ],
        ];
        yield 'processingTime pair 76' => [
            self::summary(['processingTime' => 0.001]),
            self::summary(['processingTime' => 0.001004999999]),
            3,
            [
                'Duration',
                '1.00 ms',
                '1.00 ms',
                '+0.00 ms (+0.5%)',
                'up',
                'profiling',
            ],
        ];
        yield 'processingTime pair 77' => [
            self::summary(['processingTime' => 0.001]),
            self::summary(['processingTime' => 0.001005]),
            3,
            [
                'Duration',
                '1.00 ms',
                '1.01 ms',
                '+0.01 ms (+0.5%)',
                'up',
                'profiling',
            ],
        ];
        yield 'processingTime pair 91' => [
            self::summary(['processingTime' => 0.001004999999]),
            self::summary(['processingTime' => 0.001005]),
            3,
            [
                'Duration',
                '1.00 ms',
                '1.01 ms',
                '+0.00 ms (+0.0%)',
                'up',
                'profiling',
            ],
        ];
        yield 'processingTime pair 106' => [
            self::summary(['processingTime' => 0.001005]),
            self::summary(['processingTime' => 0.001005000001]),
            3,
            [
                'Duration',
                '1.01 ms',
                '1.01 ms',
                '+0.00 ms (+0.0%)',
                'up',
                'profiling',
            ],
        ];
        yield 'processingTime pair 164' => [
            self::summary(['processingTime' => 0.30000000000000004]),
            self::summary(['processingTime' => 0.3]),
            3,
            [
                'Duration',
                '300.00 ms',
                '300.00 ms',
                '0.00 ms (0.0%)',
                'down',
                'profiling',
            ],
        ];
        yield 'processingTime pair 151' => [
            self::summary(['processingTime' => 0.3]),
            self::summary(['processingTime' => 0.30000000000000004]),
            3,
            [
                'Duration',
                '300.00 ms',
                '300.00 ms',
                '+0.00 ms (+0.0%)',
                'up',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 7' => [
            self::summary(['peakMemory' => null]),
            self::summary(['peakMemory' => 10485760000000]),
            4,
            [
                'Peak memory',
                'Not captured',
                '10,000,000.00 MB',
                'Not comparable',
                'neutral',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 56' => [
            self::summary(['peakMemory' => 10485760000000]),
            self::summary(['peakMemory' => null]),
            4,
            [
                'Peak memory',
                '10,000,000.00 MB',
                'Not captured',
                'Not comparable',
                'neutral',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 10' => [
            self::summary(['peakMemory' => 0]),
            self::summary(['peakMemory' => 1]),
            4,
            [
                'Peak memory',
                '0.00 MB',
                '0.00 MB',
                '+0.00 MB',
                'up',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 17' => [
            self::summary(['peakMemory' => 1]),
            self::summary(['peakMemory' => 0]),
            4,
            [
                'Peak memory',
                '0.00 MB',
                '0.00 MB',
                '0.00 MB (-100.0%)',
                'down',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 37' => [
            self::summary(['peakMemory' => 5242]),
            self::summary(['peakMemory' => 5243]),
            4,
            [
                'Peak memory',
                '0.00 MB',
                '0.01 MB',
                '+0.00 MB (+0.0%)',
                'up',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 44' => [
            self::summary(['peakMemory' => 5243]),
            self::summary(['peakMemory' => 5242]),
            4,
            [
                'Peak memory',
                '0.01 MB',
                '0.00 MB',
                '0.00 MB (0.0%)',
                'down',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 54' => [
            self::summary(['peakMemory' => 1048576]),
            self::summary(['peakMemory' => 1048576]),
            4,
            [
                'Peak memory',
                '1.00 MB',
                '1.00 MB',
                'No change',
                'neutral',
                'profiling',
            ],
        ];
        yield 'peakMemory pair 11' => [
            self::summary(['peakMemory' => 0]),
            self::summary(['peakMemory' => -1]),
            4,
            [
                'Peak memory',
                '0.00 MB',
                '0.00 MB',
                '0.00 MB',
                'down',
                'profiling',
            ],
        ];
        yield 'sqlCount pair 0' => [
            self::summary(['sqlCount' => 0]),
            self::summary(['sqlCount' => 0]),
            5,
            [
                'SQL queries',
                '0',
                '0',
                'No change',
                'neutral',
                'db',
            ],
        ];
        yield 'sqlCount pair 4' => [
            self::summary(['sqlCount' => 0]),
            self::summary(['sqlCount' => 1000]),
            5,
            [
                'SQL queries',
                '0',
                '1,000',
                '+1,000',
                'up',
                'db',
            ],
        ];
        yield 'sqlCount pair 24' => [
            self::summary(['sqlCount' => 1000]),
            self::summary(['sqlCount' => 0]),
            5,
            [
                'SQL queries',
                '1,000',
                '0',
                '-1,000 (-100.0%)',
                'down',
                'db',
            ],
        ];
        yield 'sqlCount pair 11' => [
            self::summary(['sqlCount' => 1]),
            self::summary(['sqlCount' => 1001]),
            5,
            [
                'SQL queries',
                '1',
                '1,001',
                '+1,000 (+100,000.0%)',
                'up',
                'db',
            ],
        ];
        yield 'sqlCount pair 31' => [
            self::summary(['sqlCount' => 1001]),
            self::summary(['sqlCount' => 1]),
            5,
            [
                'SQL queries',
                '1,001',
                '1',
                '-1,000 (-99.9%)',
                'down',
                'db',
            ],
        ];
        yield 'sqlCount pair 21' => [
            self::summary(['sqlCount' => 2]),
            self::summary(['sqlCount' => 2]),
            5,
            [
                'SQL queries',
                '2',
                '2',
                'No change',
                'neutral',
                'db',
            ],
        ];
        yield 'sqlCount pair 13' => [
            self::summary(['sqlCount' => -1]),
            self::summary(['sqlCount' => 1]),
            5,
            [
                'SQL queries',
                '-1',
                '1',
                '+2 (+-200.0%)',
                'up',
                'db',
            ],
        ];
        yield 'mailCount pair 2' => [
            self::summary(['mailCount' => 0]),
            self::summary(['mailCount' => 9]),
            6,
            [
                'Mail messages',
                '0',
                '9',
                '+9',
                'up',
                'mail',
            ],
        ];
        yield 'mailCount pair 7' => [
            self::summary(['mailCount' => 9]),
            self::summary(['mailCount' => 1]),
            6,
            [
                'Mail messages',
                '9',
                '1',
                '-8 (-88.9%)',
                'down',
                'mail',
            ],
        ];
        yield 'excessiveCallersCount pair 2' => [
            self::summary(['excessiveCallersCount' => 0]),
            self::summary(['excessiveCallersCount' => 8]),
            7,
            [
                'Excessive DB callers',
                '0',
                '8',
                '+8',
                'up',
                'db',
            ],
        ];
        yield 'excessiveCallersCount pair 7' => [
            self::summary(['excessiveCallersCount' => 8]),
            self::summary(['excessiveCallersCount' => 1]),
            7,
            [
                'Excessive DB callers',
                '8',
                '1',
                '-7 (-87.5%)',
                'down',
                'db',
            ],
        ];
        yield 'statusCode pair 1' => [
            self::summary(['statusCode' => 0]),
            self::summary(['statusCode' => 200]),
            0,
            [
                'Status',
                'Not captured',
                '200',
                'Changed',
                'neutral',
                null,
            ],
        ];
        yield 'statusCode pair 6' => [
            self::summary(['statusCode' => 500]),
            self::summary(['statusCode' => 0]),
            0,
            [
                'Status',
                '500',
                'Not captured',
                'Changed',
                'neutral',
                null,
            ],
        ];
        yield 'method pair 1' => [
            self::summary(['method' => '']),
            self::summary(['method' => '0']),
            1,
            [
                'Method',
                '',
                '0',
                'Changed',
                'neutral',
                null,
            ],
        ];
        yield 'method pair 4' => [
            self::summary(['method' => '0']),
            self::summary(['method' => '']),
            1,
            [
                'Method',
                '0',
                '',
                'Changed',
                'neutral',
                null,
            ],
        ];
        yield 'method pair 10' => [
            self::summary(['method' => 'GET']),
            self::summary(['method' => 'GET']),
            1,
            [
                'Method',
                'GET',
                'GET',
                'No change',
                'neutral',
                null,
            ],
        ];
        yield 'ajax pair 2' => [
            self::summary(['ajax' => true]),
            self::summary(['ajax' => false]),
            2,
            [
                'AJAX',
                'Yes',
                'No',
                'Changed',
                'neutral',
                null,
            ],
        ];
        yield 'ajax pair 0' => [
            self::summary(['ajax' => false]),
            self::summary(['ajax' => false]),
            2,
            [
                'AJAX',
                'No',
                'No',
                'No change',
                'neutral',
                null,
            ],
        ];
    }

    /**
     * @return iterable<string, array{RequestSummary, RequestSummary, list<array{string, string, string, string, string, string|null}>}>
     */
    public static function summaries(): iterable
    {
        yield 'equal absent metrics' => [
            self::summary([]),
            self::summary([]),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '',
                    '',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    'profiling',
                ],
                [
                    'Peak memory',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
        yield 'all metrics increase' => [
            self::summary(
                [
                    'statusCode' => 200,
                    'method' => 'GET',
                    'ajax' => false,
                    'processingTime' => 0.01,
                    'peakMemory' => 1048576,
                    'sqlCount' => 2,
                    'mailCount' => 4,
                    'excessiveCallersCount' => 6,
                ],
            ),
            self::summary(
                [
                    'statusCode' => 500,
                    'method' => 'POST',
                    'ajax' => true,
                    'processingTime' => 0.015,
                    'peakMemory' => 2097152,
                    'sqlCount' => 5,
                    'mailCount' => 7,
                    'excessiveCallersCount' => 10,
                ]
            ),
            [
                [
                    'Status',
                    '200',
                    '500',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    'GET',
                    'POST',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'Yes',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '10.00 ms',
                    '15.00 ms',
                    '+5.00 ms (+50.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '1.00 MB',
                    '2.00 MB',
                    '+1.00 MB (+100.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '2',
                    '5',
                    '+3 (+150.0%)',
                    'up',
                    'db',
                ],
                [
                    'Mail messages',
                    '4',
                    '7',
                    '+3 (+75.0%)',
                    'up',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '6',
                    '10',
                    '+4 (+66.7%)',
                    'up',
                    'db',
                ],
            ],
        ];
        yield 'zero becomes captured' => [
            self::summary([]),
            self::summary(
                [
                    'processingTime' => 0.0,
                    'peakMemory' => 0,
                    'method' => '0',
                ],
            ),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '',
                    '0',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    'Not captured',
                    '0.00 ms',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'Peak memory',
                    'Not captured',
                    '0.00 MB',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
        yield 'rounding boundary' => [
            self::summary(
                [
                    'processingTime' => 0.001004999999,
                    'peakMemory' => 5242,
                ],
            ),
            self::summary(
                [
                    'processingTime' => 0.001005,
                    'peakMemory' => 5243,
                ],
            ),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '',
                    '',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '1.00 ms',
                    '1.01 ms',
                    '+0.00 ms (+0.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '0.00 MB',
                    '0.01 MB',
                    '+0.00 MB (+0.0%)',
                    'up',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
        yield 'all metrics decrease' => [
            self::summary(
                [
                    'statusCode' => 500,
                    'method' => 'POST',
                    'ajax' => true,
                    'processingTime' => 0.015,
                    'peakMemory' => 2097152,
                    'sqlCount' => 5,
                    'mailCount' => 7,
                    'excessiveCallersCount' => 10,
                ],
            ),
            self::summary(
                [
                    'statusCode' => 200,
                    'method' => 'GET',
                    'ajax' => false,
                    'processingTime' => 0.01,
                    'peakMemory' => 1048576,
                    'sqlCount' => 2,
                    'mailCount' => 4,
                    'excessiveCallersCount' => 6,
                ]
            ),
            [
                [
                    'Status',
                    '500',
                    '200',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    'POST',
                    'GET',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'Yes',
                    'No',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '15.00 ms',
                    '10.00 ms',
                    '-5.00 ms (-33.3%)',
                    'down',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '2.00 MB',
                    '1.00 MB',
                    '-1.00 MB (-50.0%)',
                    'down',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '5',
                    '2',
                    '-3 (-60.0%)',
                    'down',
                    'db',
                ],
                [
                    'Mail messages',
                    '7',
                    '4',
                    '-3 (-42.9%)',
                    'down',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '10',
                    '6',
                    '-4 (-40.0%)',
                    'down',
                    'db',
                ],
            ],
        ];
        yield 'captured zero becomes absent' => [
            self::summary(
                [
                    'processingTime' => 0.0,
                    'peakMemory' => 0,
                    'method' => '0',
                ],
            ),
            self::summary([]),
            [
                [
                    'Status',
                    'Not captured',
                    'Not captured',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Method',
                    '0',
                    '',
                    'Changed',
                    'neutral',
                    null,
                ],
                [
                    'AJAX',
                    'No',
                    'No',
                    'No change',
                    'neutral',
                    null,
                ],
                [
                    'Duration',
                    '0.00 ms',
                    'Not captured',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'Peak memory',
                    '0.00 MB',
                    'Not captured',
                    'Not comparable',
                    'neutral',
                    'profiling',
                ],
                [
                    'SQL queries',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
                [
                    'Mail messages',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'mail',
                ],
                [
                    'Excessive DB callers',
                    '0',
                    '0',
                    'No change',
                    'neutral',
                    'db',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function summary(array $values): RequestSummary
    {
        return RequestSummary::fromArray(array_replace(RequestSummary::create('sample')->jsonSerialize(), $values));
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use PHPForge\Debug\Helper\LogLevel;
use PHPForge\Debug\Panel\Dump\DumpSnapshot;
use PHPForge\Debug\Panel\Inertia\InertiaSnapshot;
use PHPForge\Debug\Panel\Log\LogSnapshot;
use PHPForge\Debug\Panel\Mail\MailSnapshot;
use PHPForge\Debug\Panel\Queue\QueueSnapshot;
use PHPForge\Debug\Panel\Request\RequestSnapshot;
use PHPForge\Debug\Panel\Router\{CurrentRouteLogRow, RouterSnapshot};
use PHPForge\Debug\Storage\HydrationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for panel snapshots that narrow live collector payloads before persistence.
 */
#[Group('panel')]
final class CapturedSnapshotTest extends TestCase
{
    public function testDumpSnapshotCapturesAndHydratesRows(): void
    {
        $captured = DumpSnapshot::capture(
            [
                ['dump', LogLevel::INFO, 'application', 1_700_000_000.5, [['file' => '/app/index.php']]],
            ],
        );
        $payload = $captured->jsonSerialize();

        $snapshot = DumpSnapshot::fromArray($payload, '$.panels.dump');

        $capturedRow = $captured->entries()[0] ?? self::fail('Expected one captured dump row.');
        $row = $snapshot->entries()[0] ?? self::fail('Expected one hydrated dump row.');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Dump payload must round-trip exactly.',
        );
        self::assertSame(
            $capturedRow->jsonSerialize(),
            $row->jsonSerialize(),
            'Typed dump rows must remain accessible.',
        );
    }

    public function testInertiaSnapshotCapturesAndHydratesResponseData(): void
    {
        $captured = InertiaSnapshot::capture(
            '/dashboard',
            ['component' => 'Dashboard'],
            ['X-Inertia' => 'true'],
            ['authenticated' => true],
            303,
        );

        $payload = $captured->jsonSerialize();

        $snapshot = InertiaSnapshot::fromArray($payload, '$.panels.inertia');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Inertia payload must round-trip exactly.',
        );
        self::assertSame(
            [
                'location' => '/dashboard',
                'page' => ['component' => 'Dashboard'],
                'requestHeaders' => ['X-Inertia' => 'true'],
                'sharedKeys' => ['authenticated' => true],
                'statusCode' => 303,
            ],
            $snapshot->data(),
            'Inertia response data must be restored for display.',
        );
    }

    public function testLogSnapshotCapturesLinksAndHydratesRows(): void
    {
        $captured = LogSnapshot::capture(
            [
                ['first', LogLevel::INFO, 'application', 100.0, [], 1_024],
                ['second', LogLevel::WARNING, 'application', 100.25, [], 2_048],
                ['third', LogLevel::ERROR, 'application', 101.0, [], 4_096],
            ],
        );
        $payload = $captured->jsonSerialize();

        self::assertSame(
            [
                'entries' => [
                    [
                        'id' => 1,
                        'message' => 'first',
                        'level' => LogLevel::INFO,
                        'category' => 'application',
                        'time' => 100_000.0,
                        'timeOfPrevious' => 100_000.0,
                        'timeSincePrevious' => 0.0,
                        'idOfPrevious' => null,
                        'idOfNext' => 2,
                        'memory' => 1_024,
                        'trace' => [],
                    ],
                    [
                        'id' => 2,
                        'message' => 'second',
                        'level' => LogLevel::WARNING,
                        'category' => 'application',
                        'time' => 100_250.0,
                        'timeOfPrevious' => 100_000.0,
                        'timeSincePrevious' => 0.25,
                        'idOfPrevious' => 1,
                        'idOfNext' => 3,
                        'memory' => 2_048,
                        'trace' => [],
                    ],
                    [
                        'id' => 3,
                        'message' => 'third',
                        'level' => LogLevel::ERROR,
                        'category' => 'application',
                        'time' => 101_000.0,
                        'timeOfPrevious' => 100_250.0,
                        'timeSincePrevious' => 0.75,
                        'idOfPrevious' => 2,
                        'idOfNext' => null,
                        'memory' => 4_096,
                        'trace' => [],
                    ],
                ],
            ],
            $payload,
            'Log capture must preserve tuple indexes, time deltas, and terminal navigation exactly.',
        );

        $snapshot = LogSnapshot::fromArray($payload, '$.panels.log');

        $first = $snapshot->entries()[0] ?? self::fail('Expected the first hydrated log row.');
        $second = $snapshot->entries()[1] ?? self::fail('Expected the second hydrated log row.');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Log payload must round-trip exactly.',
        );
        self::assertSame(
            2,
            $first->idOfNext,
            'First row must link to the second row.',
        );
        self::assertSame(
            1,
            $second->idOfPrevious,
            'Second row must link to the first row.',
        );
    }

    public function testMailSnapshotCapturesAndHydratesMessages(): void
    {
        $captured = MailSnapshot::capture(
            [
                [
                    'from' => 'sender@example.test',
                    'to' => 'one@example.test, two@example.test',
                    'subject' => 'Subject',
                    'isSuccessful' => true,
                    'time' => 1_700_000_000,
                ],
                'invalid',
            ],
        );

        $payload = $captured->jsonSerialize();

        $snapshot = MailSnapshot::fromArray($payload, '$.panels.mail');

        $capturedMessage = $captured->entries()[0] ?? self::fail('Expected one captured mail message.');
        $message = $snapshot->entries()[0] ?? self::fail('Expected one hydrated mail message.');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Mail payload must round-trip exactly.',
        );
        self::assertSame(
            $capturedMessage->jsonSerialize(),
            $message->jsonSerialize(),
            'Typed mail messages must remain accessible.',
        );
    }

    public function testQueueSnapshotHydratesCapturedRecords(): void
    {
        $captured = QueueSnapshot::capture(
            [
                [
                    'eventType' => 'exec',
                    'componentId' => 'queue',
                    'driverName' => 'Redis',
                    'driverClass' => 'yii\\queue\\redis\\Queue',
                    'isAsync' => true,
                    'jobClass' => 'app\\jobs\\SendMail',
                    'payloadFields' => ['messageId' => 42],
                    'time' => 100.5,
                    'jobId' => 'job-1',
                    'attempt' => 1,
                    'duration' => 0.25,
                ],
                'invalid',
            ],
        );

        $payload = $captured->jsonSerialize();

        $snapshot = QueueSnapshot::fromArray($payload, '$.panels.queue');

        $capturedRecord = $captured->entries()[0] ?? self::fail('Expected one captured queue record.');
        $record = $snapshot->entries()[0] ?? self::fail('Expected one hydrated queue record.');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Queue payload must round-trip exactly.',
        );
        self::assertSame(
            $capturedRecord->jsonSerialize(),
            $record->jsonSerialize(),
            'Typed queue records must remain accessible.',
        );
    }

    public function testRequestSnapshotCapturesAndHydratesRequestData(): void
    {
        $captured = RequestSnapshot::capture(['statusCode' => 201, 'method' => 'POST']);

        $payload = $captured->jsonSerialize();

        $snapshot = RequestSnapshot::fromArray($payload, '$.panels.request');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Request payload must round-trip exactly.',
        );
        self::assertSame(
            ['statusCode' => 201, 'method' => 'POST'],
            $snapshot->data(),
            'Request data must be restored for display.',
        );
    }

    public function testRequestSnapshotRejectsMismatchedHydratedStatusCode(): void
    {
        $payload = RequestSnapshot::capture(['statusCode' => 200])->jsonSerialize();

        $payload['statusCode'] = 500;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.request.statusCode'",
        );

        RequestSnapshot::fromArray($payload, '$.panels.request');
    }

    public function testRequestSnapshotRejectsMissingStatusCode(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            "Invalid debug snapshot value at '$.panels.request.statusCode'",
        );

        RequestSnapshot::capture(['method' => 'GET']);
    }

    public function testRouterSnapshotCapturesAndHydratesRouteTrace(): void
    {
        $captured = RouterSnapshot::capture(
            'site/index',
            [
                [
                    'Route resolved.',
                    LogLevel::TRACE,
                ],
                [
                    ['invalid' => true],
                    LogLevel::INFO,
                ],
                [
                    [
                        'rule' => 'yii\\rest\\UrlRule',
                        'parent' => '',
                        'match' => false,
                    ],
                    LogLevel::INFO,
                ],
                [
                    [
                        'rule' => 'app\\rules\\ViewRule',
                        'parent' => 'yii\\rest\\UrlRule',
                        'match' => true,
                    ],
                    LogLevel::INFO,
                ],
                [
                    [
                        'rule' => 'yii\\rest\\UrlRule',
                        'match' => false,
                    ],
                    LogLevel::INFO,
                ],
                [
                    [
                        'rule' => 'app\\rules\\AfterRule',
                        'parent' => '',
                        'match' => false,
                    ],
                    LogLevel::INFO,
                ],
            ],
            'post/42',
        );

        $payload = $captured->jsonSerialize();

        $snapshot = RouterSnapshot::fromArray($payload, '$.panels.router');

        self::assertSame(
            $payload,
            $snapshot->jsonSerialize(),
            'Router payload must round-trip exactly.',
        );
        self::assertTrue(
            $snapshot->hasMatch(),
            'A successful routing rule must be detected.',
        );
        self::assertSame(
            'Route resolved.',
            $snapshot->message,
            'Trace message must be retained.',
        );
        self::assertSame(
            3,
            count($snapshot->entries()),
            'Invalid rows and nested REST duplicates must be skipped without dropping later rules.',
        );
    }

    public function testRouterSnapshotKeepsTraceLevelRuleTuplesAsRows(): void
    {
        $snapshot = RouterSnapshot::capture(
            'site/index',
            [
                [
                    [
                        'rule' => 'app\\rules\\HomeRule',
                        'parent' => '',
                        'match' => true,
                    ],
                    LogLevel::TRACE,
                ],
            ],
            'site/index',
        );

        self::assertNull(
            $snapshot->message,
            'A non-string trace payload must not become the message.',
        );
        self::assertSame(
            [
                [
                    'rule' => 'app\\rules\\HomeRule',
                    'parent' => '',
                    'match' => true,
                ],
            ],
            array_map(
                static fn(CurrentRouteLogRow $row): array => $row->jsonSerialize(),
                $snapshot->entries(),
            ),
            'A rule tuple logged at trace level must still produce a row.',
        );
    }

    public function testRouterSnapshotReportsNoMatchWithoutSuccessfulRows(): void
    {
        $snapshot = RouterSnapshot::capture(null, [], 'missing');

        self::assertFalse(
            $snapshot->hasMatch(),
            'An empty routing trace must not report a match.',
        );
        self::assertSame(
            [],
            $snapshot->entries(),
            'An empty routing trace must not create rows.',
        );
    }
}

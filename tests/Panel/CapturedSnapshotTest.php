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
use PHPForge\Debug\Panel\Router\RouterSnapshot;
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
                'invalid',
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
                'invalid',
                ['second', LogLevel::WARNING, 'application', 100.25, [], 2_048],
            ],
        );
        $payload = $captured->jsonSerialize();

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
                ['Route resolved.', LogLevel::TRACE],
                [['rule' => 'yii\\rest\\UrlRule', 'parent' => '', 'match' => false], LogLevel::INFO],
                [['rule' => 'app\\rules\\ViewRule', 'parent' => 'yii\\rest\\UrlRule', 'match' => true], LogLevel::INFO],
                [['rule' => 'yii\\rest\\UrlRule', 'match' => false], LogLevel::INFO],
                [['invalid' => true], LogLevel::INFO],
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
            2,
            count($snapshot->entries()),
            'Nested REST duplicate must be omitted.',
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

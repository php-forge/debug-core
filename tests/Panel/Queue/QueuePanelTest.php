<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Queue;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\Queue\{JobRecord, QueuePanel, QueueSnapshot};
use PHPForge\Debug\Tests\Support\{JobRecordFixture, PanelViewAccessors};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function date;

/**
 * Unit tests for {@see QueuePanel} covering lifecycle counters, the event table, and the per-event detail groups.
 *
 * @phpstan-import-type Pair from PanelView
 */
#[Group('panel')]
#[Group('queue')]
final class QueuePanelTest extends TestCase
{
    use PanelViewAccessors;

    /**
     * @var float Capture timestamp shared by the populated events.
     */
    private const float TIME = 1_757_700_000.5;

    public function testAsyncDriversWarnThatWorkerEventsLiveInAnotherCapture(): void
    {
        $view = self::present(
            [
                JobRecordFixture::create(driverName: 'Database', isAsync: true, time: self::TIME),
                JobRecordFixture::create(driverName: 'Database', isAsync: true, time: self::TIME),
                JobRecordFixture::create(driverName: 'Redis', isAsync: true, time: self::TIME),
                JobRecordFixture::create(driverName: '', isAsync: true, time: self::TIME),
            ],
        );

        $callout = self::paragraph(self::blockAt($view, 0));

        self::assertSame(
            Tone::INFO,
            $callout['tone'],
            'The async hint must read as a neutral callout.',
        );
        self::assertSame(
            [
                'Async driver: Database, Redis.',
                ' Push events show here, but jobs run in a separate worker process; see the History sidebar for ',
                'CLI',
                ' debug snapshots that capture the matching exec and error events.',
            ],
            self::inlineValues($callout),
            'Each async driver must be named once, in first-seen order.',
        );
    }

    public function testEmptyCaptureDeactivatesThePanelAndExplainsTheMissingEvents(): void
    {
        $view = self::present([]);

        self::assertFalse(
            $view->isActive(),
            'An empty lifecycle log must not activate navigation.',
        );
        self::assertSame(
            [' events', ' queued', ' done'],
            self::metricLabels($view->summaryMetrics()),
            'A capture without failures must not add the failed counter.',
        );

        $state = self::emptyState(self::blockAt($view, 0));

        self::assertCount(
            1,
            $view->blocks(),
            'An empty lifecycle log must replace the table and the groups.',
        );
        self::assertSame(
            'No queue activity in this request',
            $state['title'],
            'The empty state must keep its heading.',
        );
        self::assertSame(
            ['This request pushed no job and ran none, so the lifecycle log is empty.'],
            self::inlineValues($state['paragraphs'][0] ?? self::fail('The empty state must explain itself.')),
            'The first paragraph must describe the empty log.',
        );
        self::assertSame(
            ['Events appear here when a queue component emits ', 'afterPush', ', ', 'afterExec', ', or ', 'afterError', '.'],
            self::inlineValues($state['paragraphs'][1] ?? self::fail('The empty state must name the hooks.')),
            'The capture hooks must stay complete and ordered.',
        );
    }

    public function testEventWithoutPayloadStatesItInstead(): void
    {
        $view = self::present([JobRecordFixture::create(time: self::TIME)]);
        $content = self::group(self::blockAt($view, 3));

        self::assertCount(
            2,
            $content['content']->blocks(),
            'An event without payload must keep only its overview and the explanation.',
        );
        self::assertSame(
            ['The event carried no job payload.'],
            self::inlineValues(self::paragraph(self::childBlockAt($content, 1))),
            'An event without payload must state it instead of rendering an empty overview.',
        );
    }

    public function testFailedEventIsCountedBadgedAndCarriesItsError(): void
    {
        $view = self::present(
            [
                JobRecordFixture::create(
                    eventType: JobRecord::TYPE_ERROR,
                    attempt: 2,
                    duration: 0.125,
                    error: 'Division by zero',
                    time: self::TIME,
                ),
            ],
        );

        self::assertSame(
            [' event', ' queued', ' done', ' failed'],
            self::metricLabels($view->summaryMetrics()),
            'A failure must add the failed counter after the others.',
        );
        self::assertSame(
            ['1', '0', '0', '1'],
            self::metricValues($view->summaryMetrics()),
            'Each lifecycle phase must be counted independently.',
        );

        $table = self::table(self::blockAt($view, 1));

        self::assertSame(
            'Failed',
            self::badge(self::row($table, 0)[1] ?? self::fail('Every event must report its phase.'))['label'],
            'A failed event must be labeled as failed.',
        );
        self::assertSame(
            Tone::DANGER,
            self::badge(self::row($table, 0)[1] ?? self::fail('Every event must report its phase.'))['tone'],
            'A failed event must use the danger tone.',
        );
        self::assertSame(
            ['1', 'app\\jobs\\HelloJob', 'queue', 'Sync', date('H:i:s', (int) self::TIME), '2', '125.0 ms'],
            self::textValues(
                [
                    self::row($table, 0)[0] ?? self::fail('Every event must be listed.'),
                    self::row($table, 0)[2] ?? self::fail('Every event must be listed.'),
                    self::row($table, 0)[3] ?? self::fail('Every event must be listed.'),
                    self::row($table, 0)[4] ?? self::fail('Every event must be listed.'),
                    self::row($table, 0)[5] ?? self::fail('Every event must be listed.'),
                    self::row($table, 0)[6] ?? self::fail('Every event must be listed.'),
                    self::row($table, 0)[7] ?? self::fail('Every event must be listed.'),
                ],
            ),
            'Every lifecycle column must survive the migration.',
        );

        $content = self::group(self::blockAt($view, 3));

        self::assertSame(
            '2',
            self::textValue(
                self::fields(self::overview(self::childBlockAt($content, 0)))['Attempt']
                    ?? self::fail('The envelope must keep the attempt row.'),
            ),
            'A retried event must report its attempt number.',
        );
        self::assertSame(
            ['Division by zero'],
            self::inlineValues(self::paragraph(self::childBlockAt($content, 1))),
            'The captured error must stay visible.',
        );
        self::assertSame(
            Tone::DANGER,
            self::paragraph(self::childBlockAt($content, 1))['tone'],
            'The captured error must read as a failure callout.',
        );
    }

    public function testJobDetailUrlsComeFromTheAdapter(): void
    {
        $panel = new QueuePanel();

        self::assertNotSame(
            $panel,
            $panel->jobUrls([]),
            'New instance must be returned (immutability).',
        );

        $view = $panel
            ->jobUrls([1 => '/debug/queue-job?seq=1'])
            ->present(
                (
                        new QueueSnapshot(
                            [
                                JobRecordFixture::create(time: self::TIME),
                                JobRecordFixture::create(time: self::TIME),
                            ],
                        )
                )->jsonSerialize(),
            );

        self::assertCount(
            2,
            self::table(self::blockAt($view, 1))['rows'],
            'The lifecycle table must list every captured event.',
        );
        self::assertArrayNotHasKey(
            'Details',
            self::fields(self::overview(self::childBlockAt(self::group(self::blockAt($view, 3)), 0))),
            'An event without detail route must not offer a link.',
        );
        self::assertSame(
            ['kind' => 'link', 'label' => 'Open job detail', 'href' => '/debug/queue-job?seq=1', 'external' => false],
            self::fields(self::overview(self::childBlockAt(self::group(self::blockAt($view, 5)), 0)))['Details']
                ?? self::fail('The detail route must reach the envelope.'),
            'The adapter-resolved detail route must stay in the same browsing context.',
        );
    }

    public function testMetadataMatchesTheBuiltInQueuePanel(): void
    {
        $panel = new QueuePanel();

        self::assertSame(
            'queue',
            $panel->id(),
            'The persisted panel identifier must stay stable.',
        );
        self::assertSame(
            'Queue',
            $panel->name(),
            'The navigation title must stay stable.',
        );
        self::assertSame(
            'queue',
            $panel->icon(),
            'The panel must reuse the existing icon.',
        );
    }

    public function testPushedEventDescribesItsEnvelopeAndPayload(): void
    {
        $view = self::present(
            [
                JobRecordFixture::create(
                    eventType: JobRecord::TYPE_PUSH,
                    componentId: 'queueEmail',
                    driverName: 'Database',
                    driverClass: 'yii\\queue\\db\\Queue',
                    isAsync: true,
                    payloadFields: ['to' => 'admin@example.com'],
                    time: self::TIME,
                    jobId: '42',
                    ttr: 300,
                    delay: 5,
                    priority: 1024,
                ),
            ],
        );

        self::assertTrue(
            $view->isActive(),
            'A captured event must activate navigation.',
        );
        self::assertSame(
            [['label' => 'Jobs', 'value' => ['kind' => 'text', 'value' => '1', 'style' => 'plain']]],
            $view->toolbarMetrics(),
            'The toolbar must report the event count.',
        );

        $heading = self::heading(self::blockAt($view, 1));

        self::assertSame(
            'Lifecycle events',
            $heading['title'],
            'The lifecycle table must keep its heading.',
        );
        self::assertTrue(
            $heading['section'],
            'The lifecycle table must open a section-level heading.',
        );

        $table = self::table(self::blockAt($view, 2));

        self::assertSame(
            ['#', 'Status', 'Job', 'Component', 'Driver', 'Time', 'Attempt', 'Duration'],
            $table['headers'],
            'The lifecycle column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::PILL,
                2 => ColumnStyle::IDENTIFIER,
                3 => ColumnStyle::IDENTIFIER,
                4 => ColumnStyle::IDENTIFIER,
                5 => ColumnStyle::IDENTIFIER,
                6 => ColumnStyle::NUMBER,
                7 => ColumnStyle::NUMBER,
            ],
            $table['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $table['collapsible'],
            'A long lifecycle log must stay collapsible.',
        );
        self::assertSame(
            'Queued',
            self::badge(self::row($table, 0)[1] ?? self::fail('Every event must report its phase.'))['label'],
            'A pushed event must be labeled as queued.',
        );
        self::assertSame(
            Tone::INFO,
            self::badge(self::row($table, 0)[1] ?? self::fail('Every event must report its phase.'))['tone'],
            'A pushed event must stay neutral.',
        );

        $eventHeading = self::heading(self::blockAt($view, 3));

        self::assertSame(
            '1. app\\jobs\\HelloJob',
            $eventHeading['title'],
            'Headings must number the events and name the job.',
        );
        self::assertTrue(
            $eventHeading['section'],
            'Each event must open a section-level heading.',
        );

        $content = self::group(self::blockAt($view, 4));

        self::assertSame(
            'Event 1',
            $content['label'],
            'The group must identify the event it describes.',
        );

        $overview = self::overview(self::childBlockAt($content, 0));

        self::assertTrue(
            $overview['compact'],
            'The envelope must use the compact presentation.',
        );
        self::assertSame(
            [
                'Job',
                'Status',
                'Component',
                'Driver',
                'Driver class',
                'Execution',
                'Job id',
                'Pushed at',
                'TTR',
                'Delay',
                'Priority',
                'Attempt',
                'Duration',
            ],
            array_keys(self::fields($overview)),
            'The envelope row order must stay stable.',
        );

        $fields = self::fields($overview);

        self::assertSame(
            ['kind' => 'text', 'value' => 'yii\\queue\\db\\Queue', 'style' => 'code'],
            $fields['Driver class'] ?? self::fail('The envelope must keep the driver class row.'),
            'A captured driver class must read as source code.',
        );
        self::assertSame(
            'Worker process',
            self::textValue($fields['Execution'] ?? self::fail('The envelope must keep the execution row.')),
            'An async driver must say the job runs in a worker.',
        );
        self::assertSame(
            '300s',
            self::textValue($fields['TTR'] ?? self::fail('The envelope must keep the time-to-run row.')),
            'A declared time-to-run must survive the migration.',
        );
        self::assertSame(
            '5s',
            self::textValue($fields['Delay'] ?? self::fail('The envelope must keep the delay row.')),
            'A declared delay must survive the migration.',
        );
        self::assertSame(
            '1024',
            self::textValue($fields['Priority'] ?? self::fail('The envelope must keep the priority row.')),
            'A declared priority must survive the migration.',
        );
        self::assertSame(
            '—',
            self::textValue($fields['Attempt'] ?? self::fail('The envelope must keep the attempt row.')),
            'A pushed event must report no attempt.',
        );
        self::assertSame(
            date('M j, Y · H:i:s', (int) self::TIME),
            self::textValue($fields['Pushed at'] ?? self::fail('The envelope must keep the time row.')),
            'The envelope must show the absolute timestamp.',
        );
        self::assertSame(
            ['kind' => 'value', 'value' => ['to' => 'admin@example.com'], 'typeOnly' => false],
            self::fields(self::overview(self::childBlockAt($content, 1)))['Payload']
                ?? self::fail('The captured payload must stay inspectable.'),
            'The captured payload must survive the migration unflattened.',
        );
    }

    public function testSynchronousDriverWithoutOverridesFallsBackToPlaceholders(): void
    {
        $view = self::present([JobRecordFixture::create(componentId: '', driverName: '', driverClass: '', jobClass: '', time: self::TIME)]);

        self::assertSame(
            'Lifecycle events',
            self::heading(self::blockAt($view, 0))['title'],
            'A synchronous driver must not add the async hint.',
        );

        $fields = self::fields(self::overview(self::childBlockAt(self::group(self::blockAt($view, 3)), 0)));

        self::assertSame(
            ['—', 'In process', '—', '—', '—', '—'],
            [
                self::textValue($fields['Component'] ?? self::fail('The envelope must keep the component row.')),
                self::textValue($fields['Execution'] ?? self::fail('The envelope must keep the execution row.')),
                self::textValue($fields['Job id'] ?? self::fail('The envelope must keep the job id row.')),
                self::textValue($fields['TTR'] ?? self::fail('The envelope must keep the time-to-run row.')),
                self::textValue($fields['Delay'] ?? self::fail('The envelope must keep the delay row.')),
                self::textValue($fields['Priority'] ?? self::fail('The envelope must keep the priority row.')),
            ],
            'Missing envelope fields must show the placeholder.',
        );
    }

    /**
     * @param list<Pair> $metrics Metrics in display order.
     *
     * @return list<string> Metric labels in display order.
     */
    private static function metricLabels(array $metrics): array
    {
        $labels = [];

        foreach ($metrics as $metric) {
            $labels[] = $metric['label'];
        }

        return $labels;
    }

    /**
     * @param list<JobRecord> $records Captured events in chronological order.
     *
     * @return PanelView Description built by the panel.
     */
    private static function present(array $records): PanelView
    {
        return (new QueuePanel())->present((new QueueSnapshot($records))->jsonSerialize());
    }
}

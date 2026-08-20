<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Collector;

use InvalidArgumentException;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\{PanelFailure, RequestSummary};
use PHPForge\Debug\Tests\Support\{ArrayPayloadSnapshotFixture, CollectorFixture};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Unit tests for {@see CollectorCoordinator} validating IDs, lifecycle, and isolated capture failures.
 *
 * @since 0.1
 */
#[Group('collector')]
final class CollectorCoordinatorTest extends TestCase
{
    public function testCaptureIsolatesCollectorFailure(): void
    {
        $coordinator = new CollectorCoordinator(
            [
                new CollectorFixture(
                    'app.example',
                    ArrayPayloadSnapshotFixture::capture(['value' => 42]),
                ),
                new CollectorFixture(
                    'broken',
                    failCapture: true,
                ),
            ],
        );

        $snapshot = $coordinator->capture($this->summary());

        self::assertArrayHasKey(
            'app.example',
            $snapshot->panels,
            'Successful payload must survive another failure.',
        );

        $failure = $snapshot->failures['broken'] ?? null;

        self::assertNotNull(
            $failure,
            'Broken collector must produce a failure.',
        );
        self::assertSame(
            PanelFailure::CAPTURE,
            $failure->stage,
            'Failure must identify the capture stage.',
        );
        self::assertSame(
            'Collector capture failed.',
            $failure->exception->getMessage(),
            'Failure must preserve the exception message.',
        );
    }
    public function testCapturePersistsSuccessfulCollectorsAndOmitsNullSnapshots(): void
    {
        $coordinator = new CollectorCoordinator(
            [
                new CollectorFixture(
                    'app.example',
                    ArrayPayloadSnapshotFixture::capture(['value' => 42]),
                ),
                new CollectorFixture(
                    'empty',
                ),
            ],
        );

        $snapshot = $coordinator->capture($this->summary());

        self::assertArrayHasKey(
            'app.example',
            $snapshot->panels,
            'Successful payload must use its stable ID.',
        );
        self::assertArrayNotHasKey(
            'empty',
            $snapshot->panels,
            "A 'null' snapshot must be omitted.",
        );
        self::assertSame(
            [],
            $snapshot->failures,
            'Successful capture must not create failures.',
        );
    }

    public function testCollectorReturnsRegisteredInstanceOrNull(): void
    {
        $collector = new CollectorFixture(
            'app.example',
        );
        $coordinator = new CollectorCoordinator(
            [$collector],
        );

        self::assertSame(
            $collector,
            $coordinator->collector('app.example'),
            'Registered instance must round-trip.',
        );
        self::assertNull(
            $coordinator->collector('unknown'),
            "Unknown ID must yield 'null'.",
        );
    }

    public function testHasCollectorUsesStableId(): void
    {
        $coordinator = new CollectorCoordinator(
            [
                new CollectorFixture(
                    'app.example',
                ),
            ],
        );

        self::assertTrue(
            $coordinator->hasCollector('app.example'),
            'Registered ID must be found.',
        );
        self::assertFalse(
            $coordinator->hasCollector('App.Example'),
            'ID matching must remain case-sensitive.',
        );
    }

    public function testLifecycleCallsCollectorsOncePerCycle(): void
    {
        $collector = new CollectorFixture(
            'app.example',
        );
        $coordinator = new CollectorCoordinator(
            [$collector],
        );

        $coordinator->startup();
        $coordinator->startup();
        $coordinator->shutdown();
        $coordinator->shutdown();

        self::assertSame(
            1,
            $collector->startupCount,
            'Startup must run once per cycle.',
        );
        self::assertSame(
            1,
            $collector->shutdownCount,
            'Shutdown must run once per cycle.',
        );

        $coordinator->startup();
        $coordinator->shutdown();

        self::assertSame(
            2,
            $collector->startupCount,
            'A completed shutdown must allow a new lifecycle cycle.',
        );
        self::assertSame(
            2,
            $collector->shutdownCount,
            'The second lifecycle cycle must also be cleaned up.',
        );
    }

    public function testRunIgnoresFailingCleanupDiagnosticAndPreservesPrimaryThrowable(): void
    {
        $primary = new RuntimeException(
            'Primary operation failed.',
        );
        $coordinator = new CollectorCoordinator(
            [
                new CollectorFixture(
                    'broken',
                    failShutdown: true,
                ),
            ],
        );

        $caught = null;

        try {
            $coordinator->run(
                static fn(): never => throw $primary,
                static fn(Throwable $_throwable): never => throw new RuntimeException('Diagnostic failed.'),
            );
        } catch (RuntimeException $throwable) {
            $caught = $throwable;
        }

        self::assertSame(
            $primary,
            $caught,
            'A diagnostic failure must not replace the exact primary throwable.',
        );
    }

    public function testRunPreservesPrimaryThrowableAndReportsCleanupFailure(): void
    {
        $primary = new RuntimeException(
            'Primary operation failed.',
        );

        $cleanupFailure = null;

        $coordinator = new CollectorCoordinator(
            [
                new CollectorFixture(
                    'broken',
                    failShutdown: true,
                ),
            ],
        );

        try {
            $coordinator->run(
                static fn(): never => throw $primary,
                static function (Throwable $throwable) use (&$cleanupFailure): void {
                    $cleanupFailure = $throwable;
                },
            );
        } catch (RuntimeException $throwable) {
            self::assertSame(
                $primary,
                $throwable,
                'The exact primary throwable must be rethrown.',
            );
        }

        self::assertInstanceOf(
            RuntimeException::class,
            $cleanupFailure,
            'Cleanup failure must reach the observer.',
        );
        self::assertSame(
            'Collector shutdown failed.',
            $cleanupFailure->getMessage(),
            'The observer must receive the cleanup failure.',
        );
    }

    public function testRunPreservesPrimaryThrowableWithoutACleanupFailureHandler(): void
    {
        $primary = new RuntimeException(
            'Primary operation failed.',
        );
        $coordinator = new CollectorCoordinator(
            [
                new CollectorFixture(
                    'broken',
                    failShutdown: true,
                ),
            ],
        );

        $caught = null;

        try {
            $coordinator->run(static fn(): never => throw $primary);
        } catch (RuntimeException $throwable) {
            $caught = $throwable;
        }

        self::assertSame(
            $primary,
            $caught,
            'Cleanup failure reporting must be optional when an operation already failed.',
        );
    }

    public function testRunReturnsOperationResultAndCompletesLifecycle(): void
    {
        $collector = new CollectorFixture(
            'app.example',
        );
        $coordinator = new CollectorCoordinator(
            [$collector],
        );

        self::assertSame(
            'result',
            $coordinator->run(static fn(): string => 'result'),
            'Operation result must survive.',
        );
        self::assertSame(
            1,
            $collector->startupCount,
            'Run must start the collector.',
        );
        self::assertSame(
            1,
            $collector->shutdownCount,
            'Run must stop the collector.',
        );
    }

    public function testShutdownContinuesAfterCollectorFailure(): void
    {
        $firstBroken = new CollectorFixture(
            'first-broken',
            failShutdown: true,
            shutdownFailureMessage: 'First shutdown failed.',
        );
        $secondBroken = new CollectorFixture(
            'second-broken',
            failShutdown: true,
            shutdownFailureMessage: 'Second shutdown failed.',
        );
        $successful = new CollectorFixture(
            'successful',
        );
        $coordinator = new CollectorCoordinator(
            [
                $firstBroken,
                $secondBroken,
                $successful,
            ],
        );

        $coordinator->startup();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'First shutdown failed.',
        );

        try {
            $coordinator->shutdown();
        } finally {
            self::assertSame(
                1,
                $secondBroken->shutdownCount,
                'Later failing collectors must still shut down.',
            );
            self::assertSame(
                1,
                $successful->shutdownCount,
                'Later collectors must still shut down.',
            );
        }
    }

    public function testShutdownRetriesOnlyCollectorsWhoseCleanupFailed(): void
    {
        $successful = new CollectorFixture(
            'successful',
        );
        $flaky = new CollectorFixture(
            'flaky',
            shutdownFailuresRemaining: 1,
        );
        $coordinator = new CollectorCoordinator(
            [
                $successful,
                $flaky,
            ],
        );

        $coordinator->startup();

        try {
            $coordinator->shutdown();
        } catch (RuntimeException) {
            // The next shutdown retries only the collector whose first cleanup attempt failed.
        }

        $coordinator->shutdown();
        $coordinator->shutdown();

        self::assertSame(
            1,
            $successful->shutdownCount,
            'Successful cleanup must not be repeated.',
        );
        self::assertSame(
            2,
            $flaky->shutdownCount,
            'Failed cleanup must be retried until it succeeds.',
        );
    }

    public function testStartupAllowsRetryAfterRollback(): void
    {
        $first = new CollectorFixture(
            'first',
        );
        $flaky = new CollectorFixture(
            'flaky',
            startupFailuresRemaining: 1,
        );
        $later = new CollectorFixture(
            'later',
        );
        $coordinator = new CollectorCoordinator(
            [
                $first,
                $flaky,
                $later,
            ],
        );

        $startupFailed = false;

        try {
            $coordinator->startup();
        } catch (RuntimeException) {
            $startupFailed = true;
        }

        self::assertTrue(
            $startupFailed,
            'Initial cycle must report its startup failure.',
        );

        $coordinator->startup();
        $coordinator->startup();

        self::assertSame(
            2,
            $first->startupCount,
            'First collector must start once per attempted cycle.',
        );
        self::assertSame(
            2,
            $flaky->startupCount,
            'Failed collector must be retried in the next cycle.',
        );
        self::assertSame(
            1,
            $later->startupCount,
            'Later collector must start only during the successful retry.',
        );
        self::assertSame(
            1,
            $first->shutdownCount,
            'First collector must be cleaned during rollback.',
        );
        self::assertSame(
            1,
            $flaky->shutdownCount,
            'Failed collector must be cleaned during rollback.',
        );
        self::assertSame(
            0,
            $later->shutdownCount,
            'Unprocessed collector must not require rollback cleanup.',
        );

        $coordinator->shutdown();
        $coordinator->shutdown();

        self::assertSame(
            2,
            $first->shutdownCount,
            'First collector must stop once after the successful cycle.',
        );
        self::assertSame(
            2,
            $flaky->shutdownCount,
            'Retried collector must stop once after the successful cycle.',
        );
        self::assertSame(
            1,
            $later->shutdownCount,
            'Later collector must stop once after the successful cycle.',
        );
    }

    public function testStartupRetriesIncompleteRollbackCleanupBeforeStartingANewCycle(): void
    {
        $flakyCleanup = new CollectorFixture(
            'flaky-cleanup',
            shutdownFailuresRemaining: 1,
        );
        $flakyStartup = new CollectorFixture(
            'flaky-startup',
            startupFailuresRemaining: 1,
        );
        $coordinator = new CollectorCoordinator(
            [
                $flakyCleanup,
                $flakyStartup,
            ],
        );

        try {
            $coordinator->startup();
        } catch (RuntimeException) {
            // The startup failure remains primary even though the first rollback cleanup also fails.
        }

        $coordinator->startup();
        $coordinator->shutdown();

        self::assertSame(
            2,
            $flakyCleanup->startupCount,
            'Collector must start again after its cleanup retry succeeds.',
        );
        self::assertSame(
            3,
            $flakyCleanup->shutdownCount,
            'Failed rollback, retry, and final cleanup must each run once.',
        );
        self::assertSame(
            2,
            $flakyStartup->startupCount,
            'Failed startup must be retried in the new cycle.',
        );
        self::assertSame(
            2,
            $flakyStartup->shutdownCount,
            'Partial and successful cycles must both be cleaned.',
        );
    }

    public function testStartupRollsBackAffectedCollectorsWhenSecondCollectorFails(): void
    {
        $first = new CollectorFixture(
            'first',
        );
        $failed = new CollectorFixture(
            'failed',
            startupFailuresRemaining: 1,
        );
        $later = new CollectorFixture(
            'later',
        );
        $coordinator = new CollectorCoordinator(
            [
                $first,
                $failed,
                $later,
            ],
        );

        $failure = null;

        try {
            $coordinator->startup();
        } catch (RuntimeException $throwable) {
            $failure = $throwable;
        }

        self::assertSame(
            'Collector startup failed.',
            $failure?->getMessage(),
            'Original startup failure must be returned.',
        );
        self::assertSame(
            1,
            $first->startupCount,
            'First collector must start before the failure.',
        );
        self::assertSame(
            1,
            $failed->startupCount,
            'Failing collector must record its partial startup.',
        );
        self::assertSame(
            0,
            $later->startupCount,
            'Later collector must not start after the failure.',
        );
        self::assertSame(
            1,
            $first->shutdownCount,
            'First collector must be cleaned during rollback.',
        );
        self::assertSame(
            1,
            $failed->shutdownCount,
            'Partially started collector must be cleaned during rollback.',
        );
        self::assertSame(
            0,
            $later->shutdownCount,
            'Later collector must not receive rollback cleanup.',
        );

        $coordinator->shutdown();

        self::assertSame(
            1,
            $first->shutdownCount,
            'Inactive coordinator must not repeat first collector cleanup.',
        );
        self::assertSame(
            1,
            $failed->shutdownCount,
            'Inactive coordinator must not repeat failed collector cleanup.',
        );
        self::assertSame(
            0,
            $later->shutdownCount,
            'Inactive coordinator must leave later collector untouched.',
        );
    }

    public function testThrowInvalidArgumentExceptionForDuplicateId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Duplicate debug collector ID: app.example.',
        );

        new CollectorCoordinator(
            [
                new CollectorFixture(
                    'app.example',
                ),
                new CollectorFixture(
                    'app.example',
                ),
            ],
        );
    }

    public function testThrowInvalidArgumentExceptionForEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Debug collector ID must not be empty.',
        );

        new CollectorCoordinator([new CollectorFixture('   ')]);
    }

    public function testThrowRuntimeExceptionWhenRollbackShutdownFails(): void
    {
        $first = new CollectorFixture(
            'first',
        );
        $rollbackFailure = new CollectorFixture(
            'rollback-failure',
            failShutdown: true,
            shutdownFailureMessage: 'Rollback shutdown failed.',
        );
        $startupFailure = new CollectorFixture(
            'startup-failure',
            startupFailuresRemaining: 1,
            startupFailureMessage: 'Primary startup failed.',
        );
        $later = new CollectorFixture(
            'later',
        );
        $coordinator = new CollectorCoordinator(
            [
                $first,
                $rollbackFailure,
                $startupFailure,
                $later,
            ],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Primary startup failed.',
        );

        try {
            $coordinator->startup();
        } finally {
            self::assertSame(
                1,
                $first->shutdownCount,
                'Rollback must clean collectors before a shutdown failure.',
            );
            self::assertSame(
                1,
                $rollbackFailure->shutdownCount,
                'Failing rollback cleanup must be attempted once.',
            );
            self::assertSame(
                1,
                $startupFailure->shutdownCount,
                'Rollback must continue after a shutdown failure.',
            );
            self::assertSame(
                0,
                $later->shutdownCount,
                'Unprocessed collector must remain untouched.',
            );
        }
    }

    /**
     * Creates representative request metadata.
     *
     * @return RequestSummary Representative request metadata.
     */
    private function summary(): RequestSummary
    {
        return new RequestSummary(
            tag: 'request-1',
            url: 'https://example.test/',
            ajax: false,
            method: 'GET',
            ip: '127.0.0.1',
            time: 1_700_000_000.0,
            statusCode: 200,
            sqlCount: 0,
            excessiveCallersCount: 0,
            mailCount: 0,
            mailFiles: [],
            processingTime: null,
            peakMemory: null,
        );
    }
}

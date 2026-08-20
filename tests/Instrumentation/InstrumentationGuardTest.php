<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Instrumentation;

use PHPForge\Debug\Instrumentation\InstrumentationGuard;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Unit tests for {@see InstrumentationGuard} preserving application behavior around diagnostic failures.
 */
#[Group('instrumentation')]
final class InstrumentationGuardTest extends TestCase
{
    public function testObserveExecutesSuccessfulObserver(): void
    {
        $observed = false;

        (new InstrumentationGuard())->observe(static function () use (&$observed): void {
            $observed = true;
        });

        self::assertTrue($observed, 'A successful diagnostic observer must run exactly where invoked.');
    }

    public function testObserveReportsExactFailureWithoutPropagatingIt(): void
    {
        $expected = new RuntimeException('Collector failed.');
        $reported = null;
        $guard = new InstrumentationGuard(
            static function (Throwable $failure) use (&$reported): void {
                $reported = $failure;
            },
        );

        $guard->observe(static fn(): never => throw $expected);

        self::assertSame($expected, $reported, 'The failure handler must receive the exact instrumentation throwable.');
    }

    public function testObserveSuppressesFailureHandlerException(): void
    {
        $this->expectNotToPerformAssertions();

        $guard = new InstrumentationGuard(
            static fn(Throwable $_failure): never => throw new RuntimeException('Handler failed.'),
        );

        $guard->observe(static fn(): never => throw new RuntimeException('Collector failed.'));

    }

    public function testObserveSuppressesFailureWithoutAHandler(): void
    {
        $this->expectNotToPerformAssertions();

        (new InstrumentationGuard())->observe(
            static fn(): never => throw new RuntimeException('Collector failed.'),
        );
    }
}

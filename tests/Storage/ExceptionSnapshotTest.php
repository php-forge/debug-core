<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Storage;

use LogicException;
use PHPForge\Debug\Storage\{DebugArray, ExceptionSnapshot, HydrationException};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for {@see ExceptionSnapshot} covering safe throwable capture and strict hydration.
 */
#[Group('storage')]
final class ExceptionSnapshotTest extends TestCase
{
    public function testThrowableRoundTripsThroughJson(): void
    {
        $throwable = new RuntimeException('outer failure', 42, new LogicException('inner failure', 7));

        $snapshot = ExceptionSnapshot::fromThrowable($throwable);

        $encoded = json_encode($snapshot, JSON_THROW_ON_ERROR);
        $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        $hydrated = ExceptionSnapshot::fromArray($decoded);

        self::assertSame(
            RuntimeException::class,
            $hydrated->getClass(),
            'The hydrated exception must retain its original class.',
        );
        self::assertSame(
            'outer failure',
            $hydrated->getMessage(),
            'The hydrated exception must retain its original message.',
        );
        self::assertSame(
            42,
            $hydrated->getCode(),
            'The hydrated exception must retain its original code.',
        );
        self::assertSame(
            $throwable->getFile(),
            $hydrated->getFile(),
            'The hydrated exception must retain its original file.',
        );
        self::assertSame(
            $throwable->getLine(),
            $hydrated->getLine(),
            'The hydrated exception must retain its original line.',
        );
        self::assertSame(
            (string) $throwable,
            (string) $hydrated,
            'The hydrated exception must retain its original string representation.',
        );

        $frame = $hydrated->getTrace()[0] ?? self::fail('Expected the helper call in the captured trace.');

        self::assertSame(
            ['namespace', 'short_class', 'class', 'type', 'function', 'file', 'line', 'args'],
            array_keys($frame),
            'Trace projection must retain frame metadata alongside its arguments.',
        );

        $previous = $hydrated->getPrevious();

        self::assertNotNull(
            $previous,
            'The hydrated exception must retain its previous exception.',
        );
        self::assertSame(
            LogicException::class,
            $previous->getClass(),
            'The hydrated previous exception must retain its original class.',
        );
        self::assertSame(
            'inner failure',
            $previous->getMessage(),
            'The hydrated previous exception must retain its original message.',
        );
        self::assertSame(
            7,
            $previous->getCode(),
            'The hydrated previous exception must retain its original code.',
        );
    }

    public function testThrowableWithInvalidUtf8MessageRemainsJsonSafe(): void
    {
        $snapshot = ExceptionSnapshot::fromThrowable(new RuntimeException("\xB1\x31"));

        self::assertSame(
            '(binary: base64 sTE=)',
            $snapshot->getMessage(),
            'A binary throwable message must be represented as base64.',
        );
        self::assertJson(
            json_encode($snapshot, JSON_THROW_ON_ERROR),
            'A binary throwable message must not break snapshot serialization.',
        );
    }

    public function testThrowHydrationExceptionForInvalidCodeType(): void
    {
        $payload = ExceptionSnapshot::fromThrowable(new RuntimeException('failure'))
            ->jsonSerialize();

        $payload['code'] = false;

        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage(
            '$.exception.code',
        );

        ExceptionSnapshot::fromArray($payload);
    }

    public function testTraceProjectsArgumentsToPlainValues(): void
    {
        $snapshot = new ExceptionSnapshot(
            class: RuntimeException::class,
            message: 'failure',
            code: 0,
            file: __FILE__,
            line: __LINE__,
            trace: [
                [
                    'namespace' => __NAMESPACE__,
                    'short_class' => 'ExceptionSnapshotTest',
                    'class' => self::class,
                    'type' => '->',
                    'function' => 'fixture',
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'args' => DebugArray::capture(['trace argument']),
                ],
            ],
            toString: 'failure',
            previous: null,
        );

        $frame = $snapshot->getTrace()[0] ?? self::fail('Expected the deterministic fixture frame.');

        self::assertSame(
            ['trace argument'],
            $frame['args'] ?? null,
            'Frame arguments must be restored to plain values.',
        );
    }

    public function testTraceRetainsClassMetadata(): void
    {
        try {
            $this->throwFromTrace();
        } catch (RuntimeException $throwable) {
            $frame = ExceptionSnapshot::fromThrowable($throwable)
                ->getTrace()[0] ?? self::fail('Expected the throwing method in the captured trace.');
        }

        self::assertSame(
            __NAMESPACE__,
            $frame['namespace'] ?? null,
            'Frame namespace must exclude the class name.',
        );
        self::assertSame(
            self::class,
            $frame['class'] ?? null,
            'Frame class must retain its fully qualified name.',
        );
        self::assertSame(
            'ExceptionSnapshotTest',
            $frame['short_class'] ?? null,
            'Short class must exclude its namespace.',
        );
    }

    /**
     * Throws from a class method so trace metadata can be verified.
     */
    private function throwFromTrace(): never
    {
        throw new RuntimeException('trace fixture');
    }
}

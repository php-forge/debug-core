<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use Closure;
use PHPForge\Debug\Panel\Event\EventInspection;
use PHPForge\Debug\Tests\Panel\Event\EventInspectionTest;

use function array_fill;
use function array_fill_keys;
use function range;
use function str_repeat;

use const INF;

/**
 * Provides immutable option updates and invalid diagnostic payloads for {@see EventInspectionTest}.
 */
final class EventInspectionProvider
{
    /**
     * @return iterable<string, array{Closure(EventInspection): EventInspection, array<string, mixed>}>
     */
    public static function inspectionUpdates(): iterable
    {
        yield 'context and state' => [
            static fn(EventInspection $inspection): EventInspection => $inspection->withContext([], 'unsupported'),
            ['context' => [], 'contextStatus' => 'unsupported'],
        ];
        yield 'trace and state' => [
            static fn(EventInspection $inspection): EventInspection => $inspection->withTrace([], 'failed'),
            ['trace' => [], 'traceStatus' => 'failed'],
        ];
        yield 'lifecycle correlation' => [
            static fn(EventInspection $inspection): EventInspection => $inspection->withLifecycle(2, 'leave', 3, 12.5),
            ['pairId' => 2, 'phase' => 'leave', 'depth' => 3, 'clock' => 12.5],
        ];
    }

    /**
     * @return iterable<string, array{string, mixed, string}>
     */
    public static function invalidDiagnostics(): iterable
    {
        $invalidDiagnosticsMessage = "Invalid debug snapshot value at '$.inspection': expected bounded event diagnostics with valid capture states.";

        $longKey = str_repeat('k', 129);

        yield 'invalid context status' => [
            'contextStatus',
            'success',
            $invalidDiagnosticsMessage,
        ];
        yield 'invalid identity' => [
            'pairId',
            0,
            $invalidDiagnosticsMessage,
        ];
        yield 'invalid phase' => [
            'phase',
            'done',
            $invalidDiagnosticsMessage,
        ];
        yield 'invalid trace status' => [
            'traceStatus',
            'unknown',
            $invalidDiagnosticsMessage,
        ];
        yield 'large context' => [
            'context',
            array_fill_keys(range('a', 'q'), 'value'),
            $invalidDiagnosticsMessage,
        ];
        yield 'large trace' => [
            'trace',
            array_fill(0, 17, 'frame'),
            $invalidDiagnosticsMessage,
        ];
        yield 'long context' => [
            'context',
            ['field' => str_repeat('a', 2049)],
            "Invalid debug snapshot value at '$.inspection.context.field': expected bounded text.",
        ];
        yield 'long key' => [
            'context',
            [$longKey => 'value'],
            "Invalid debug snapshot value at '$.inspection.context.{$longKey}': expected bounded text.",
        ];
        yield 'long trace' => [
            'trace',
            [str_repeat('a', 2049)],
            "Invalid debug snapshot value at '$.inspection.trace[0]': expected bounded text.",
        ];
        yield 'negative clock' => [
            'clock',
            -0.1,
            $invalidDiagnosticsMessage,
        ];
        yield 'negative depth' => [
            'depth',
            -1,
            $invalidDiagnosticsMessage,
        ];
        yield 'non-text context' => [
            'context',
            ['payload' => ['object']],
            "Invalid debug snapshot value at '$.inspection.context.payload': expected bounded text.",
        ];
        yield 'non-text trace' => [
            'trace',
            [42],
            "Invalid debug snapshot value at '$.inspection.trace[0]': expected bounded text.",
        ];
        yield 'nonfinite clock' => [
            'clock',
            INF,
            "Invalid debug snapshot value at '$.inspection.clock': expected a number or null.",
        ];
        yield 'undeclared field' => [
            'outcome',
            'success',
            "Invalid debug snapshot value at '$.inspection.outcome': expected a declared field.",
        ];
    }
}

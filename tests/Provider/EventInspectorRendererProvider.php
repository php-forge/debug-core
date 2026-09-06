<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Event\EventInspectorRendererTest;

use function str_repeat;

/**
 * Provides capture states, context values, table widths, and group values and limits for {@see EventInspectorRendererTest}.
 */
final class EventInspectorRendererProvider
{
    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function captureStates(): iterable
    {
        yield 'captured' => [
            'captured',
            'captured',
            'Selected context at observation time',
            'Argument-free source trace',
        ];
        yield 'disabled' => [
            'disabled',
            'disabled',
            'Not captured (context capture is opt-in)',
            'Not captured (source trace capture is opt-in)',
        ];
        yield 'failed' => [
            'failed',
            'failed',
            'Context capture failed',
            'Source trace capture failed',
        ];
        yield 'unsupported' => [
            'unsupported',
            'disabled',
            'No context extractor for this event type',
            'Not captured (source trace capture is opt-in)',
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contextValues(): iterable
    {
        yield 'long value' => [str_repeat('a', 200)];
        yield 'multibyte value' => [str_repeat('a', 149) . "\u{20AC}tail"];
        yield 'short value' => ['short'];
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function groupLimits(): iterable
    {
        yield 'exactly eight groups' => [8, 0];
        yield 'two overflow groups' => [10, 2];
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function numericGroupKeys(): iterable
    {
        foreach (['name', 'class', 'senderClass'] as $attribute) {
            foreach (['0', '123', '-123', '0123'] as $value) {
                yield "{$attribute} {$value} without filters" => [$attribute, $value, false];
                yield "{$attribute} {$value} with filters" => [$attribute, $value, true];
            }
        }
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function observationTimes(): iterable
    {
        yield 'backward wall clock' => [3, '-125.000 ms', '-250.000 ms gap'];
        yield 'first observation' => [0, '+0.000 ms', 'First observation'];
        yield 'later observation' => [1, '+125.000 ms', '+125.000 ms gap'];
        yield 'same timestamp' => [2, '+125.000 ms', '+0.000 ms gap'];
    }

    /**
     * @return iterable<string, array{int<1, 1000>}>
     */
    public static function tableColumns(): iterable
    {
        yield 'Yii2 columns' => [6];
        yield 'Yii3 columns' => [4];
    }
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tests\Panel\Event\EventInspectorRendererTest;

use function str_repeat;

/**
 * Provides capture states, preview boundaries, and group values and limits for {@see EventInspectorRendererTest}.
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
     * @return iterable<string, array{string, string}>
     */
    public static function previews(): iterable
    {
        yield '161 bytes' => [
            str_repeat('a', 154),
            'Value: ' . str_repeat('a', 150) . '...',
        ];
        yield 'exactly 160 bytes' => [
            str_repeat('a', 153),
            'Value: ' . str_repeat('a', 153),
        ];
        yield 'multibyte boundary' => [
            str_repeat('a', 149) . "\u{20AC}tail",
            'Value: ' . str_repeat('a', 149) . '...',
        ];
        yield 'short value' => [
            'short',
            'Value: short',
        ];
    }
}

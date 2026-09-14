<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Provider;

use PHPForge\Debug\Tone;

/**
 * Data provider for {@see \PHPForge\Debug\Tests\Theme\CssTest} test cases.
 */
final class CssProvider
{
    /**
     * @return iterable<string, array{Tone, string}>
     */
    public static function badges(): iterable
    {
        yield 'danger' => [Tone::DANGER, 'yii-debug-badge yii-debug-badge-danger'];
        yield 'info' => [Tone::INFO, 'yii-debug-badge yii-debug-badge-info'];
        yield 'muted' => [Tone::MUTED, 'yii-debug-badge yii-debug-badge-muted'];
        yield 'success' => [Tone::SUCCESS, 'yii-debug-badge yii-debug-badge-success'];
        yield 'warning' => [Tone::WARNING, 'yii-debug-badge yii-debug-badge-warning'];
    }

    /**
     * @return iterable<string, array{Tone, string}>
     */
    public static function callouts(): iterable
    {
        yield 'danger' => [Tone::DANGER, 'yii-debug-callout yii-debug-callout-danger'];
        yield 'info' => [Tone::INFO, 'yii-debug-callout yii-debug-callout-info'];
        yield 'muted' => [Tone::MUTED, 'yii-debug-callout yii-debug-callout-muted'];
        yield 'success' => [Tone::SUCCESS, 'yii-debug-callout yii-debug-callout-success'];
        yield 'warning' => [Tone::WARNING, 'yii-debug-callout yii-debug-callout-warning'];
    }
}

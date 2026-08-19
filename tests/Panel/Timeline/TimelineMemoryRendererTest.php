<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Timeline;

use PHPForge\Debug\Panel\MemorySample;
use PHPForge\Debug\Panel\Timeline\TimelineMemoryRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see TimelineMemoryRenderer} producing the shared memory SVG from captured samples.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelineMemoryRendererTest extends TestCase
{
    public function testRenderProducesExactSharedSvg(): void
    {
        self::assertSame(
            <<<'HTML'
            <svg width="100" height="20" preserveAspectRatio="none" viewBox="0 0 100 20" xmlns="http://www.w3.org/2000/svg">
            <defs>
            <linearGradient id="yii-debug-tl-memory-gradient" x1="0" x2="0" y1="1" y2="0">
            <stop offset="10%" stop-color="currentColor" stop-opacity="0.18"><stop offset="60%" stop-color="currentColor" stop-opacity="0.45"><stop offset="90%" stop-color="currentColor" stop-opacity="0.65"><stop offset="100%" stop-color="currentColor" stop-opacity="0.85">
            </linearGradient>
            </defs><g>
            <polygon points="0 20 0 10 50 5 99.999 5 100 20" fill="url(#yii-debug-tl-memory-gradient)"><polyline points="0 20 0 10 50 5 100 5" fill="none" stroke="currentColor" stroke-width="1.5">
            </g>
            </svg>
            HTML,
            TimelineMemoryRenderer::render(
                [new MemorySample(1_000.0, 50), new MemorySample(1_050.0, 75)],
                1_000.0,
                100.0,
                100,
                100,
                20,
            ),
            'Memory samples must render the exact shared gradient, polygon, and polyline contract.',
        );
    }

    public function testRenderReturnsEmptyStringForInvalidGeometry(): void
    {
        self::assertSame(
            '',
            TimelineMemoryRenderer::render([], 0.0, 1.0, 1),
            'No samples must omit the SVG.',
        );
        self::assertSame(
            '',
            TimelineMemoryRenderer::render([new MemorySample(0.0, 1)], 0.0, 0.0, 1),
            'A zero duration must omit the SVG.',
        );
    }
}

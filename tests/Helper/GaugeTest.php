<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Helper;

use PHPForge\Debug\Helper\Gauge;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see Gauge} covering the micro-gauge markup, the no-scale passthrough, and the percentage clamps.
 */
#[Group('helpers')]
#[Group('gauge')]
final class GaugeTest extends TestCase
{
    public function testRenderClampsPercentagesIntoTheRailRange(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 100%;'><span class="yii-debug-gauge-value">big</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            Gauge::render('big', 2.0, 1.0),
            'Values above the maximum must clamp to `100%`.',
        );
        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 0%;'><span class="yii-debug-gauge-value">negative</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            Gauge::render('negative', -1.0, 4.0),
            'Negative values must clamp to `0%`.',
        );
    }

    public function testRenderReturnsValueUntouchedWithoutPositiveMax(): void
    {
        self::assertSame(
            '125 ms',
            Gauge::render('125 ms', 0.5, 0.0),
            'Zero maximum must skip the rail entirely.',
        );
        self::assertSame(
            '125 ms',
            Gauge::render('125 ms', 0.5, -1.0),
            'Negative maximum must skip the rail entirely.',
        );
    }

    public function testRenderRoundsTheRailPercentageToThreeDecimals(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 33.333%;'><span class="yii-debug-gauge-value">1 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            Gauge::render('1 ms', 1.0, 3.0),
            'Thirds must round to three decimals.',
        );
    }

    public function testRenderWrapsValueAndRailInGaugeMarkup(): void
    {
        self::assertSame(
            <<<HTML
            <span class="yii-debug-gauge" style='--yii-debug-gauge: 50%;'><span class="yii-debug-gauge-value">125 ms</span><span class="yii-debug-gauge-bar" aria-hidden="true"></span></span>
            HTML,
            Gauge::render('125 ms', 0.5, 1.0),
            'Markup must carry the value span and the decorative rail.',
        );
    }
}

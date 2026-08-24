<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Timeline;

use PHPForge\Debug\Panel\MemorySample;
use UIAwesome\Html\Svg\{Defs, G, LinearGradient, Polygon, Polyline, Stop, Svg};

use function rtrim;
use function sprintf;
use function usort;

/**
 * Renders profiler memory samples as the shared inline timeline SVG.
 */
final class TimelineMemoryRenderer
{
    private const array GRADIENT = [
        10 => 0.18,
        60 => 0.45,
        90 => 0.65,
        100 => 0.85,
    ];

    /**
     * Renders an SVG memory graph, or `''` when its geometry cannot be resolved.
     *
     * @param list<MemorySample> $samples Memory samples in any order.
     */
    public static function render(
        array $samples,
        float $start,
        float $duration,
        int $memory,
        int $width = 1920,
        int $height = 40,
    ): string {
        if ($samples === [] || $duration <= 0.0 || $memory <= 0 || $width <= 0 || $height <= 0) {
            return '';
        }

        $points = [];

        foreach ($samples as $sample) {
            $points[] = [
                ($sample->time - $start) / $duration * $width,
                $height - ($sample->memory / $memory * $height),
            ];
        }

        usort($points, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        return Svg::tag()
            ->addAriaAttribute('hidden', 'true')
            ->addAttribute('focusable', 'false')
            ->height($height)
            ->html(
                Defs::tag()->html(self::gradient()),
                G::tag()
                    ->html(
                        Polygon::tag()
                            ->points(self::polygonPoints($points, $width, $height))
                            ->fill('url(#yii-debug-tl-memory-gradient)'),
                        Polyline::tag()
                            ->points(self::polylinePoints($points, $width, $height))
                            ->fill('none')
                            ->stroke('currentColor')
                            ->strokeWidth('1.5'),
                    ),
            )
            ->preserveAspectRatio('none')
            ->viewBox("0 0 {$width} {$height}")
            ->width($width)
            ->xmlns('http://www.w3.org/2000/svg')
            ->render();
    }

    private static function gradient(): LinearGradient
    {
        $stops = [];

        foreach (self::GRADIENT as $percent => $opacity) {
            $stops[] = Stop::tag()
                ->offset("{$percent}%")
                ->stopColor('currentColor')
                ->stopOpacity(self::number($opacity));
        }

        return LinearGradient::tag()
            ->id('yii-debug-tl-memory-gradient')
            ->x1(0)
            ->x2(0)
            ->y1(1)
            ->y2(0)
            ->html(...$stops);
    }

    private static function number(float|int $value): string
    {
        $rendered = rtrim(sprintf('%.6F', $value), '0');

        return rtrim($rendered, '.');
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     */
    private static function polygonPoints(array $points, int $width, int $height): string
    {
        $rendered = "0 {$height}";

        $lastY = $height;

        foreach ($points as [$x, $y]) {
            $rendered .= ' ' . self::number($x) . ' ' . self::number($y);
            $lastY = $y;
        }

        return $rendered
            . ' ' . self::number($width - 0.001) . ' ' . self::number($lastY)
            . " {$width} {$height}";
    }

    /**
     * @param list<array{0: float, 1: float}> $points
     */
    private static function polylinePoints(array $points, int $width, int $height): string
    {
        $rendered = "0 {$height}";

        $lastY = $height;

        foreach ($points as [$x, $y]) {
            $rendered .= ' ' . self::number($x) . ' ' . self::number($y);
            $lastY = $y;
        }

        return $rendered . " {$width} " . self::number($lastY);
    }
}

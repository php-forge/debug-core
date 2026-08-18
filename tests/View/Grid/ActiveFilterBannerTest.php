<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\View\Grid;

use PHPForge\Debug\View\Grid\ActiveFilterBanner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function implode;
use function substr_count;

/**
 * Unit tests for {@see ActiveFilterBanner} covering the removable filter pills, the "Clear all" action, and the
 * empty-state short-circuit.
 *
 * @since 0.1
 */
#[Group('view')]
#[Group('grid')]
final class ActiveFilterBannerTest extends TestCase
{
    public function testRenderBuildsRemovalUrlsThroughTheCallback(): void
    {
        $html = ActiveFilterBanner::render(
            ['statusCode' => '404', 'url' => 'admin'],
            static fn(array $without): string => '/debug?without=' . implode(',', $without),
        );

        self::assertStringContainsString(
            'href="/debug?without=statusCode"',
            $html,
            'Pill link must drop only its own attribute.',
        );
        self::assertStringContainsString(
            'href="/debug?without=statusCode,url"',
            $html,
            'Clear-all link must drop every active attribute.',
        );
    }

    public function testRenderEmitsOnePillPerActiveFilter(): void
    {
        $html = ActiveFilterBanner::render(
            ['statusCode' => '404', 'url' => 'admin'],
            static fn(array $without): string => '/debug',
        );

        self::assertSame(2, substr_count($html, 'yii-debug-active-filter-pill'), 'One pill per active filter.');
        self::assertStringContainsString('2 filters active', $html, 'Plural count label must surface.');
        self::assertStringContainsString('statusCode', $html, 'Attribute names must surface inside the pills.');
        self::assertStringContainsString('404', $html, 'Filter values must surface inside the pills.');
        self::assertStringContainsString('Clear all', $html, 'The clear-all action must render.');
        self::assertStringContainsString('aria-label="Active filters"', $html, 'Group must carry its accessible name.');
    }

    public function testRenderReturnsEmptyStringWhenNoFiltersAreActive(): void
    {
        self::assertSame(
            '',
            ActiveFilterBanner::render([], static fn(array $without): string => '/debug'),
            'No active filters must render no banner.',
        );
    }

    public function testRenderUsesSingularLabelForOneFilter(): void
    {
        self::assertStringContainsString(
            '1 filter active',
            ActiveFilterBanner::render(['url' => 'admin'], static fn(array $without): string => '/debug'),
            'Single filter must use the singular label.',
        );
    }
}

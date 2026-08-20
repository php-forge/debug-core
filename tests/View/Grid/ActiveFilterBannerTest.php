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

        self::assertSame(
            <<<HTML
            <div class="yii-debug-active-filters" role="group" aria-label="Active filters">
            <span class="yii-debug-active-filters-label">2 filters active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug?without=statusCode" title="Remove this filter"><span class="yii-debug-active-filter-attr">statusCode</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">404</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a><a class="yii-debug-active-filter-pill" href="/debug?without=url" title="Remove this filter"><span class="yii-debug-active-filter-attr">url</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">admin</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug?without=statusCode,url" title="Clear all filters and show every row">Clear all</a>
            </div>
            HTML,
            $html,
            'Pill link must drop only its own attribute.',
        );

    }

    public function testRenderEmitsOnePillPerActiveFilter(): void
    {
        $html = ActiveFilterBanner::render(
            ['statusCode' => '404', 'url' => 'admin'],
            static fn(array $without): string => '/debug',
        );

        self::assertSame(
            2,
            substr_count($html, 'yii-debug-active-filter-pill'),
            'One pill per active filter.',
        );
        self::assertSame(
            <<<HTML
            <div class="yii-debug-active-filters" role="group" aria-label="Active filters">
            <span class="yii-debug-active-filters-label">2 filters active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug" title="Remove this filter"><span class="yii-debug-active-filter-attr">statusCode</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">404</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a><a class="yii-debug-active-filter-pill" href="/debug" title="Remove this filter"><span class="yii-debug-active-filter-attr">url</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">admin</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug" title="Clear all filters and show every row">Clear all</a>
            </div>
            HTML,
            $html,
            'Plural count label must surface.',
        );
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
        self::assertSame(
            <<<HTML
            <div class="yii-debug-active-filters" role="group" aria-label="Active filters">
            <span class="yii-debug-active-filters-label">1 filter active</span><span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/debug" title="Remove this filter"><span class="yii-debug-active-filter-attr">url</span><span class="yii-debug-active-filter-sep">:</span><span class="yii-debug-active-filter-value">admin</span><span class="yii-debug-active-filter-x" aria-hidden="true">×</span></a></span><a class="yii-debug-active-filters-clear" href="/debug" title="Clear all filters and show every row">Clear all</a>
            </div>
            HTML,
            ActiveFilterBanner::render(['url' => 'admin'], static fn(array $without): string => '/debug'),
            'Single filter must use the singular label.',
        );
    }
}

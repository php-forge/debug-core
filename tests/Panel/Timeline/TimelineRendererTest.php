<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Timeline;

use PHPForge\Debug\Panel\Profile\ProfileRow;
use PHPForge\Debug\Panel\Timeline\{TimelineGeometry, TimelineRenderer};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Unit tests for {@see TimelineRenderer} preserving the complete cross-adapter Timeline markup contract.
 */
#[Group('panel')]
#[Group('timeline')]
final class TimelineRendererTest extends TestCase
{
    public function testRenderChartDeclaresExactScalarDefaults(): void
    {
        $parameters = (new ReflectionMethod(TimelineRenderer::class, 'renderChart'))->getParameters();
        $defaults = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                $defaults[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }

        self::assertSame(
            0,
            $defaults['memory'] ?? null,
            'Peak memory must default to exactly zero bytes.',
        );
        self::assertSame(
            40,
            $defaults['memoryHeight'] ?? null,
            'Memory chart height must default to exactly 40 pixels.',
        );
    }

    public function testRenderChartFormatsSecondsAndOmitsSingleCategoryLegend(): void
    {
        $rows = TimelineGeometry::spans(
            [
                new ProfileRow(1_000.0, 1_500.0, 'Yii3\\Application::handle', 'GET /slow', 0, 0, 0, 0, []),
            ],
            1_000.0,
            2_000.0,
        );

        $html = TimelineRenderer::renderChart($rows, [1_500 => 75.0]);

        self::assertSame(
            1,
            substr_count($html, '>1.5 s</span>'),
            'Second-based ruler label must render once.',
        );
        self::assertSame(
            0,
            substr_count($html, 'yii-debug-tl-legend-item'),
            'A single category must not render a redundant legend.',
        );
    }

    public function testRenderChartFormatsTickBoundariesExactly(): void
    {
        $rows = TimelineGeometry::spans(
            [new ProfileRow(0.0, 10.0, 'Queue\\Job', 'job', 0, 0, 0, 0, [])],
            0.0,
            100.0,
        );

        self::assertSame(
            <<<HTML
            <section class="yii-debug-tl">
            <header class="yii-debug-tl-axis">
            <span class="yii-debug-tl-tick" style='left: 10%;'>1 s</span><span class="yii-debug-tl-tick" style='left: 20%;'>1 s</span><span class="yii-debug-tl-tick" style='left: 30%;'>1.1 s</span>
            </header><div class="yii-debug-tl-rows" role="list">
            <div class="yii-debug-tl-row yii-debug-tl-row-queue" title="job
            10.000 ms · 0.00 MB" role="listitem">
            <div class="yii-debug-tl-label" style='--depth: 0;'>
            <span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-name"><span title="Queue\Job"><span class="yii-debug-muted">Queue\</span><wbr><strong>Job</strong></span></span><span class="yii-debug-tl-bar-duration">10.0 ms</span>
            </div><div class="yii-debug-tl-track">
            <div class="yii-debug-tl-bar" style='left: 0%; width: 10%;'>
            </div>
            </div>
            </div>
            </div>
            </section>
            HTML,
            TimelineRenderer::renderChart($rows, [1_000 => 10.0, 1_049 => 20.0, 1_050 => 30.0]),
            'Millisecond-to-second boundaries and decimal trimming must render exactly.',
        );
    }

    public function testRenderChartIncludesNonAdjacentLegendVariants(): void
    {
        $rows = TimelineGeometry::spans(
            [
                new ProfileRow(0.0, 10.0, 'Yii3\\Application::handle', 'app', 0, 0, 0, 0, []),
                new ProfileRow(10.0, 10.0, 'Queue\\Job', 'queue', 0, 1, 0, 0, []),
            ],
            0.0,
            100.0,
        );

        self::assertSame(
            <<<HTML
            <section class="yii-debug-tl">
            <header class="yii-debug-tl-axis">
            </header><div class="yii-debug-tl-legend">
            <span class="yii-debug-tl-legend-item yii-debug-tl-row-app"><span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-legend-label">Application</span></span><span class="yii-debug-tl-legend-item yii-debug-tl-row-queue"><span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-legend-label">Queue</span></span>
            </div><div class="yii-debug-tl-rows" role="list">
            <div class="yii-debug-tl-row yii-debug-tl-row-app" title="app
            10.000 ms · 0.00 MB" role="listitem">
            <div class="yii-debug-tl-label" style='--depth: 0;'>
            <span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-name"><span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span></span><span class="yii-debug-tl-bar-duration">10.0 ms</span>
            </div><div class="yii-debug-tl-track">
            <div class="yii-debug-tl-bar" style='left: 0%; width: 10%;'>
            </div>
            </div>
            </div><div class="yii-debug-tl-row yii-debug-tl-row-queue" title="queue
            10.000 ms · 0.00 MB" role="listitem">
            <div class="yii-debug-tl-label" style='--depth: 0;'>
            <span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-name"><span title="Queue\Job"><span class="yii-debug-muted">Queue\</span><wbr><strong>Job</strong></span></span><span class="yii-debug-tl-bar-duration">10.0 ms</span>
            </div><div class="yii-debug-tl-track">
            <div class="yii-debug-tl-bar" style='left: 10%; width: 10%;'>
            </div>
            </div>
            </div>
            </div>
            </section>
            HTML,
            TimelineRenderer::renderChart($rows, []),
            'Legend traversal must retain variants separated by absent canonical categories.',
        );
    }
    public function testRenderChartProducesExactSharedMarkup(): void
    {
        $rows = TimelineGeometry::spans(
            [
                new ProfileRow(1_000.0, 50.0, 'Yii3\\Application::handle', 'GET /', 0, 0, 0, 0, []),
                new ProfileRow(1_025.0, 10.0, 'Yiisoft\\Db\\Command::query', 'SELECT 1', 1, 1, 0, 0, []),
            ],
            1_000.0,
            100.0,
        );

        self::assertSame(
            <<<HTML
            <section class="yii-debug-tl">
            <header class="yii-debug-tl-axis">
            <span class="yii-debug-tl-tick" style='left: 0%;'>0 ms</span><span class="yii-debug-tl-tick" style='left: 50%;'>50 ms</span>
            </header><div class="yii-debug-tl-legend">
            <span class="yii-debug-tl-legend-item yii-debug-tl-row-app"><span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-legend-label">Application</span></span><span class="yii-debug-tl-legend-item yii-debug-tl-row-db"><span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-legend-label">Database</span></span>
            </div><div class="yii-debug-tl-rows" role="list">
            <div class="yii-debug-tl-row yii-debug-tl-row-app" title="GET /
            50.000 ms · 0.00 MB" role="listitem">
            <div class="yii-debug-tl-label" style='--depth: 0;'>
            <span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-name"><span title="Yii3\Application::handle"><span class="yii-debug-muted">Yii3\</span><wbr><strong>Application::handle</strong></span></span><span class="yii-debug-tl-bar-duration">50.0 ms</span>
            </div><div class="yii-debug-tl-track">
            <div class="yii-debug-tl-bar" style='left: 0%; width: 50%;'>
            </div>
            </div>
            </div><div class="yii-debug-tl-row yii-debug-tl-row-db" title="SELECT 1
            10.000 ms · 0.00 MB" role="listitem">
            <div class="yii-debug-tl-label" style='--depth: 1;'>
            <span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-name"><span title="Yiisoft\Db\Command::query"><span class="yii-debug-muted">Yiisoft\Db\</span><wbr><strong>Command::query</strong></span></span><span class="yii-debug-tl-bar-duration">10.0 ms</span>
            </div><div class="yii-debug-tl-track">
            <div class="yii-debug-tl-bar" style='left: 25%; width: 10%;'>
            </div>
            </div>
            </div>
            </div><footer class="yii-debug-tl-memory">
            <span class="yii-debug-tl-memory-label">Memory</span><div class="yii-debug-tl-memory-track" style='height: 20px;'>
            <svg></svg>
            </div><span class="yii-debug-tl-memory-peak">2.00 MB</span>
            </footer>
            </section>
            HTML,
            TimelineRenderer::renderChart($rows, [0 => 0.0, 50 => 50.0], '<svg></svg>', 2_097_152, 20),
            'Axis, legend, nested rows, bars, and memory footer must render exactly.',
        );
        self::assertSame(
            '',
            TimelineRenderer::renderChart([], []),
            'A chart without spans must stay empty.',
        );
    }

    public function testRenderChartUsesExactDefaultMemoryValues(): void
    {
        $rows = TimelineGeometry::spans(
            [new ProfileRow(0.0, 10.0, 'Queue\\Job', 'job', 0, 0, 0, 0, [])],
            0.0,
            100.0,
        );

        self::assertSame(
            <<<HTML
            <section class="yii-debug-tl">
            <header class="yii-debug-tl-axis">
            </header><div class="yii-debug-tl-rows" role="list">
            <div class="yii-debug-tl-row yii-debug-tl-row-queue" title="job
            10.000 ms · 0.00 MB" role="listitem">
            <div class="yii-debug-tl-label" style='--depth: 0;'>
            <span class="yii-debug-tl-dot" aria-hidden="true"></span><span class="yii-debug-tl-name"><span title="Queue\Job"><span class="yii-debug-muted">Queue\</span><wbr><strong>Job</strong></span></span><span class="yii-debug-tl-bar-duration">10.0 ms</span>
            </div><div class="yii-debug-tl-track">
            <div class="yii-debug-tl-bar" style='left: 0%; width: 10%;'>
            </div>
            </div>
            </div>
            </div><footer class="yii-debug-tl-memory">
            <span class="yii-debug-tl-memory-label">Memory</span><div class="yii-debug-tl-memory-track" style='height: 40px;'>
            <svg></svg>
            </div><span class="yii-debug-tl-memory-peak">0.00 MB</span>
            </footer>
            </section>
            HTML,
            TimelineRenderer::renderChart($rows, [], '<svg></svg>'),
            'The default memory value and footer height must render exactly.',
        );
    }
    public function testRenderFilterFormProducesExactSharedMarkup(): void
    {
        self::assertSame(
            <<<HTML
            <form class="yii-debug-tl-filter" action="/debug/view" method="get">
            <input name="tag" type="hidden" value="request-1"><input name="panel" type="hidden" value="timeline"><div class="yii-debug-tl-field">
            <label for="tl-duration">Min duration (ms)</label><input id="tl-duration" name="Timeline[duration]" type="number" value="5" min="0" placeholder="0" step="0.1">
            </div><div class="yii-debug-tl-field yii-debug-tl-field-grow">
            <label for="tl-category">Category</label><input id="tl-category" name="Timeline[category]" type="text" value="Yiisoft\Db" placeholder="yii\db\Command::query">
            </div><button class="yii-debug-btn yii-debug-btn-primary yii-debug-btn-sm" type="submit">Apply</button>
            </form>
            HTML,
            TimelineRenderer::renderFilterForm(
                '/debug/view',
                ['tag' => 'request-1', 'panel' => 'timeline'],
                '5',
                'Yiisoft\\Db',
            ),
            'The shared Timeline form must preserve hidden routing parameters and filter values exactly.',
        );
    }

    public function testRenderHintAndSummaryProduceExactSharedMarkup(): void
    {
        self::assertSame(
            <<<HTML
            <header class="yii-debug-grid-summary">
            <span><strong>123</strong> ms total</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2.00 MB</strong> peak memory</span><span class="yii-debug-grid-summary-sep">·</span><span><strong>2</strong> spans</span>
            </header>
            HTML,
            TimelineRenderer::renderSummary(123.4, 2_097_152, 2),
            'Timeline totals must render exactly.',
        );
        self::assertSame(
            <<<'HTML'
            <div class="yii-debug-tl-hint">
            <p class="yii-debug-tl-hint-title">
            No spans matched your filter.
            </p><p class="yii-debug-tl-hint-body">
            The timeline is most useful for requests that take hundreds of milliseconds, where you can <em>see</em> which operations dominate. For quick requests the <a href="/debug/view?tag=request-1&amp;panel=profiling">Profiling panel</a> presents the same data as a sortable list easier to scan.
            </p>
            </div>
            HTML,
            TimelineRenderer::renderEmptyHint(false, '/debug/view?tag=request-1&panel=profiling'),
            'The empty-state guidance and Profiling link must render exactly.',
        );
        self::assertSame(
            '',
            TimelineRenderer::renderEmptyHint(true, '/profiling'),
            'Visible spans must omit the hint.',
        );
    }
}

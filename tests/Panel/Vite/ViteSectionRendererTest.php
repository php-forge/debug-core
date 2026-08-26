<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Vite;

use PHPForge\Debug\Panel\Asset\ViteChunk;
use PHPForge\Debug\Panel\Vite\{ViteComponent, ViteSectionRenderer, ViteSummary};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the shared Vite detail renderer and its mode-specific states.
 */
#[Group('panel')]
#[Group('vite')]
final class ViteSectionRendererTest extends TestCase
{
    public function testDevelopmentIntegrationUsesSharedPanelTableWithoutAVisibleComponentTitle(): void
    {
        $component = self::component(
            id: 'inertiaVue',
            mode: ViteComponent::MODE_DEVELOPMENT,
            entrypoints: ['resources/js/app.js'],
            devServerUrl: 'http://localhost:5173',
            includeViteClient: true,
        );

        $html = ViteSectionRenderer::render(new ViteSummary([$component]));

        self::assertStringContainsString(
            'class="yii-debug-table yii-debug-table-mono yii-debug-table-vite-overview"',
            $html,
            'Configuration must use the same overview table as the other diagnostic panels.',
        );
        self::assertStringContainsString('scope="row"', $html, 'Configuration labels must identify their table rows.');
        self::assertStringContainsString('Component ID', $html, 'The exact adapter registration ID must remain visible.');
        self::assertStringContainsString('inertiaVue', $html, 'The exact case-sensitive component ID must be preserved.');
        self::assertDoesNotMatchRegularExpression(
            '~<h2[^>]*>\s*inertiaVue\s*</h2>~',
            $html,
            'The technical component ID must not be repeated as a visible panel title.',
        );
        self::assertStringContainsString(
            '<div class="yii-debug-section-header">',
            $html,
            'The chunk heading must use the shared tabular-section treatment.',
        );
        self::assertStringContainsString('Implementation', $html, 'The implementation field must remain readable.');
        self::assertMatchesRegularExpression(
            '~<th scope="row">\s*Mode\s*</th><td>\s*Development\s*</td>~',
            $html,
            'Development mode must be rendered in its overview row.',
        );
        self::assertMatchesRegularExpression(
            '~<th scope="row">\s*Inspection\s*</th><td>\s*'
            . '<span class="yii-debug-badge yii-debug-badge-success">Available</span>\s*</td>~',
            $html,
            'Successful inspection must use the success badge in its overview row.',
        );
        self::assertStringContainsString('resources/js/app.js', $html, 'Configured entrypoints must be rendered.');
        self::assertStringContainsString('http://localhost:5173', $html, 'The development server must be rendered.');
        self::assertStringContainsString('Enabled', $html, 'Enabled development options must remain explicit.');
        self::assertStringContainsString('Not applicable', $html, 'Production-only options must be identified.');
        self::assertStringContainsString(
            'Development mode resolves entry points through the dev server.',
            $html,
            'Development mode must explain the absence of build chunks.',
        );
    }

    public function testDifferentComponentModesRenderMixedPluralSummary(): void
    {
        $html = ViteSectionRenderer::render(
            new ViteSummary(
                [
                    self::component(id: 'frontend', mode: ViteComponent::MODE_DEVELOPMENT),
                    self::component(id: 'admin', mode: ViteComponent::MODE_PRODUCTION),
                ],
            ),
        );

        self::assertStringContainsString('2</strong> components', $html, 'Multiple integrations need a plural count.');
        self::assertStringContainsString('Mixed', $html, 'Different runtime modes must be summarized as mixed.');
        self::assertSame(
            2,
            substr_count($html, 'class="yii-debug-vite-component"'),
            'Every integration needs its own section.',
        );
    }
    public function testEmptySummaryRendersAccessibleHeadingAndFallback(): void
    {
        $html = ViteSectionRenderer::render(new ViteSummary([]));

        self::assertStringContainsString(
            '<h1 class="yii-debug-sr-only">',
            $html,
            'The panel name must remain available to assistive technology.',
        );
        self::assertStringContainsString('0</strong> components', $html, 'The empty summary must report zero components.');
        self::assertStringContainsString(
            'No Vite integrations captured',
            $html,
            'Opening an empty snapshot directly must show a clear fallback.',
        );
        self::assertStringNotContainsString(
            'yii-debug-grid-summary-sep',
            $html,
            'An empty summary must not append a meaningless mode separator.',
        );
        self::assertLessThan(
            strpos($html, 'No Vite integrations captured'),
            strpos($html, '<h1 class="yii-debug-sr-only">'),
            'The accessible panel heading must precede the empty state.',
        );
        self::assertLessThan(
            strpos($html, '<header class="yii-debug-grid-summary">'),
            strpos($html, '<h1 class="yii-debug-sr-only">'),
            'The accessible panel heading must precede the summary strip.',
        );
        self::assertLessThan(
            strpos($html, 'No Vite integrations captured'),
            strpos($html, '<header class="yii-debug-grid-summary">'),
            'The summary strip must precede the empty state.',
        );
    }

    public function testOverviewEscapesPlainValuesWhileRenderingTypedBadgeMarkup(): void
    {
        $html = ViteSectionRenderer::render(
            new ViteSummary(
                [
                    self::component(
                        id: '<vite>',
                        entrypoints: ['<script>'],
                    ),
                ],
            ),
        );

        self::assertStringContainsString('&lt;vite&gt;', $html, 'Plain component metadata must remain escaped.');
        self::assertStringContainsString('&lt;script&gt;', $html, 'Plain entrypoint metadata must remain escaped.');
        self::assertStringNotContainsString('<vite>', $html, 'Component metadata must never become markup.');
        self::assertStringNotContainsString('<script>', $html, 'Entrypoint metadata must never become markup.');
        self::assertStringContainsString(
            '<span class="yii-debug-badge yii-debug-badge-success">Available</span>',
            $html,
            'Typed badge markup must render instead of being escaped as text.',
        );
    }

    public function testProductionIntegrationExplainsAnEmptyManifest(): void
    {
        $component = self::component(
            mode: ViteComponent::MODE_PRODUCTION,
            includeViteClient: null,
            modulePreload: true,
        );

        $html = ViteSectionRenderer::render(new ViteSummary([$component]));

        self::assertStringContainsString('Enabled', $html, 'Enabled production options must remain explicit.');
        self::assertStringContainsString(
            'The Vite manifest is missing or empty — run the front-end build to populate it.',
            $html,
            'Production mode must explain how to populate an empty manifest.',
        );
    }

    public function testProductionIntegrationRendersChunkInventoryAndDisabledOption(): void
    {
        $component = self::component(
            id: 'frontend',
            mode: ViteComponent::MODE_PRODUCTION,
            baseUrl: '/build',
            devServerUrl: null,
            manifestPath: '/app/public/build/.vite/manifest.json',
            includeViteClient: null,
            modulePreload: false,
            chunks: [
                new ViteChunk('resources/js/app.js', 'assets/app.js', 1, 2, true),
                new ViteChunk('_shared.js', '', 0, 0, false),
            ],
        );

        $html = ViteSectionRenderer::render(new ViteSummary([$component]));

        self::assertStringContainsString('1</strong> component', $html, 'A single integration must use the singular label.');
        self::assertMatchesRegularExpression(
            '~<th scope="row">\s*Mode\s*</th><td>\s*Production\s*</td>~',
            $html,
            'Production mode must be rendered in its overview row.',
        );
        self::assertMatchesRegularExpression(
            '~<th scope="row">\s*Base URL\s*</th><td>\s*/build\s*</td>~',
            $html,
            'The production base URL must be rendered in its overview row.',
        );
        self::assertStringContainsString(
            '/app/public/build/.vite/manifest.json',
            $html,
            'The manifest path must be rendered.',
        );
        self::assertStringContainsString('Disabled', $html, 'Disabled production options must remain explicit.');
        self::assertStringContainsString('scope="col"', $html, 'Chunk headers must identify their column scope.');
        self::assertStringContainsString('assets/app.js', $html, 'Emitted chunk files must be rendered.');
        self::assertMatchesRegularExpression(
            '~<tr>\s*<td>\s*1\s*</td>.*?resources/js/app\.js.*?'
            . '<span class="yii-debug-badge yii-debug-badge-success">entry</span>\s*</td>\s*</tr>~s',
            $html,
            'The first chunk must keep its one-based index and entry badge.',
        );
        self::assertMatchesRegularExpression(
            '~<tr>\s*<td>\s*2\s*</td>.*?_shared\.js.*?'
            . '<td class="yii-debug-cell-mono">\s*—\s*</td>.*?<span>—</span>\s*</td>\s*</tr>~s',
            $html,
            'The second non-entry chunk must keep its index and placeholders.',
        );
    }

    public function testUnavailableUnknownIntegrationRendersWarningAndUnknownValues(): void
    {
        $component = self::component(
            inspectionAvailable: false,
            mode: ViteComponent::MODE_UNKNOWN,
            entrypoints: [],
            includeViteClient: null,
            modulePreload: null,
        );

        $html = ViteSectionRenderer::render(new ViteSummary([$component]));

        self::assertMatchesRegularExpression(
            '~<th scope="row">\s*Inspection\s*</th><td>\s*'
            . '<span class="yii-debug-badge yii-debug-badge-warning">Unavailable</span>\s*</td>~',
            $html,
            'Failed inspection must use the warning badge in its overview row.',
        );
        self::assertStringContainsString('role="status"', $html, 'The inspection warning must expose status semantics.');
        self::assertStringContainsString(
            'Runtime inspection is unavailable for this component.',
            $html,
            'Failed inspection must explain why fields are unavailable.',
        );
        self::assertGreaterThanOrEqual(3, substr_count($html, 'Unknown'), 'Unknown mode and flags must remain explicit.');
        self::assertStringContainsString(
            'No build chunks were available for inspection.',
            $html,
            'Unknown mode must use the neutral empty-chunk explanation.',
        );
    }

    /**
     * @param list<string> $entrypoints
     * @param list<ViteChunk> $chunks
     */
    private static function component(
        string $id = 'vite',
        bool $inspectionAvailable = true,
        string $mode = ViteComponent::MODE_DEVELOPMENT,
        array $entrypoints = [],
        string $baseUrl = '',
        string|null $devServerUrl = null,
        string $manifestPath = '',
        bool|null $includeViteClient = false,
        bool|null $modulePreload = null,
        array $chunks = [],
    ): ViteComponent {
        return new ViteComponent(
            id: $id,
            class: 'PHPForge\Vite\Vite',
            implementation: ViteComponent::IMPLEMENTATION_MODERN,
            inspectionAvailable: $inspectionAvailable,
            mode: $mode,
            entrypoints: $entrypoints,
            baseUrl: $baseUrl,
            devServerUrl: $devServerUrl,
            manifestPath: $manifestPath,
            includeViteClient: $includeViteClient,
            modulePreload: $modulePreload,
            chunks: $chunks,
        );
    }
}

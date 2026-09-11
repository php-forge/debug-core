<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use JsonException;
use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Helper\CellMore;
use PHPForge\Debug\Panel\PanelRenderer;
use PHPForge\Debug\Tests\Provider\PanelRendererProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see PanelRenderer} rendering provider-owned declarative panels through the debugger frontend.
 *
 * {@see PanelRendererProvider} for test case data providers.
 */
final class PanelRendererTest extends TestCase
{
    #[DataProviderExternal(PanelRendererProvider::class, 'nonCollapsingTables')]
    public function testCollapseRequiresOptInAndMoreRowsThanThreshold(int $count, bool $collapsible): void
    {
        $view = PanelView::create()->table(['Value'], array_fill(0, $count, ['short']), $collapsible);

        $html = PanelRenderer::render('Custom', $view);

        self::assertStringNotContainsString(
            'class="yii-debug-cell-more"',
            $html,
            'No disclosure control must be rendered.',
        );
    }

    public function testJsonPreviewPreservesUnicodeAndUnescapedSlashes(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->paragraph(PanelView::value('/café')),
        );

        self::assertStringContainsString(
            '/café',
            $html,
            "Raw '/café' must appear verbatim.",
        );
    }

    public function testLargeTablesAndValuesUseExistingCollapseControls(): void
    {
        $value = str_repeat('x', 1000);
        $rows = array_fill(0, CellMore::ROW_THRESHOLD + 1, [PanelView::preview($value)]);

        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->table(['Value'], $rows, collapsible: true),
        );

        self::assertStringContainsString(
            'yii-debug-cell-more',
            $html,
            'Long values must retain the standard disclosure.',
        );
        self::assertSame(
            CellMore::ROW_THRESHOLD + 2,
            substr_count($html, 'class="yii-debug-cell-more"'),
            'Both the table and each long value must have collapse controls.',
        );
        self::assertStringContainsString(
            $value,
            $html,
            'Collapsing must not truncate captured values.',
        );
    }

    public function testRenderUsesExistingFrontendAndEscapesEveryTextPosition(): void
    {
        $hostile = '<script>alert("test")</script>';

        $view = PanelView::create()
            ->summary($hostile, $hostile)
            ->summary('', $hostile, emphasized: false)
            ->group(
                $hostile,
                PanelView::create()
                    ->heading($hostile, section: true)
                    ->overview([$hostile => PanelView::badge($hostile, Tone::WARNING)], compact: true)
                    ->callout(Tone::DANGER, $hostile, PanelView::strong($hostile), PanelView::code($hostile))
                    ->emptyState($hostile, $hostile)
                    ->disclosure($hostile, $hostile),
            )
            ->heading($hostile)
            ->overview(['Type' => PanelView::value(false, typeOnly: true)])
            ->table(
                ['Plain', 'Mono', 'Identifier', 'Number', 'Pill', 'Value'],
                [[$hostile, $hostile, PanelView::strong($hostile), '0', '—', PanelView::value($hostile)]],
                styles: [
                    1 => ColumnStyle::MONOSPACE,
                    2 => ColumnStyle::IDENTIFIER,
                    3 => ColumnStyle::NUMBER,
                    4 => ColumnStyle::PILL,
                    5 => ColumnStyle::PAYLOAD,
                ],
            );

        $html = PanelRenderer::render(
            $hostile,
            $view,
        );

        self::assertStringNotContainsString(
            '<script>',
            $html,
            'No data position may inject HTML.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Escaped diagnostic text must remain inspectable.',
        );
        self::assertStringContainsString(
            'yii-debug-table-overview',
            $html,
            'Overview tables must use the host stylesheet.',
        );
        self::assertStringContainsString(
            'yii-debug-callout-danger',
            $html,
            'Warnings must use semantic host styles.',
        );
        self::assertStringContainsString(
            'yii-debug-disclosure',
            $html,
            'Disclosures must retain existing frontend behavior.',
        );
        self::assertStringContainsString(
            '<span>—</span>',
            $html,
            'Empty pills must match the existing Vite presentation.',
        );
    }

    public function testThrowJsonExceptionForUnencodableValue(): void
    {
        $this->expectException(JsonException::class);

        PanelRenderer::render(
            'Custom',
            PanelView::create()->paragraph(PanelView::value(NAN)),
        );
    }
}

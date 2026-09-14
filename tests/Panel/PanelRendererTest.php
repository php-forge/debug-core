<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel;

use JsonException;
use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Helper\{CellMore, Trace};
use PHPForge\Debug\Panel\PanelRenderer;
use PHPForge\Debug\Tests\Provider\PanelRendererProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

use function array_fill;
use function substr_count;

/**
 * Unit tests for {@see PanelRenderer} rendering provider-owned declarative panels through the debugger frontend.
 *
 * {@see PanelRendererProvider} for test case data providers.
 */
final class PanelRendererTest extends TestCase
{
    public function testCapturedFramesRenderThroughTheAdapterFrameRenderer(): void
    {
        $view = PanelView::create()->paragraph(
            PanelView::trace([['file' => '/app/src/Site.php', 'line' => 42], ['internal' => 'call_user_func']]),
        );

        self::assertStringContainsString(
            '<ul class="yii-debug-trace">',
            PanelRenderer::render('Custom', $view),
            'Frames must reuse the existing trace list markup.',
        );
        self::assertStringContainsString(
            'ide://open?url=file:///app/src/Site.php&amp;line=42',
            PanelRenderer::render('Custom', $view),
            'The default frame renderer must keep its editor deep link.',
        );
        self::assertStringContainsString(
            '<li>' . "\n" . '/app/src/Site.php:42' . "\n" . '</li>',
            PanelRenderer::render('Custom', $view, Trace::create()->withTemplate(false)),
            'An adapter-configured frame renderer must replace the default one.',
        );
        self::assertStringContainsString(
            'internal',
            PanelRenderer::render('Custom', $view),
            'A frame without file or line must still be inspectable.',
        );
    }
    #[DataProviderExternal(PanelRendererProvider::class, 'nonCollapsingTables')]
    public function testCollapseRequiresOptInAndMoreRowsThanThreshold(int $count, bool $collapsible): void
    {
        $view = PanelView::create()->table(['Value'], array_fill(0, $count, ['short']), $collapsible);

        $html = PanelRenderer::render(
            'Custom',
            $view,
        );

        self::assertStringNotContainsString(
            'class="yii-debug-cell-more"',
            $html,
            'No disclosure control must be rendered.',
        );
    }

    public function testFactStripRendersEveryPairAsADefinitionList(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->facts(
                PanelView::fact('Charset', 'UTF-8'),
                PanelView::fact('Current language', 'en'),
            ),
        );

        self::assertStringContainsString(
            '<dl class="yii-debug-fact-strip">',
            $html,
            'Strip must be a definition list.',
        );
        self::assertStringContainsString(
            "<dt class=\"yii-debug-fact-label\">\nCharset\n</dt>",
            $html,
            'Label must be the term of its pair.',
        );
        self::assertStringContainsString(
            '<dd class="yii-debug-fact-value" title="UTF-8">',
            $html,
            'Value must carry the untruncated text as its title.',
        );
    }

    public function testFilterableTablesReuseTheExistingRowFilter(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->table(['Name', 'Value'], [['HTTP_HOST', 'example.test']], filterable: true),
        );

        self::assertStringContainsString(
            'data-yii-debug-filter-scope="true"',
            $html,
            'The filter must be paired with its table through an explicit scope.',
        );
        self::assertStringContainsString(
            '<input class="yii-debug-filter-input" type="search" aria-label="Filter Name, Value"'
            . ' data-yii-debug-filter="true" placeholder="Filter…">',
            $html,
            'The filter input must carry the hook the shared row filter listens to.',
        );
        self::assertStringContainsString(
            'data-yii-debug-filter-target="true"',
            $html,
            'The table must declare itself as the filter target.',
        );
        self::assertStringNotContainsString(
            'yii-debug-filter-scope',
            PanelRenderer::render('Custom', PanelView::create()->table(['Name'], [['x']])),
            'An ordinary table must not request the row filter.',
        );
    }

    public function testFilterableTablesStayCollapsibleAboveTheRowThreshold(): void
    {
        $rows = array_fill(0, CellMore::ROW_THRESHOLD + 1, ['x']);

        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->table(['Name'], $rows, collapsible: true, filterable: true),
        );

        self::assertStringContainsString(
            'yii-debug-cell-more-toggle',
            $html,
            'Filtering must not remove the collapse control.',
        );
        self::assertStringContainsString(
            'data-yii-debug-filter-scope="true"',
            $html,
            'Collapsing must not remove the filter scope.',
        );
    }

    public function testFrontendContractPinsOrderSeparatorsAndColumnTreatments(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()
                ->summary(' first', 1)
                ->summary(' second', 2)
                ->heading('Section', true)
                ->heading('Plain')
                ->table(
                    ['Pill', 'Badge', 'Plain'],
                    [['queued', PanelView::badge('done', Tone::SUCCESS), 'text']],
                    styles: [0 => ColumnStyle::PILL, 1 => ColumnStyle::PILL],
                ),
        );

        self::assertStringContainsString(
            '<span><strong>1</strong> first</span>'
            . '<span class="yii-debug-grid-summary-sep">·</span>'
            . '<span><strong>2</strong> second</span>',
            $html,
            'Exactly one separator must sit between the two metrics, never before the first.',
        );
        self::assertStringStartsWith(
            '<h1 class="yii-debug-sr-only">' . "\n" . 'Custom' . "\n" . '</h1><header class="yii-debug-grid-summary">',
            $html,
            'Order: accessible heading, summary strip, then content.',
        );
        self::assertStringContainsString(
            '<div class="yii-debug-section-header">' . "\n" . '<h2>' . "\n" . 'Section' . "\n" . '</h2>',
            $html,
            'Only the section heading may use the section wrapper.',
        );
        self::assertStringContainsString(
            '</div><h2>' . "\n" . 'Plain' . "\n" . '</h2>',
            $html,
            'An ordinary heading must stay a bare level-two heading.',
        );
        self::assertStringContainsString(
            '<table class="yii-debug-table yii-debug-table-mono yii-debug-table-overview">',
            PanelRenderer::render('Custom', PanelView::create()->overview(['Key' => 'value'], true)),
            'The compact overview must request the overview table treatment.',
        );
        self::assertStringNotContainsString(
            'yii-debug-table-overview',
            PanelRenderer::render('Custom', PanelView::create()->overview(['Key' => 'value'])),
            'An ordinary overview must not request the compact treatment.',
        );
        self::assertStringContainsString(
            '<td class="yii-debug-cell-pill">' . "\n" . '<span>queued</span>',
            $html,
            'Plain text in a pill column must be wrapped for the reader.',
        );
        self::assertStringContainsString(
            '<td class="yii-debug-cell-pill">' . "\n" . '<span class="yii-debug-badge',
            $html,
            'A badge in a pill column must not be wrapped twice.',
        );
        self::assertStringContainsString(
            '<td>' . "\n" . 'text',
            $html,
            'A column without style must stay class-free.',
        );
        self::assertStringContainsString(
            'tabindex="0"',
            $html,
            'The table region must stay keyboard reachable.',
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

    public function testLinkLabelsAndTargetsAreEscaped(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->paragraph(
                PanelView::link('<script>alert("x")</script>', '/debug?q="><script>alert(1)</script>'),
            ),
        );

        self::assertStringNotContainsString(
            '<script>',
            $html,
            'Neither the label nor the target may inject HTML.',
        );
        self::assertStringContainsString(
            '&lt;script&gt;',
            $html,
            'Escaped link text must remain inspectable.',
        );
    }

    public function testLinksRenderAsAnchorsAndIsolateExternalTargets(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->overview(
                [
                    'Internal' => PanelView::link('View full phpinfo', '/debug/php-info?tag=1&panel=config'),
                    'External' => PanelView::link('Docs', 'https://example.test/docs', true),
                ],
            ),
        );

        self::assertStringContainsString(
            '<a href="/debug/php-info?tag=1&amp;panel=config">View full phpinfo</a>',
            $html,
            'An internal target must stay in the same browsing context.',
        );
        self::assertStringContainsString(
            'rel="noopener"',
            $html,
            'An external target must not leak the opener.',
        );
        self::assertStringContainsString(
            'target="_blank"',
            $html,
            'An external target must open in a new browsing context.',
        );
    }

    public function testManifestGroupsPackagesUnderTheirVendorWithATally(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->manifest(
                'yiisoft/',
                PanelView::package('aliases', 'v3.1.1'),
                PanelView::package('arrays', 'v3.2.1'),
            ),
        );

        self::assertStringContainsString(
            'class="yii-debug-manifest" aria-label="yiisoft/"',
            $html,
            'Manifest must be labelled by its vendor.',
        );
        self::assertStringContainsString(
            '2 packages',
            $html,
            'Tally must be pluralised.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-manifest-version">v3.1.1</span>',
            $html,
            'Version must sit beside its package name.',
        );
    }

    public function testManifestTallyUsesTheSingularForOnePackage(): void
    {
        self::assertStringContainsString(
            '1 package',
            PanelRenderer::render(
                'Custom',
                PanelView::create()->manifest('yiisoft/', PanelView::package('aliases', 'v3.1.1')),
            ),
            'A single package must use the singular tally.',
        );
    }

    public function testPanelsWithoutSummaryMetricsOmitTheSummaryStrip(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->paragraph('Nothing captured.'),
        );

        self::assertStringNotContainsString(
            'yii-debug-grid-summary',
            $html,
            'An empty summary must not leave a bare strip above the content.',
        );
        self::assertStringContainsString(
            '<h1 class="yii-debug-sr-only">',
            $html,
            'The accessible heading must survive an empty summary.',
        );
        self::assertStringContainsString(
            'yii-debug-grid-summary',
            PanelRenderer::render('Custom', PanelView::create()->summary(' items', 1)),
            'A captured metric must still render the summary strip.',
        );
    }

    public function testPillStripRendersEachSubjectWithItsState(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->pills(
                PanelView::pill('APCu', 'on', true),
                PanelView::pill('Memcache', 'off', false),
            ),
        );

        self::assertStringContainsString(
            '<div class="yii-debug-ext-strip">',
            $html,
            'Pills must share one strip.',
        );
        self::assertStringContainsString(
            'yii-debug-ext-pill is-on',
            $html,
            'An enabled subject must read as on.',
        );
        self::assertStringContainsString(
            'yii-debug-ext-pill is-off',
            $html,
            'A disabled subject must read as off.',
        );
    }

    public function testReadoutRowRendersCardsAndDropsAnEmptyCaption(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->readouts(
                PanelView::readout('Yii', '3', 'framework'),
                PanelView::readout('PHP', '8.5.9'),
            ),
        );

        self::assertStringContainsString(
            '<div class="yii-debug-readout-grid">',
            $html,
            'Cards must share one row.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-readout-meta">framework</span>',
            $html,
            'A caption must follow its value.',
        );
        self::assertSame(
            1,
            substr_count($html, 'yii-debug-readout-meta'),
            'A card without a caption must omit the element.',
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

    public function testSectionWithoutATallyOmitsTheCount(): void
    {
        self::assertStringNotContainsString(
            'yii-debug-section-count',
            PanelRenderer::render(
                'Custom',
                PanelView::create()->section('//', 'Application details', PanelView::create()->paragraph('None.')),
            ),
            'A section without a tally must omit the count element.',
        );
    }

    public function testSectionWrapsItsBlocksUnderAMarkedTitle(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->section(
                '::',
                'Installed extensions',
                PanelView::create()->paragraph('Nothing captured.'),
                47,
            ),
        );

        self::assertStringContainsString(
            'class="yii-debug-section" aria-label="Installed extensions"',
            $html,
            'Section must be labelled by its title.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-section-mark">::</span>',
            $html,
            'Mark must precede the title.',
        );
        self::assertStringContainsString(
            '<span class="yii-debug-section-count">47</span>',
            $html,
            'Tally must close the title.',
        );
        self::assertStringContainsString(
            'Nothing captured.',
            $html,
            'Section must render its own blocks.',
        );
    }

    public function testSqlStatementsAreHighlightedAndEscaped(): void
    {
        $html = PanelRenderer::render(
            'Custom',
            PanelView::create()->paragraph(PanelView::sql("SELECT * FROM t WHERE a = '<x>'")),
        );

        self::assertStringContainsString(
            '<span class="yii-debug-sql-kw">SELECT</span>',
            $html,
            'Statements must reuse the shared SQL token spans.',
        );
        self::assertStringNotContainsString(
            "'<x>'",
            $html,
            'A statement literal must never inject HTML.',
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

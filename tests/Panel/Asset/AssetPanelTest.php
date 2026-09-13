<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Asset;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\Asset\{AssetPanel, AssetSnapshot};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * Unit tests for {@see AssetPanel} covering the Vite section, bundle inventory, and per-bundle detail groups.
 *
 * @phpstan-import-type BadgeInline from PanelView
 * @phpstan-import-type Block from PanelView
 * @phpstan-import-type EmptyStateBlock from PanelView
 * @phpstan-import-type GroupBlock from PanelView
 * @phpstan-import-type Inline from PanelView
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type TableBlock from PanelView
 */
#[Group('panel')]
#[Group('asset')]
final class AssetPanelTest extends TestCase
{
    public function testBundleWithoutFilesOrDependenciesStatesItInstead(): void
    {
        $view = self::present([self::bundle(['name' => 'AppAsset', 'css' => [], 'js' => [], 'depends' => []])]);
        $content = self::group(self::blockAt($view, 3));

        self::assertCount(
            2,
            $content['content']->blocks(),
            'A bundle without files or dependencies must keep only its overview and the explanation.',
        );
        self::assertSame(
            'paragraph',
            self::childBlockAt($content, 1)['kind'],
            'A bundle without files must state it instead of rendering an empty table.',
        );
        self::assertSame(
            '—',
            self::textValue(
                self::fields(self::overview(self::childBlockAt($content, 0)))['Namespace']
                    ?? self::fail('The wiring must keep the namespace row.'),
            ),
            'A bundle without namespace must show the placeholder.',
        );
    }

    public function testEmptyCaptureDeactivatesThePanelAndExplainsTheMissingBundles(): void
    {
        $view = self::present([]);

        self::assertFalse(
            $view->isActive(),
            'An empty inventory must not activate navigation.',
        );
        self::assertSame(
            [
                ['label' => ' bundles', 'value' => ['kind' => 'text', 'value' => '0', 'style' => 'strong']],
                ['label' => ' css', 'value' => ['kind' => 'text', 'value' => '0', 'style' => 'strong']],
                ['label' => ' js', 'value' => ['kind' => 'text', 'value' => '0', 'style' => 'strong']],
                ['label' => ' links', 'value' => ['kind' => 'text', 'value' => '0', 'style' => 'strong']],
            ],
            $view->summaryMetrics(),
            'The aggregate counters must stay visible for an empty inventory.',
        );

        $state = self::emptyState(self::blockAt($view, 0));

        self::assertCount(
            1,
            $view->blocks(),
            'An empty inventory must replace the table and the groups.',
        );
        self::assertSame(
            'No asset bundles loaded',
            $state['title'],
            'The empty state must keep its heading.',
        );
        self::assertSame(
            [
                'This request did not register any ',
                'yii\\web\\AssetBundle',
                ' via ',
                'register()',
                ', so the inventory is empty.',
            ],
            self::inlineValues($state['paragraphs'][0] ?? self::fail('The empty state must explain itself.')),
            'The first paragraph must stay complete and ordered.',
        );
        self::assertSame(
            [
                'Bundles appear here when something in the request actively pulls them in, typically a layout or ',
                'view that calls a bundle\'s ',
                'register()',
                ', or any bundle reached transitively through the ',
                'depends',
                ' chain.',
            ],
            self::inlineValues($state['paragraphs'][1] ?? self::fail('The empty state must explain the chain.')),
            'The second paragraph must stay complete and ordered.',
        );
    }

    public function testMetadataMatchesTheBuiltInAssetPanel(): void
    {
        $panel = new AssetPanel();

        self::assertSame(
            'asset',
            $panel->id(),
            'The persisted panel identifier must stay stable.',
        );
        self::assertSame(
            'Asset Bundles',
            $panel->name(),
            'The navigation title must stay stable.',
        );
        self::assertSame(
            'asset',
            $panel->icon(),
            'The panel must reuse the existing icon.',
        );
    }

    public function testRegisteredBundlesProduceTheInventoryAndTheirDetailGroups(): void
    {
        $view = self::present(
            [
                self::bundle(),
                self::bundle(
                    [
                        'name' => 'app\\assets\\SecondAsset',
                        'sourcePath' => '',
                        'basePath' => '',
                        'baseUrl' => '',
                        'css' => [],
                        'js' => ['second.js'],
                        'depends' => [],
                    ],
                ),
            ],
        );

        self::assertTrue(
            $view->isActive(),
            'A captured bundle must activate navigation.',
        );
        self::assertSame(
            [
                ['label' => ' bundles', 'value' => ['kind' => 'text', 'value' => '2', 'style' => 'strong']],
                ['label' => ' css', 'value' => ['kind' => 'text', 'value' => '1', 'style' => 'strong']],
                ['label' => ' js', 'value' => ['kind' => 'text', 'value' => '3', 'style' => 'strong']],
                ['label' => ' link', 'value' => ['kind' => 'text', 'value' => '1', 'style' => 'strong']],
            ],
            $view->summaryMetrics(),
            'The aggregate counters must total every bundle.',
        );
        self::assertSame(
            [['label' => 'Bundles', 'value' => ['kind' => 'text', 'value' => '2', 'style' => 'plain']]],
            $view->toolbarMetrics(),
            'The toolbar must report the bundle count.',
        );

        $heading = self::heading(self::blockAt($view, 0));

        self::assertSame(
            'Registered bundles',
            $heading['title'],
            'The inventory must keep its heading.',
        );
        self::assertTrue(
            $heading['section'],
            'The inventory must open a section-level heading.',
        );

        $table = self::table(self::blockAt($view, 1));

        self::assertSame(
            ['#', 'Bundle', 'CSS', 'JS', 'Depends'],
            $table['headers'],
            'The inventory column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::IDENTIFIER,
                2 => ColumnStyle::NUMBER,
                3 => ColumnStyle::NUMBER,
                4 => ColumnStyle::NUMBER,
            ],
            $table['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $table['collapsible'],
            'A long inventory must stay collapsible.',
        );
        self::assertSame(
            ['1', 'app\\assets\\AppAsset', '1', '2', '1'],
            self::textValues($table['rows'][0] ?? self::fail('The inventory must list every bundle.')),
            'The inventory row must count the declared files and dependencies.',
        );
        self::assertCount(
            2,
            $table['rows'],
            'The inventory must list every bundle.',
        );
        self::assertSame(
            ['2', 'app\\assets\\SecondAsset', '0', '1', '0'],
            self::textValues($table['rows'][1] ?? self::fail('The inventory must list every bundle.')),
            'The inventory must keep every bundle in registration order.',
        );

        $bundleHeading = self::heading(self::blockAt($view, 2));

        self::assertSame(
            '1. AppAsset',
            $bundleHeading['title'],
            'Headings must number the bundles and use their short name.',
        );
        self::assertTrue(
            $bundleHeading['section'],
            'Each bundle must open a section-level heading.',
        );

        $content = self::group(self::blockAt($view, 3));

        self::assertSame(
            'app\\assets\\AppAsset',
            $content['label'],
            'The group must identify the bundle it describes.',
        );

        $overview = self::overview(self::childBlockAt($content, 0));

        self::assertTrue(
            $overview['compact'],
            'The wiring must use the compact presentation.',
        );
        self::assertSame(
            ['Class', 'Namespace', 'Source path', 'Base path', 'Base URL'],
            array_keys(self::fields($overview)),
            'The wiring row order must stay stable.',
        );

        $files = self::table(self::childBlockAt($content, 1));

        self::assertSame(
            ['Type', 'File'],
            $files['headers'],
            'The file column order must stay stable.',
        );
        self::assertSame(
            [0 => ColumnStyle::PILL, 1 => ColumnStyle::MONOSPACE],
            $files['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $files['collapsible'],
            'A long file list must stay collapsible.',
        );
        $stylesheet = self::badge(self::firstCell($files, 0));
        $script = self::badge(self::firstCell($files, 1));

        self::assertSame(
            ['css', 'js', 'js'],
            [$stylesheet['label'], $script['label'], self::badge(self::firstCell($files, 2))['label']],
            'Stylesheets must be listed before scripts.',
        );
        self::assertSame(
            Tone::INFO,
            $stylesheet['tone'],
            'Stylesheets and scripts must stay visually distinct.',
        );
        self::assertSame(
            Tone::WARNING,
            $script['tone'],
            'Stylesheets and scripts must stay visually distinct.',
        );

        $depends = self::table(self::childBlockAt($content, 2));

        self::assertSame(
            ['Depends on'],
            $depends['headers'],
            'Dependencies must keep their own table.',
        );
        self::assertSame(
            [0 => ColumnStyle::IDENTIFIER],
            $depends['styles'],
            'Dependency class names must stay on one line.',
        );
        self::assertTrue(
            $depends['collapsible'],
            'A long dependency list must stay collapsible.',
        );
        self::assertSame(
            [['yii\\web\\YiiAsset']],
            [self::textValues($depends['rows'][0] ?? self::fail('Every dependency must be listed.'))],
            'Every declared dependency must survive the migration.',
        );

        $second = self::group(self::blockAt($view, 5));

        self::assertSame(
            '—',
            self::textValue(
                self::fields(self::overview(self::childBlockAt($second, 0)))['Base URL']
                    ?? self::fail('The wiring must keep the base URL row.'),
            ),
            'An unpublished bundle must show the placeholder.',
        );
    }

    public function testViteBridgeIsDescribedBeforeTheBundles(): void
    {
        $view = self::present(
            [],
            [
                'baseUrl' => '/build/',
                'devMode' => false,
                'devServerUrl' => null,
                'manifestPath' => '/app/public/build/manifest.json',
                'chunks' => [
                    [
                        'name' => 'resources/js/app.js',
                        'file' => 'assets/app-1a2b.js',
                        'cssCount' => 2,
                        'imports' => 1,
                        'isEntry' => true,
                    ],
                    [
                        'name' => 'resources/js/vendor.js',
                        'file' => '',
                        'cssCount' => 0,
                        'imports' => 0,
                        'isEntry' => false,
                    ],
                ],
            ],
        );

        self::assertTrue(
            $view->isActive(),
            'A captured Vite bridge must activate navigation even without bundles.',
        );

        $viteHeading = self::heading(self::blockAt($view, 0));

        self::assertSame(
            'Vite',
            $viteHeading['title'],
            'The Vite section must come first.',
        );
        self::assertTrue(
            $viteHeading['section'],
            'The Vite section must open a section-level heading.',
        );

        $bridge = self::overview(self::blockAt($view, 1));

        self::assertTrue(
            $bridge['compact'],
            'The bridge configuration must use the compact presentation.',
        );
        self::assertSame(
            ['Mode' => 'Build manifest', 'Base URL' => '/build/', 'Manifest' => '/app/public/build/manifest.json'],
            self::textFields($bridge),
            'The bridge configuration must survive the migration.',
        );

        $chunks = self::table(self::blockAt($view, 2));

        self::assertSame(
            ['#', 'Chunk', 'Output', 'CSS', 'Imports', 'Entry'],
            $chunks['headers'],
            'The chunk column order must stay stable.',
        );
        self::assertSame(
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::MONOSPACE,
                2 => ColumnStyle::MONOSPACE,
                3 => ColumnStyle::NUMBER,
                4 => ColumnStyle::NUMBER,
                5 => ColumnStyle::PILL,
            ],
            $chunks['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $chunks['collapsible'],
            'A long chunk list must stay collapsible.',
        );
        self::assertSame(
            'entry',
            self::badge($chunks['rows'][0][5] ?? self::fail('Every chunk must report its entry state.'))['label'],
            'An entry chunk must be badged.',
        );
        self::assertSame(
            ['2', 'resources/js/vendor.js', '—', '0', '0', '—'],
            self::textValues($chunks['rows'][1] ?? self::fail('Every chunk must be listed.')),
            'A chunk without output must show the placeholder.',
        );
    }

    public function testViteBuildWithoutChunksExplainsTheMissingManifest(): void
    {
        $view = self::present([], self::vite(['devMode' => false]));

        self::assertSame(
            'paragraph',
            self::blockAt($view, 2)['kind'],
            'A build without chunks must explain the missing manifest.',
        );
    }

    public function testViteDevServerModeNeedsNoManifestExplanation(): void
    {
        $view = self::present([], self::vite(['devMode' => true, 'devServerUrl' => 'http://localhost:5173']));

        self::assertSame(
            'Dev server (http://localhost:5173)',
            self::textFields(self::overview(self::blockAt($view, 1)))['Mode'] ?? null,
            'The dev server URL must stay visible.',
        );
        self::assertSame(
            'emptyState',
            self::blockAt($view, 2)['kind'],
            'A dev server without chunks must not explain a missing manifest.',
        );
    }

    public function testViteDevServerWithoutUrlKeepsTheBareMode(): void
    {
        $view = self::present([], self::vite(['devMode' => true]));

        self::assertSame(
            'Dev server',
            self::textFields(self::overview(self::blockAt($view, 1)))['Mode'] ?? null,
            'A dev server without URL must keep the bare mode label.',
        );
    }

    /**
     * @param Inline $inline Cell value to narrow.
     *
     * @return BadgeInline Narrowed badge.
     */
    private static function badge(array $inline): array
    {
        return match ($inline['kind']) {
            'badge' => $inline,
            default => self::fail('The value must be a badge.'),
        };
    }

    /**
     * @param PanelView $view View to read.
     * @param int $index Position of the block in display order.
     *
     * @return Block Block declared at the requested position.
     */
    private static function blockAt(PanelView $view, int $index): array
    {
        return $view->blocks()[$index] ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * Builds a capture payload for one bundle, overriding the requested fields.
     *
     * @param array<string, mixed> $overrides Capture fields replacing the defaults.
     *
     * @return array<string, mixed> Captured bundle.
     */
    private static function bundle(array $overrides = []): array
    {
        return [
            'name' => 'app\\assets\\AppAsset',
            'sourcePath' => '@app/assets',
            'basePath' => '@webroot/assets/1a2b',
            'baseUrl' => '/assets/1a2b',
            'css' => ['css/site.css'],
            'js' => ['js/app.js', 'js/extra.js'],
            'depends' => ['yii\\web\\YiiAsset'],
            ...$overrides,
        ];
    }

    /**
     * @param GroupBlock $block Group whose child view is read.
     * @param int $index Position of the block inside the group.
     *
     * @return Block Block declared at the requested position.
     */
    private static function childBlockAt(array $block, int $index): array
    {
        return $block['content']->blocks()[$index]
            ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return EmptyStateBlock Narrowed empty state.
     */
    private static function emptyState(array $block): array
    {
        return match ($block['kind']) {
            'emptyState' => $block,
            default => self::fail('An empty inventory must be explained by an empty state.'),
        };
    }

    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, Inline> Field values keyed by their label, in display order.
     */
    private static function fields(array $block): array
    {
        $fields = [];

        foreach ($block['fields'] as $field) {
            $fields[$field['label']] = $field['value'];
        }

        return $fields;
    }

    /**
     * @param TableBlock $block Table to read.
     * @param int $row Position of the row in display order.
     *
     * @return Inline First cell of the requested row.
     */
    private static function firstCell(array $block, int $row): array
    {
        $cells = $block['rows'][$row] ?? self::fail('Every declared file must be listed.');

        return $cells[0] ?? self::fail('Every row must keep its first column.');
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return GroupBlock Narrowed bundle group.
     */
    private static function group(array $block): array
    {
        return match ($block['kind']) {
            'group' => $block,
            default => self::fail('Each bundle must have an accessible group.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return array{kind: 'heading', title: string, section: bool} Narrowed section heading.
     */
    private static function heading(array $block): array
    {
        return match ($block['kind']) {
            'heading' => $block,
            default => self::fail('Each section must have a visible heading.'),
        };
    }

    /**
     * @param ParagraphBlock $block Paragraph whose inline content is read.
     *
     * @return list<string> Text carried by each inline value, in display order.
     */
    private static function inlineValues(array $block): array
    {
        $values = [];

        foreach ($block['content'] as $inline) {
            $values[] = self::textValue($inline);
        }

        return $values;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return OverviewBlock Narrowed overview.
     */
    private static function overview(array $block): array
    {
        return match ($block['kind']) {
            'overview' => $block,
            default => self::fail('Each bundle must keep an inspectable overview.'),
        };
    }

    /**
     * Presents the given capture through the panel under test.
     *
     * @param list<array<string, mixed>> $bundles Captured bundles in registration order.
     * @param array<string, mixed>|null $vite Captured Vite bridge snapshot, or `null` when absent.
     *
     * @return PanelView Description built by the panel.
     */
    private static function present(array $bundles, array|null $vite = null): PanelView
    {
        return (new AssetPanel())->present(
            AssetSnapshot::fromArray(['bundles' => $bundles, 'vite' => $vite], '$')->jsonSerialize(),
        );
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return TableBlock Narrowed table.
     */
    private static function table(array $block): array
    {
        return match ($block['kind']) {
            'table' => $block,
            default => self::fail('The inventory must use the shared table contract.'),
        };
    }

    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, string> Field text keyed by label, in display order.
     */
    private static function textFields(array $block): array
    {
        $fields = [];

        foreach ($block['fields'] as $field) {
            $fields[$field['label']] = self::textValue($field['value']);
        }

        return $fields;
    }

    /**
     * @param Inline $inline Cell or field value to read.
     *
     * @return string Text carried by the value.
     */
    private static function textValue(array $inline): string
    {
        return match ($inline['kind']) {
            'text' => $inline['value'],
            default => self::fail('The value must be plain text.'),
        };
    }

    /**
     * @param list<Inline> $row Row cells in display order.
     *
     * @return list<string> Cell text in display order.
     */
    private static function textValues(array $row): array
    {
        $values = [];

        foreach ($row as $cell) {
            $values[] = self::textValue($cell);
        }

        return $values;
    }

    /**
     * Builds a Vite capture without chunks, overriding the requested fields.
     *
     * @param array<string, mixed> $overrides Capture fields replacing the defaults.
     *
     * @return array<string, mixed> Captured Vite bridge snapshot.
     */
    private static function vite(array $overrides = []): array
    {
        return [
            'baseUrl' => '',
            'devMode' => false,
            'devServerUrl' => null,
            'manifestPath' => '',
            'chunks' => [],
            ...$overrides,
        ];
    }
}

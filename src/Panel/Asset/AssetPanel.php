<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};
use PHPForge\Debug\Helper\Fqcn;

use function count;
use function sprintf;

/**
 * Presents the registered asset bundles as an inventory table with one detail group per bundle.
 *
 * The optional Vite bridge snapshot is described first, because it governs how the bundles resolve their URLs.
 */
final class AssetPanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Asset Bundles navigation entry.
     */
    protected const string ICON = 'asset';

    /**
     * @var string Stable identifier associating the panel with the captured asset payload.
     */
    protected const string ID = 'asset';

    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = 'Asset Bundles';

    /**
     * @var string Placeholder shown wherever the capture left a field empty.
     */
    private const string PLACEHOLDER = '—';

    /**
     * Builds the panel view from the decoded asset capture.
     *
     * @param array<string, mixed> $data Decoded panel payload with `bundles` and `vite` keys.
     *
     * @return PanelView Vite overview, bundle inventory, and per-bundle detail groups.
     */
    public function present(array $data): PanelView
    {
        $snapshot = AssetSnapshot::fromArray(
            $data,
            '$.asset',
        );

        $bundles = $snapshot->bundles();
        $vite = $snapshot->vite();

        $count = count($bundles);

        $css = 0;
        $js = 0;
        $depends = 0;

        foreach ($bundles as $bundle) {
            $css += count($bundle->css);
            $js += count($bundle->js);
            $depends += count($bundle->depends);
        }

        $view = PanelView::create()
            ->active($count > 0 || $vite !== null)
            ->summary($count === 1 ? ' bundle' : ' bundles', $count)
            ->summary(' css', $css)
            ->summary(' js', $js)
            ->summary($depends === 1 ? ' link' : ' links', $depends)
            ->toolbar('Bundles', $count);

        if ($vite !== null) {
            $view = self::vite($view, $vite);
        }

        if ($count === 0) {
            return $view->emptyState(
                'No asset bundles loaded',
                [
                    'This request did not register any ',
                    PanelView::code('yii\\web\\AssetBundle'),
                    ' via ',
                    PanelView::code('register()'),
                    ', so the inventory is empty.',
                ],
                [
                    'Bundles appear here when something in the request actively pulls them in, typically a layout or ',
                    'view that calls a bundle\'s ',
                    PanelView::code('register()'),
                    ', or any bundle reached transitively through the ',
                    PanelView::code('depends'),
                    ' chain.',
                ],
            );
        }

        $view = $view
            ->heading('Registered bundles', true)
            ->table(
                ['#', 'Bundle', 'CSS', 'JS', 'Depends'],
                self::inventory($bundles),
                true,
                [
                    0 => ColumnStyle::NUMBER,
                    1 => ColumnStyle::IDENTIFIER,
                    2 => ColumnStyle::NUMBER,
                    3 => ColumnStyle::NUMBER,
                    4 => ColumnStyle::NUMBER,
                ],
            );

        foreach ($bundles as $index => $bundle) {
            $view = $view
                ->heading(sprintf('%d. %s', $index + 1, Fqcn::shortName($bundle->name)), true)
                ->group($bundle->name, self::detail($bundle));
        }

        return $view;
    }

    /**
     * Builds the detail group of one bundle: wiring overview, declared files, and dependencies.
     *
     * @param AssetBundleRow $bundle Captured bundle to describe.
     *
     * @return PanelView Child view holding only the detail blocks of the bundle.
     */
    private static function detail(AssetBundleRow $bundle): PanelView
    {
        $namespace = Fqcn::namespacePart($bundle->name);

        $view = PanelView::create()->overview(
            [
                'Class' => PanelView::code($bundle->name),
                'Namespace' => $namespace === '' ? self::PLACEHOLDER : $namespace,
                'Source path' => self::orPlaceholder($bundle->sourcePath),
                'Base path' => self::orPlaceholder($bundle->basePath),
                'Base URL' => self::orPlaceholder($bundle->baseUrl),
            ],
            true,
        );

        $files = [];

        foreach ($bundle->css as $file) {
            $files[] = [PanelView::badge('css', Tone::INFO), $file];
        }

        foreach ($bundle->js as $file) {
            $files[] = [PanelView::badge('js', Tone::WARNING), $file];
        }

        $view = $files === []
            ? $view->paragraph('This bundle declares no CSS or JavaScript files.')
            : $view->table(
                ['Type', 'File'],
                $files,
                true,
                [
                    0 => ColumnStyle::PILL,
                    1 => ColumnStyle::MONOSPACE,
                ],
            );

        if ($bundle->depends === []) {
            return $view;
        }

        $rows = [];

        foreach ($bundle->depends as $depend) {
            $rows[] = [$depend];
        }

        return $view->table(['Depends on'], $rows, true, [0 => ColumnStyle::IDENTIFIER]);
    }

    /**
     * Builds the inventory table rows in registration order.
     *
     * @param list<AssetBundleRow> $bundles Captured bundles in registration order.
     *
     * @return list<list<mixed>> One row per bundle, matching the declared column order.
     */
    private static function inventory(array $bundles): array
    {
        $rows = [];

        foreach ($bundles as $index => $bundle) {
            $rows[] = [
                $index + 1,
                $bundle->name,
                count($bundle->css),
                count($bundle->js),
                count($bundle->depends),
            ];
        }

        return $rows;
    }

    /**
     * Returns the value, or the placeholder when the capture left it empty.
     *
     * @param string $value Captured value.
     *
     * @return string Captured value, or the placeholder when empty.
     */
    private static function orPlaceholder(string $value): string
    {
        return $value === '' ? self::PLACEHOLDER : $value;
    }

    /**
     * Appends the Vite bridge overview and its build-manifest chunks.
     *
     * @param PanelView $view View to extend.
     * @param ViteManifest $vite Captured Vite bridge snapshot.
     *
     * @return PanelView View completed with the Vite section.
     */
    private static function vite(PanelView $view, ViteManifest $vite): PanelView
    {
        $server = $vite->devServerUrl;

        $mode = match (true) {
            $vite->devMode && $server !== null => "Dev server ({$server})",
            $vite->devMode => 'Dev server',
            default => 'Build manifest',
        };

        $view = $view
            ->heading('Vite', true)
            ->overview(
                [
                    'Mode' => $mode,
                    'Base URL' => self::orPlaceholder($vite->baseUrl),
                    'Manifest' => self::orPlaceholder($vite->manifestPath),
                ],
                true,
            );

        if ($vite->chunks === []) {
            return $vite->devMode
                ? $view
                : $view->paragraph('The Vite manifest is missing or empty; run the front-end build to populate it.');
        }

        $rows = [];

        foreach ($vite->chunks as $index => $chunk) {
            $rows[] = [
                $index + 1,
                PanelView::strong($chunk->name),
                self::orPlaceholder($chunk->file),
                $chunk->cssCount,
                $chunk->imports,
                $chunk->isEntry ? PanelView::badge('entry', Tone::SUCCESS) : self::PLACEHOLDER,
            ];
        }

        return $view->table(
            ['#', 'Chunk', 'Output', 'CSS', 'Imports', 'Entry'],
            $rows,
            true,
            [
                0 => ColumnStyle::NUMBER,
                1 => ColumnStyle::MONOSPACE,
                2 => ColumnStyle::MONOSPACE,
                3 => ColumnStyle::NUMBER,
                4 => ColumnStyle::NUMBER,
                5 => ColumnStyle::PILL,
            ],
        );
    }
}

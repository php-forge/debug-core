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
    protected const string ICON = AssetMessage::ID->value;
    /**
     * @var string Stable identifier associating the panel with the captured asset payload.
     */
    protected const string ID = AssetMessage::ID->value;
    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = AssetMessage::TITLE->value;

    /**
     * Builds the panel view from the decoded asset capture.
     *
     * @param array<string, mixed> $data Decoded panel payload with `bundles` and `vite` keys.
     *
     * @return PanelView Vite overview, bundle inventory, and per-bundle detail groups.
     */
    public function present(array $data): PanelView
    {
        $snapshot = AssetSnapshot::fromArray($data, '$.asset');

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
            ->summary(
                $count === 1 ? AssetMessage::BUNDLE_SUFFIX->value : AssetMessage::BUNDLES_SUFFIX->value,
                $count,
            )
            ->summary(AssetMessage::CSS_SUFFIX->value, $css)
            ->summary(AssetMessage::JS_SUFFIX->value, $js)
            ->summary(
                $depends === 1 ? AssetMessage::LINK_SUFFIX->value : AssetMessage::LINKS_SUFFIX->value,
                $depends,
            )
            ->toolbar(AssetMessage::TOOLBAR->value, $count);

        if ($vite !== null) {
            $view = self::vite($view, $vite);
        }

        if ($count === 0) {
            return $view->emptyState(
                AssetMessage::EMPTY_HEADLINE->value,
                [
                    AssetMessage::EMPTY_REGISTER->value,
                    PanelView::code(AssetMessage::BUNDLE_CLASS->value),
                    AssetMessage::EMPTY_VIA->value,
                    PanelView::code(AssetMessage::REGISTER_CALL->value),
                    AssetMessage::EMPTY_INVENTORY->value,
                ],
                [
                    AssetMessage::EMPTY_TRIGGER->value,
                    AssetMessage::EMPTY_TRIGGER_CALL->value,
                    PanelView::code(AssetMessage::REGISTER_CALL->value),
                    AssetMessage::EMPTY_TRANSITIVE_REACH->value,
                    PanelView::code(AssetMessage::DEPENDS_PROPERTY->value),
                    AssetMessage::EMPTY_TRANSITIVE->value,
                ],
            );
        }

        $view = $view
            ->heading(AssetMessage::REGISTERED->value, true)
            ->table(
                [
                    AssetMessage::NUMBER->value,
                    AssetMessage::BUNDLE->value,
                    AssetMessage::CSS->value,
                    AssetMessage::JS->value,
                    AssetMessage::DEPENDS->value,
                ],
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
                ->heading(
                    sprintf(AssetMessage::BUNDLE_HEADING->value, $index + 1, Fqcn::shortName($bundle->name)),
                    true,
                )
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
                AssetMessage::CLASS_NAME->value => PanelView::code($bundle->name),
                AssetMessage::NAMESPACE_PART->value => $namespace === ''
                    ? AssetMessage::PLACEHOLDER->value
                    : $namespace,
                AssetMessage::SOURCE_PATH->value => self::orPlaceholder($bundle->sourcePath),
                AssetMessage::BASE_PATH->value => self::orPlaceholder($bundle->basePath),
                AssetMessage::BASE_URL->value => self::orPlaceholder($bundle->baseUrl),
            ],
            true,
        );

        $files = [];

        foreach ($bundle->css as $file) {
            $files[] = [PanelView::badge(AssetMessage::CSS_BADGE->value, Tone::INFO), $file];
        }

        foreach ($bundle->js as $file) {
            $files[] = [PanelView::badge(AssetMessage::JS_BADGE->value, Tone::WARNING), $file];
        }

        $view = $files === []
            ? $view->paragraph(AssetMessage::NO_FILES->value)
            : $view->table(
                [AssetMessage::TYPE->value, AssetMessage::FILE->value],
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

        return $view->table([AssetMessage::DEPENDS_ON->value], $rows, true, [0 => ColumnStyle::IDENTIFIER]);
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
        return $value === '' ? AssetMessage::PLACEHOLDER->value : $value;
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
            $vite->devMode && $server !== null => sprintf(AssetMessage::MODE_DEV_SERVER->value, $server),
            $vite->devMode => AssetMessage::MODE_DEV->value,
            default => AssetMessage::MODE_BUILD->value,
        };

        $view = $view
            ->heading(AssetMessage::VITE->value, true)
            ->overview(
                [
                    AssetMessage::MODE->value => $mode,
                    AssetMessage::BASE_URL->value => self::orPlaceholder($vite->baseUrl),
                    AssetMessage::MANIFEST->value => self::orPlaceholder($vite->manifestPath),
                ],
                true,
            );

        if ($vite->chunks === []) {
            return $vite->devMode ? $view : $view->paragraph(AssetMessage::VITE_EMPTY->value);
        }

        $rows = [];

        foreach ($vite->chunks as $index => $chunk) {
            $rows[] = [
                $index + 1,
                PanelView::strong($chunk->name),
                self::orPlaceholder($chunk->file),
                $chunk->cssCount,
                $chunk->imports,
                $chunk->isEntry
                    ? PanelView::badge(AssetMessage::ENTRY_BADGE->value, Tone::SUCCESS)
                    : AssetMessage::PLACEHOLDER->value,
            ];
        }

        return $view->table(
            [
                AssetMessage::NUMBER->value,
                AssetMessage::CHUNK->value,
                AssetMessage::OUTPUT->value,
                AssetMessage::CSS->value,
                AssetMessage::IMPORTS->value,
                AssetMessage::ENTRY->value,
            ],
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

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};
use PHPForge\Debug\Helper\{Fqcn, Text};
use PHPForge\Debug\Presenter\{FileEntry, LinkInline};

use function array_map;
use function count;
use function sprintf;

/**
 * Presents the registered asset bundles as a strip of headline statistics and one card per bundle.
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
     * @return PanelView Aggregate statistics, Vite overview, and one card per registered bundle.
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
            ->toolbar(AssetMessage::TOOLBAR->value, $count)
            ->stats(
                PanelView::stat(
                    AssetMessage::ID->value,
                    $count === 1 ? AssetMessage::BUNDLE->value : AssetMessage::BUNDLES->value,
                    (string) $count,
                ),
                PanelView::stat(
                    AssetMessage::CSS_ICON->value,
                    AssetMessage::CSS_LABEL->value,
                    (string) $css,
                    Tone::INFO,
                ),
                PanelView::stat(
                    AssetMessage::JS_ICON->value,
                    AssetMessage::JS_LABEL->value,
                    (string) $js,
                    Tone::WARNING,
                ),
                PanelView::stat(
                    AssetMessage::LINK->value,
                    $depends === 1 ? AssetMessage::LINK->value : AssetMessage::LINKS->value,
                    (string) $depends,
                    Tone::SUCCESS,
                ),
            );

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

        foreach ($bundles as $bundle) {
            $view = self::card($view, $bundle);
        }

        return $view;
    }

    /**
     * Appends one bundle as a card: identity in the header, declared files and wiring in its columns.
     *
     * @param PanelView $view View to extend.
     * @param AssetBundleRow $bundle Captured bundle to describe.
     *
     * @return PanelView View completed with the bundle card.
     */
    private static function card(PanelView $view, AssetBundleRow $bundle): PanelView
    {
        $cssCount = count($bundle->css);
        $jsCount = count($bundle->js);
        $dependsCount = count($bundle->depends);

        $meta = [];

        if ($cssCount > 0) {
            $meta[] = PanelView::badge(sprintf(AssetMessage::CSS_CHIP->value, $cssCount), Tone::INFO);
        }

        if ($jsCount > 0) {
            $meta[] = PanelView::badge(sprintf(AssetMessage::JS_CHIP->value, $jsCount), Tone::WARNING);
        }

        if ($dependsCount > 0) {
            $meta[] = PanelView::badge(
                sprintf(
                    $dependsCount === 1 ? AssetMessage::DEP_CHIP->value : AssetMessage::DEPS_CHIP->value,
                    $dependsCount,
                ),
                Tone::SUCCESS,
            );
        }

        $columns = [];

        if ($cssCount + $jsCount > 0) {
            $columns[] = PanelView::column(AssetMessage::FILES->value, self::files($bundle));
        }

        $wiring = self::wiring($bundle);

        if ($wiring !== null) {
            $columns[] = PanelView::column(AssetMessage::WIRING->value, $wiring);
        }

        $namespace = Fqcn::namespacePart($bundle->name);

        return $view->card(
            Text::camel2id($bundle->name),
            AssetMessage::ID->value,
            Fqcn::shortName($bundle->name),
            $namespace === '' ? '' : "{$namespace}\\",
            $meta,
            ...$columns,
        );
    }

    /**
     * Builds the `Files` column, keeping the stylesheets in their own list ahead of the scripts.
     *
     * @param AssetBundleRow $bundle Captured bundle to describe.
     *
     * @return PanelView Child view holding only the file lists of the bundle.
     */
    private static function files(AssetBundleRow $bundle): PanelView
    {
        $view = PanelView::create();

        if ($bundle->css !== []) {
            $view = $view->files(
                ...array_map(
                    static fn(string $file): FileEntry => PanelView::file(
                        AssetMessage::CSS_TYPE->value,
                        $file,
                        Tone::INFO,
                    ),
                    $bundle->css,
                ),
            );
        }

        if ($bundle->js === []) {
            return $view;
        }

        return $view->files(
            ...array_map(
                static fn(string $file): FileEntry => PanelView::file(
                    AssetMessage::JS_TYPE->value,
                    $file,
                    Tone::WARNING,
                ),
                $bundle->js,
            ),
        );
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

    /**
     * Builds the `Wiring` column from the paths the bundle publishes and the bundles it pulls in.
     *
     * @param AssetBundleRow $bundle Captured bundle to describe.
     *
     * @return PanelView|null Child view holding only the wiring blocks, or `null` when the capture left them all
     * empty.
     */
    private static function wiring(AssetBundleRow $bundle): PanelView|null
    {
        $facts = [];

        if ($bundle->sourcePath !== '') {
            $facts[] = PanelView::fact(AssetMessage::SOURCE->value, $bundle->sourcePath);
        }

        if ($bundle->basePath !== '') {
            $facts[] = PanelView::fact(AssetMessage::BASE->value, $bundle->basePath);
        }

        if ($bundle->baseUrl !== '') {
            $facts[] = PanelView::fact(AssetMessage::URL->value, $bundle->baseUrl);
        }

        if ($facts === [] && $bundle->depends === []) {
            return null;
        }

        $view = PanelView::create();

        if ($facts !== []) {
            $view = $view->facts(...$facts);
        }

        if ($bundle->depends === []) {
            return $view;
        }

        return $view->links(
            sprintf(AssetMessage::DEPENDS_LABEL->value, count($bundle->depends)),
            ...array_map(
                static fn(string $depend): LinkInline => PanelView::link(
                    Fqcn::shortName($depend),
                    '#' . Text::camel2id($depend),
                ),
                $bundle->depends,
            ),
        );
    }
}

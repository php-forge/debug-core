<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

/**
 * Identity and presentation text of the Asset Bundles panel, shared by every adapter that renders it.
 */
enum AssetMessage: string
{
    /**
     * Wiring fact label of the path the bundle publishes into.
     */
    case BASE = 'base';

    /**
     * Detail field label of the base URL configured on the Vite bridge.
     */
    case BASE_URL = 'Base URL';

    /**
     * Stat label of a single registered bundle.
     */
    case BUNDLE = 'bundle';

    /**
     * Asset bundle base class named in the empty state.
     */
    case BUNDLE_CLASS = 'yii\web\AssetBundle';

    /**
     * Stat label of the registered bundle count.
     */
    case BUNDLES = 'bundles';

    /**
     * Header of the manifest chunk column.
     */
    case CHUNK = 'Chunk';

    /**
     * Header of the stylesheet count column in the manifest table.
     */
    case CSS = 'CSS';

    /**
     * `sprintf()` template of the stylesheet chip in a bundle card header.
     */
    case CSS_CHIP = '%d css';

    /**
     * Icon key of the stylesheet stat tile.
     */
    case CSS_ICON = 'brand-css3';

    /**
     * Stat label of the declared stylesheet count.
     */
    case CSS_LABEL = 'css';

    /**
     * Kind pill marking a declared stylesheet in a bundle file list.
     */
    case CSS_TYPE = '.css';

    /**
     * `sprintf()` template of the chip declaring a single dependency in a bundle card header.
     */
    case DEP_CHIP = '%d dep';

    /**
     * `sprintf()` template of the label introducing the dependency link strip.
     */
    case DEPENDS_LABEL = 'Depends on %d';

    /**
     * Bundle property named in the empty state, which pulls bundles in transitively.
     */
    case DEPENDS_PROPERTY = 'depends';

    /**
     * `sprintf()` template of the dependency chip in a bundle card header.
     */
    case DEPS_CHIP = '%d deps';

    /**
     * Headline of the empty state when the request registered no bundle.
     */
    case EMPTY_HEADLINE = 'No asset bundles loaded';

    /**
     * Closing sentence of the first empty-state paragraph.
     */
    case EMPTY_INVENTORY = ', so the inventory is empty.';

    /**
     * Opening sentence of the first empty-state paragraph, preceding the bundle base class.
     */
    case EMPTY_REGISTER = 'This request did not register any ';

    /**
     * Closing sentence of the second empty-state paragraph, following the `depends` property.
     */
    case EMPTY_TRANSITIVE = ' chain.';

    /**
     * Middle sentence of the second empty-state paragraph, preceding the `depends` property.
     */
    case EMPTY_TRANSITIVE_REACH = ', or any bundle reached transitively through the ';

    /**
     * Opening sentence of the second empty-state paragraph, describing what registers a bundle.
     */
    case EMPTY_TRIGGER = 'Bundles appear here when something in the request actively pulls them in, typically a '
        . 'layout or ';

    /**
     * Middle sentence of the second empty-state paragraph, preceding the registration call.
     */
    case EMPTY_TRIGGER_CALL = 'view that calls a bundle\'s ';

    /**
     * Preposition of the first empty-state paragraph, preceding the registration call.
     */
    case EMPTY_VIA = ' via ';

    /**
     * Header of the manifest entry-point column.
     */
    case ENTRY = 'Entry';

    /**
     * Badge marking a manifest chunk declared as an entry point.
     */
    case ENTRY_BADGE = 'entry';

    /**
     * Title of the card column listing the files a bundle declares.
     */
    case FILES = 'Files';

    /**
     * Stable identifier associating the panel with the captured payload, also used as its icon key.
     */
    case ID = 'asset';

    /**
     * Header of the manifest import count column.
     */
    case IMPORTS = 'Imports';

    /**
     * `sprintf()` template of the script chip in a bundle card header.
     */
    case JS_CHIP = '%d js';

    /**
     * Icon key of the script stat tile.
     */
    case JS_ICON = 'brand-javascript';

    /**
     * Stat label of the declared script count.
     */
    case JS_LABEL = 'js';

    /**
     * Kind pill marking a declared script in a bundle file list.
     */
    case JS_TYPE = '.js';

    /**
     * Icon key of the dependency stat tile, also its label for a single link.
     */
    case LINK = 'link';

    /**
     * Stat label of the dependency link count.
     */
    case LINKS = 'links';

    /**
     * Detail field label of the Vite manifest path.
     */
    case MANIFEST = 'Manifest';

    /**
     * Detail field label of the active Vite resolution mode.
     */
    case MODE = 'Mode';

    /**
     * Vite mode resolving URLs through the build manifest.
     */
    case MODE_BUILD = 'Build manifest';

    /**
     * Vite mode resolving URLs through the dev server.
     */
    case MODE_DEV = 'Dev server';

    /**
     * `sprintf()` template of the Vite dev-server mode, naming the configured server URL.
     */
    case MODE_DEV_SERVER = 'Dev server (%s)';

    /**
     * Header of the position column, which sorts by manifest order.
     */
    case NUMBER = '#';

    /**
     * Header of the manifest output file column.
     */
    case OUTPUT = 'Output';

    /**
     * Placeholder shown wherever the capture left a field empty.
     */
    case PLACEHOLDER = '—';

    /**
     * Registration call named in the empty state, which pulls a bundle into the request.
     */
    case REGISTER_CALL = 'register()';

    /**
     * Wiring fact label of the source path declared by the bundle.
     */
    case SOURCE = 'source';

    /**
     * Panel title used in the debugger navigation.
     */
    case TITLE = 'Asset Bundles';

    /**
     * Label of the toolbar metric counting the registered bundles.
     */
    case TOOLBAR = 'Bundles';

    /**
     * Wiring fact label of the URL the bundle publishes under.
     */
    case URL = 'url';

    /**
     * Heading above the Vite bridge overview.
     */
    case VITE = 'Vite';

    /**
     * Note shown when the build manifest carries no chunk outside dev mode.
     */
    case VITE_EMPTY = 'The Vite manifest is missing or empty; run the front-end build to populate it.';

    /**
     * Title of the card column describing how a bundle resolves its files.
     */
    case WIRING = 'Wiring';
}

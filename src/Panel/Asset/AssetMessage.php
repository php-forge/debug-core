<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Asset;

/**
 * Identity and presentation text of the Asset Bundles panel, shared by every adapter that renders it.
 */
enum AssetMessage: string
{
    /**
     * Detail field label of the published base path.
     */
    case BASE_PATH = 'Base path';

    /**
     * Detail field label of the published base URL, also used by the Vite overview.
     */
    case BASE_URL = 'Base URL';

    /**
     * Header of the bundle class column.
     */
    case BUNDLE = 'Bundle';

    /**
     * Asset bundle base class named in the empty state.
     */
    case BUNDLE_CLASS = 'yii\web\AssetBundle';

    /**
     * `sprintf()` template of the heading preceding each detail group.
     */
    case BUNDLE_HEADING = '%d. %s';

    /**
     * Suffix appended to a single registered bundle in the summary header.
     */
    case BUNDLE_SUFFIX = ' bundle';

    /**
     * Suffix appended to the registered bundle count in the summary header.
     */
    case BUNDLES_SUFFIX = ' bundles';

    /**
     * Header of the manifest chunk column.
     */
    case CHUNK = 'Chunk';

    /**
     * Detail field label of the fully qualified bundle class.
     */
    case CLASS_NAME = 'Class';

    /**
     * Header of the stylesheet count column.
     */
    case CSS = 'CSS';

    /**
     * Badge marking a declared stylesheet in the bundle file table.
     */
    case CSS_BADGE = 'css';

    /**
     * Suffix appended to the stylesheet count in the summary header.
     */
    case CSS_SUFFIX = ' css';

    /**
     * Header of the dependency count column.
     */
    case DEPENDS = 'Depends';

    /**
     * Header of the single-column dependency table in a bundle detail.
     */
    case DEPENDS_ON = 'Depends on';

    /**
     * Bundle property named in the empty state, which pulls bundles in transitively.
     */
    case DEPENDS_PROPERTY = 'depends';

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
     * Header of the declared file column in a bundle detail.
     */
    case FILE = 'File';

    /**
     * Stable identifier associating the panel with the captured payload, also used as its icon key.
     */
    case ID = 'asset';

    /**
     * Header of the manifest import count column.
     */
    case IMPORTS = 'Imports';

    /**
     * Header of the script count column.
     */
    case JS = 'JS';

    /**
     * Badge marking a declared script in the bundle file table.
     */
    case JS_BADGE = 'js';

    /**
     * Suffix appended to the script count in the summary header.
     */
    case JS_SUFFIX = ' js';

    /**
     * Suffix appended to a single dependency link in the summary header.
     */
    case LINK_SUFFIX = ' link';

    /**
     * Suffix appended to the dependency link count in the summary header.
     */
    case LINKS_SUFFIX = ' links';

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
     * Detail field label of the bundle namespace.
     */
    case NAMESPACE_PART = 'Namespace';

    /**
     * Note of the detail group when the bundle declares no file.
     */
    case NO_FILES = 'This bundle declares no CSS or JavaScript files.';

    /**
     * Header of the position column, which sorts by registration order.
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
     * Heading above the inventory table.
     */
    case REGISTERED = 'Registered bundles';

    /**
     * Detail field label of the source path published by the bundle.
     */
    case SOURCE_PATH = 'Source path';

    /**
     * Panel title used in the debugger navigation.
     */
    case TITLE = 'Asset Bundles';

    /**
     * Label of the toolbar metric counting the registered bundles.
     */
    case TOOLBAR = 'Bundles';

    /**
     * Header of the file kind column in a bundle detail.
     */
    case TYPE = 'Type';

    /**
     * Heading above the Vite bridge overview.
     */
    case VITE = 'Vite';

    /**
     * Note shown when the build manifest carries no chunk outside dev mode.
     */
    case VITE_EMPTY = 'The Vite manifest is missing or empty; run the front-end build to populate it.';
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

/**
 * Identity and presentation text of the Configuration panel, shared by every adapter that renders it.
 */
enum ConfigMessage: string
{
    /**
     * Overview field label of the application name.
     */
    case APPLICATION = 'Application';

    /**
     * Heading above the charset and language overview.
     */
    case APPLICATION_DETAILS = 'Application details';

    /**
     * Overview field label of the application version.
     */
    case APPLICATION_VERSION = 'Application version';

    /**
     * Overview field label of the application charset.
     */
    case CHARSET = 'Charset';

    /**
     * Overview field label of the active language.
     */
    case CURRENT_LANGUAGE = 'Current language';

    /**
     * Overview field label of the debug flag.
     */
    case DEBUG_MODE = 'Debug mode';

    /**
     * Badge label of a disabled debug flag.
     */
    case DEBUG_OFF = 'off';

    /**
     * Badge label of an enabled debug flag.
     */
    case DEBUG_ON = 'on';

    /**
     * Explanation of the empty state when the capture carried no package roster.
     */
    case EMPTY_EXPLANATION = 'The capture carried no Composer package roster for this request.';

    /**
     * Headline of the empty state when the capture recorded no installed extension.
     */
    case EMPTY_HEADLINE = 'No installed extensions recorded';

    /**
     * Overview field label of the application environment.
     */
    case ENVIRONMENT = 'Environment';

    /**
     * Badge label of a bundled PHP extension reported as loaded.
     */
    case EXTENSION_LOADED = 'loaded';

    /**
     * Badge label of a bundled PHP extension reported as missing.
     */
    case EXTENSION_MISSING = 'missing';

    /**
     * Suffix appended to a single installed extension in the summary header.
     */
    case EXTENSION_SUFFIX = ' extension';

    /**
     * Suffix appended to the installed extension count in the summary header.
     */
    case EXTENSIONS_SUFFIX = ' extensions';

    /**
     * Stable identifier associating the panel with the captured payload, also used as its icon key.
     */
    case ID = 'config';

    /**
     * `sprintf()` template of the heading above the installed-package roster.
     */
    case INSTALLED = 'Installed extensions (%d)';

    /**
     * `sprintf()` template annotating a BCP-47 tag with its English display name.
     */
    case LANGUAGE_ANNOTATION = '%s (%s)';

    /**
     * Label of the APCu extension in the runtime overview.
     */
    case PACKAGE_APCU = 'APCu';

    /**
     * Label of the Memcache extension in the runtime overview.
     */
    case PACKAGE_MEMCACHE = 'Memcache';

    /**
     * Label of the Memcached extension in the runtime overview.
     */
    case PACKAGE_MEMCACHED = 'Memcached';

    /**
     * Header of the package name column.
     */
    case PACKAGE_NAME = 'Package';

    /**
     * Label of the Xdebug extension in the runtime overview.
     */
    case PACKAGE_XDEBUG = 'Xdebug';

    /**
     * Overview field label of the PHP version.
     */
    case PHP = 'PHP';

    /**
     * Heading above the bundled PHP extension overview.
     */
    case PHP_EXTENSIONS = 'PHP extensions';

    /**
     * Label of the link to the adapter-owned phpinfo page.
     */
    case PHP_INFO_LINK = 'View full phpinfo';

    /**
     * `sprintf()` template of the PHP version reported in the summary header.
     */
    case PHP_SUMMARY = 'PHP %s';

    /**
     * Placeholder shown wherever the capture left a field empty.
     */
    case PLACEHOLDER = '—';

    /**
     * Overview field label of the source language.
     */
    case SOURCE_LANGUAGE = 'Source language';

    /**
     * Panel title used in the debugger navigation.
     */
    case TITLE = 'Configuration';

    /**
     * Header of the package version column.
     */
    case VERSION = 'Version';

    /**
     * Overview field label of the framework version.
     */
    case YII = 'Yii';

    /**
     * `sprintf()` template of the framework version reported in the summary header.
     */
    case YII_SUMMARY = 'Yii %s';
}

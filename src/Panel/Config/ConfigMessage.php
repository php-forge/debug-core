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
     * Caption of the readout card reporting the debug flag.
     */
    case CAPTION_DEBUG = 'debug %s';

    /**
     * Caption of the readout card naming the framework.
     */
    case CAPTION_FRAMEWORK = 'framework';

    /**
     * Caption of the readout card naming the application instance.
     */
    case CAPTION_INSTANCE = 'instance';

    /**
     * Caption of the readout card naming the PHP runtime.
     */
    case CAPTION_RUNTIME = 'runtime';

    /**
     * Overview field label of the application charset.
     */
    case CHARSET = 'Charset';

    /**
     * Overview field label of the active language.
     */
    case CURRENT_LANGUAGE = 'Current language';

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
     * Title of the installed-extension section.
     */
    case INSTALLED_TITLE = 'Installed extensions';

    /**
     * `sprintf()` template annotating a BCP-47 tag with its English display name.
     */
    case LANGUAGE_ANNOTATION = '%s (%s)';

    /**
     * Glyph marking a section that continues the identity readouts.
     */
    case MARK_CONTINUATION = '//';

    /**
     * Glyph marking a primary section.
     */
    case MARK_PRIMARY = '::';

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
     * Negative state, shown as the debug-flag caption and on the pill of a PHP extension the runtime did not load.
     */
    case STATE_OFF = 'off';

    /**
     * Positive state, shown as the debug-flag caption and on the pill of a PHP extension the runtime loaded.
     */
    case STATE_ON = 'on';

    /**
     * Panel title used in the debugger navigation.
     */
    case TITLE = 'Configuration';

    /**
     * Overview field label of the framework version.
     */
    case YII = 'Yii';

    /**
     * `sprintf()` template of the framework version reported in the summary header.
     */
    case YII_SUMMARY = 'Yii %s';
}

<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

use Locale;
use PHPForge\Debug\{Panel, PanelView};

use function count;
use function implode;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;
use function strpos;
use function substr;

/**
 * Presents the captured application identity, PHP runtime, and installed-extension roster.
 *
 * Adapters own the phpinfo route, so they supply it with {@see self::phpInfoUrl()} before presenting.
 *
 * @phpstan-import-type BadgeInline from PanelView
 */
final class ConfigPanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Configuration navigation entry.
     */
    protected const string ICON = ConfigMessage::ID->value;
    /**
     * @var string Stable identifier associating the panel with the captured configuration payload.
     */
    protected const string ID = ConfigMessage::ID->value;
    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = ConfigMessage::TITLE->value;

    /**
     * Bundled PHP extensions reported as loaded or missing, keyed by payload field and listed alphabetically by label,
     * matching the installed-extension roster below them.
     *
     * @var array<string, ConfigMessage>
     */
    private const array PHP_EXTENSIONS = [
        'apcu' => ConfigMessage::PACKAGE_APCU,
        'memcache' => ConfigMessage::PACKAGE_MEMCACHE,
        'memcached' => ConfigMessage::PACKAGE_MEMCACHED,
        'xdebug' => ConfigMessage::PACKAGE_XDEBUG,
    ];

    /**
     * @var string Adapter-owned phpinfo URL, or `''` when the adapter exposes no phpinfo page.
     */
    private string $phpInfoUrl = '';

    /**
     * Returns a new instance with the adapter-owned phpinfo URL.
     *
     * @param string $url Adapter-resolved phpinfo URL, or `''` to omit the call to action.
     *
     * @return self New instance with the requested phpinfo URL.
     */
    public function phpInfoUrl(string $url): self
    {
        $new = clone $this;
        $new->phpInfoUrl = $url;

        return $new;
    }

    /**
     * Builds the panel view from the decoded configuration capture.
     *
     * @param array<string, mixed> $data Decoded panel payload with a `data` key holding the tagged configuration.
     *
     * @return PanelView Identity overview, runtime sections, and the installed-extension roster.
     */
    public function present(array $data): PanelView
    {
        $config = ConfigSnapshot::fromArray($data, '$.config')->data();

        $application = self::slice($config, 'application');
        $php = self::slice($config, 'php');
        $extensions = self::extensions($config);

        $count = count($extensions);

        $yii = self::text($application, 'yii');
        $version = self::text($php, 'version');

        $debug = ($application['debug'] ?? false) === true
            ? ConfigMessage::DEBUG_ON->value
            : ConfigMessage::DEBUG_OFF->value;

        $view = PanelView::create()
            ->summary(
                '',
                $yii === ''
                    ? ConfigMessage::PLACEHOLDER->value
                    : sprintf(ConfigMessage::YII_SUMMARY->value, $yii),
            )
            ->summary(
                '',
                $version === ''
                    ? ConfigMessage::PLACEHOLDER->value
                    : sprintf(ConfigMessage::PHP_SUMMARY->value, $version),
                false,
            )
            ->summary(
                $count === 1 ? ConfigMessage::EXTENSION_SUFFIX->value : ConfigMessage::EXTENSIONS_SUFFIX->value,
                $count,
            )
            ->readouts(
                PanelView::readout(
                    ConfigMessage::YII->value,
                    self::orPlaceholder($yii),
                    ConfigMessage::CAPTION_FRAMEWORK->value,
                ),
                PanelView::readout(
                    ConfigMessage::PHP->value,
                    self::orPlaceholder($version),
                    ConfigMessage::CAPTION_RUNTIME->value,
                ),
                PanelView::readout(
                    ConfigMessage::ENVIRONMENT->value,
                    self::orPlaceholder(self::text($application, 'env')),
                    sprintf(ConfigMessage::CAPTION_DEBUG->value, $debug),
                ),
                PanelView::readout(
                    ConfigMessage::APPLICATION->value,
                    self::orPlaceholder(self::text($application, 'name')),
                    ConfigMessage::CAPTION_INSTANCE->value,
                ),
            );

        $pills = [];

        foreach (self::PHP_EXTENSIONS as $key => $label) {
            $loaded = ($php[$key] ?? false) === true;

            $pills[] = PanelView::pill(
                $label->value,
                $loaded ? ConfigMessage::DEBUG_ON->value : ConfigMessage::DEBUG_OFF->value,
                $loaded,
            );
        }

        $view = $view
            ->section(
                ConfigMessage::MARK_CONTINUATION->value,
                ConfigMessage::APPLICATION_DETAILS->value,
                PanelView::create()->facts(
                    PanelView::fact(
                        ConfigMessage::CHARSET->value,
                        self::orPlaceholder(self::text($application, 'charset')),
                    ),
                    PanelView::fact(
                        ConfigMessage::CURRENT_LANGUAGE->value,
                        self::language(self::text($application, 'language')),
                    ),
                    PanelView::fact(
                        ConfigMessage::SOURCE_LANGUAGE->value,
                        self::language(self::text($application, 'sourceLanguage')),
                    ),
                    PanelView::fact(
                        ConfigMessage::APPLICATION_VERSION->value,
                        self::orPlaceholder(self::text($application, 'version')),
                    ),
                ),
            )
            ->section(
                ConfigMessage::MARK_PRIMARY->value,
                ConfigMessage::PHP_EXTENSIONS->value,
                PanelView::create()->pills(...$pills),
            )
            ->section(
                ConfigMessage::MARK_PRIMARY->value,
                ConfigMessage::INSTALLED_TITLE->value,
                self::roster($extensions),
                $count,
            );

        return $this->phpInfoUrl === ''
            ? $view
            : $view->paragraph(PanelView::link(ConfigMessage::PHP_INFO_LINK->value, $this->phpInfoUrl));
    }

    /**
     * Narrows the captured roster into a sorted `package => version` map.
     *
     * @param array<array-key, mixed> $config Decoded configuration payload.
     *
     * @return array<string, string> Installed versions keyed by package name, sorted alphabetically.
     */
    private static function extensions(array $config): array
    {
        $roster = self::slice($config, 'extensions');

        $packages = [];

        foreach ($roster as $entry) {
            if (is_array($entry) === false) {
                continue;
            }

            $name = $entry['name'] ?? null;
            $version = $entry['version'] ?? null;

            if (is_string($name) && is_string($version)) {
                $packages[$name] = $version;
            }
        }

        ksort($packages);

        return $packages;
    }

    /**
     * Annotates a BCP-47 tag with its English display name.
     *
     * @param string $locale BCP-47 tag to annotate.
     *
     * A non-empty tag always resolves at least a display language, so the annotation is never empty.
     *
     * @return string Tag annotated with its display language and region, or the placeholder when the tag is empty.
     */
    private static function language(string $locale): string
    {
        if ($locale === '') {
            return ConfigMessage::PLACEHOLDER->value;
        }

        $candidates = [
            Locale::getDisplayLanguage($locale, 'en'),
            Locale::getDisplayRegion($locale, 'en'),
        ];

        $parts = [];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                $parts[] = $candidate;
            }
        }

        return sprintf(ConfigMessage::LANGUAGE_ANNOTATION->value, $locale, implode(', ', $parts));
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
        return $value === '' ? ConfigMessage::PLACEHOLDER->value : $value;
    }

    /**
     * Builds the installed-extension roster, one manifest per vendor in package order.
     *
     * @param array<string, string> $extensions Installed versions keyed by package name, sorted alphabetically.
     *
     * @return PanelView Vendor manifests, or the empty state when the capture carried no roster.
     */
    private static function roster(array $extensions): PanelView
    {
        if ($extensions === []) {
            return PanelView::create()->emptyState(
                ConfigMessage::EMPTY_HEADLINE->value,
                ConfigMessage::EMPTY_EXPLANATION->value,
            );
        }

        $vendors = [];

        foreach ($extensions as $name => $version) {
            $separator = strpos($name, '/');
            $vendor = $separator === false ? $name : substr($name, 0, $separator) . '/';
            $short = $separator === false ? $name : substr($name, $separator + 1);

            $vendors[$vendor][] = PanelView::package($short, "v{$version}");
        }

        $view = PanelView::create();

        foreach ($vendors as $vendor => $packages) {
            $view = $view->manifest($vendor, ...$packages);
        }

        return $view;
    }

    /**
     * Returns a nested payload slice, falling back to an empty array when it was not captured.
     *
     * @param array<array-key, mixed> $config Decoded configuration payload.
     * @param string $key Slice to read.
     *
     * @return array<array-key, mixed> Captured slice, or an empty array when missing or malformed.
     */
    private static function slice(array $config, string $key): array
    {
        $slice = $config[$key] ?? null;

        return is_array($slice) ? $slice : [];
    }

    /**
     * Returns a captured scalar field as a string.
     *
     * @param array<array-key, mixed> $slice Decoded payload slice holding the field.
     * @param string $key Field to read.
     *
     * @return string Captured string, or `''` when missing or not a string.
     */
    private static function text(array $slice, string $key): string
    {
        $value = $slice[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}

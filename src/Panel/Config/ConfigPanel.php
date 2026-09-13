<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

use Locale;
use PHPForge\Debug\{ColumnStyle, Panel, PanelView, Tone};

use function count;
use function implode;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;

/**
 * Presents the captured application identity, PHP runtime, and installed-extension roster.
 *
 * Adapters own the phpinfo route, so they supply it with {@see self::phpInfoUrl()} before presenting.
 *
 * Usage example:
 * ```php
 * $view = (new \PHPForge\Debug\Panel\Config\ConfigPanel())
 *     ->phpInfoUrl('/debug/php-info')
 *     ->present($snapshot->jsonSerialize());
 * ```
 *
 * @phpstan-import-type BadgeInline from PanelView
 */
final class ConfigPanel extends Panel
{
    /**
     * @var string Icon key shared with the built-in Configuration navigation entry.
     */
    protected const string ICON = 'config';

    /**
     * @var string Stable identifier associating the panel with the captured configuration payload.
     */
    protected const string ID = 'config';

    /**
     * @var string Panel title used in the debugger navigation.
     */
    protected const string TITLE = 'Configuration';

    /**
     * @var array<string, string> Bundled PHP extensions reported as loaded or missing, keyed by payload field.
     */
    private const array PHP_EXTENSIONS = [
        'xdebug' => 'Xdebug',
        'apcu' => 'APCu',
        'memcache' => 'Memcache',
        'memcached' => 'Memcached',
    ];

    /**
     * @var string Placeholder shown wherever the capture left a field empty.
     */
    private const string PLACEHOLDER = '—';

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

        $view = PanelView::create()
            ->summary('', $yii === '' ? self::PLACEHOLDER : "Yii {$yii}")
            ->summary('', $version === '' ? self::PLACEHOLDER : "PHP {$version}", false)
            ->summary($count === 1 ? ' extension' : ' extensions', $count)
            ->overview(
                [
                    'Yii' => self::orPlaceholder($yii),
                    'PHP' => self::orPlaceholder($version),
                    'Environment' => self::orPlaceholder(self::text($application, 'env')),
                    'Debug mode' => self::flag($application, 'debug', 'on', 'off'),
                    'Application' => self::orPlaceholder(self::text($application, 'name')),
                    'Application version' => self::orPlaceholder(self::text($application, 'version')),
                ],
                true,
            );

        $runtime = [];

        foreach (self::PHP_EXTENSIONS as $key => $label) {
            $runtime[$label] = self::flag($php, $key, 'loaded', 'missing');
        }

        $view = $view
            ->heading('PHP extensions', true)
            ->overview($runtime, true)
            ->heading('Application details', true)
            ->overview(
                [
                    'Charset' => self::orPlaceholder(self::text($application, 'charset')),
                    'Current language' => self::language(self::text($application, 'language')),
                    'Source language' => self::language(self::text($application, 'sourceLanguage')),
                ],
                true,
            )
            ->heading(sprintf('Installed extensions (%d)', $count), true);

        $view = $count === 0
            ? $view->emptyState(
                'No installed extensions recorded',
                'The capture carried no Composer package roster for this request.',
            )
            : $view->table(
                ['Package', 'Version'],
                self::rows($extensions),
                true,
                [
                    0 => ColumnStyle::IDENTIFIER,
                    1 => ColumnStyle::MONOSPACE,
                ],
            );

        return $this->phpInfoUrl === ''
            ? $view
            : $view->paragraph(PanelView::link('View full phpinfo', $this->phpInfoUrl, true));
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
     * Builds the badge reporting whether a captured flag was enabled.
     *
     * @param array<array-key, mixed> $slice Decoded payload slice holding the flag.
     * @param string $key Flag to read.
     * @param string $enabled Badge label used when the flag is `true`.
     * @param string $disabled Badge label used when the flag is `false`.
     *
     * @return BadgeInline Success badge when enabled, muted badge otherwise.
     */
    private static function flag(array $slice, string $key, string $enabled, string $disabled): array
    {
        return ($slice[$key] ?? false) === true
            ? PanelView::badge($enabled, Tone::SUCCESS)
            : PanelView::badge($disabled, Tone::MUTED);
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
            return self::PLACEHOLDER;
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

        $annotation = implode(', ', $parts);

        return "{$locale} ({$annotation})";
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
     * Builds the roster table rows in package order.
     *
     * @param array<string, string> $packages Installed versions keyed by package name.
     *
     * @return list<list<string>> One row per package, matching the declared column order.
     */
    private static function rows(array $packages): array
    {
        $rows = [];

        foreach ($packages as $name => $version) {
            $rows[] = [$name, $version];
        }

        return $rows;
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

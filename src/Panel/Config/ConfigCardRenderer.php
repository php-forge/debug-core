<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Config;

use Locale;
use PHPForge\Debug\Helper\ExtensionPill;
use Stringable;
use UIAwesome\Html\Flow\Div;
use UIAwesome\Html\Heading\{H2, H3};
use UIAwesome\Html\List\{Dd, Dl, Dt};
use UIAwesome\Html\Palpable\A;
use UIAwesome\Html\Phrasing\Span;
use UIAwesome\Html\Root\Header;
use UIAwesome\Html\Sectioning\{Article, Section};

use function array_map;
use function count;
use function explode;
use function implode;
use function is_string;

/**
 * Renders the typed sections of the Configuration panel detail view.
 */
final class ConfigCardRenderer
{
    /**
     * Decorative corner positions framing every readout card, in render order.
     */
    private const array CORNERS = [
        'tl',
        'tr',
        'bl',
        'br',
    ];

    /**
     * Renders the `Application details` description list (charset, current language, source language).
     *
     * @param ApplicationConfig $app Typed application section.
     *
     * @return Section Application details section.
     */
    public static function renderApplicationDetailsSection(ApplicationConfig $app): Section
    {
        return self::renderSection(
            '//',
            [' Application details'],
            Dl::tag()
                ->class('yii-debug-dl')
                ->html(
                    self::renderDlRow('Charset', $app->charset !== '' ? $app->charset : '—'),
                    self::renderDlRow('Current language', self::formatLanguage($app->language)),
                    self::renderDlRow('Source language', self::formatLanguage($app->sourceLanguage)),
                ),
        );
    }

    /**
     * Renders the Installed extensions section, or returns `null` when the roster is empty so the caller can omit the
     * wrapper entirely.
     *
     * @param ConfigSummary $summary Typed configuration summary.
     *
     * @return Section|null Installed extensions section, or `null` when the roster is empty.
     */
    public static function renderInstalledExtensionsSection(ConfigSummary $summary): Section|null
    {
        if ($summary->hasExtensions() === false) {
            return null;
        }

        $groups = [];

        foreach ($summary->extensions as $name => $version) {
            $parts = explode('/', $name, 2);
            $vendor = $parts[0];
            $package = $parts[1] ?? $name;

            $groups[$vendor][$package] = $version;
        }

        $items = [];

        foreach ($groups as $vendor => $packages) {
            $items[] = self::renderPackageGroup($vendor, $packages);
        }

        return self::renderSection(
            '::',
            [
                ' Installed extensions ',
                Span::tag()
                    ->class('yii-debug-section-count')
                    ->content((string) $summary->extensionCount()),
            ],
            Div::tag()
                ->class('yii-debug-package-groups')
                ->html(...$items),
        );
    }

    /**
     * Renders the labeled on/off pill strip for the bundled PHP extensions (Xdebug, APCu, Memcache, Memcached).
     *
     * @param PhpConfig $php Typed PHP runtime section.
     *
     * @return Section PHP extensions section.
     */
    public static function renderPhpExtensionsSection(PhpConfig $php): Section
    {
        $pills = [
            self::renderExtensionPill('Xdebug', $php->xdebug),
            self::renderExtensionPill('APCu', $php->apcu),
            self::renderExtensionPill('Memcache', $php->memcache),
            self::renderExtensionPill('Memcached', $php->memcached),
        ];

        return self::renderSection(
            '::',
            [' PHP extensions'],
            Div::tag()
                ->class('yii-debug-ext-strip')
                ->html(...$pills),
        );
    }

    /**
     * Renders the bottom call-to-action linking to the standalone phpinfo viewer.
     *
     * The caller resolves the destination URL (typically via `Url::to(['php-info'])`) so the renderer stays free of
     * routing concerns and easy to test in isolation.
     *
     * @param string $href Destination URL of the standalone phpinfo viewer.
     *
     * @return A Call-to-action link element.
     */
    public static function renderPhpInfoCta(string $href): A
    {
        return A::tag()
            ->class('yii-debug-cta')
            ->href($href)
            ->target('_blank')
            ->rel('noopener')
            ->html(
                Span::tag()
                    ->class('yii-debug-cta-prompt')
                    ->addAriaAttribute('hidden', 'true')
                    ->content('→'),
                Span::tag()->content('View full phpinfo'),
                Span::tag()
                    ->class('yii-debug-cta-external')
                    ->addAriaAttribute('hidden', 'true')
                    ->content('↗'),
            );
    }

    /**
     * Renders the four-card readout grid (`Yii`, `PHP`, `Environment`, `Application`) at the top of the detail view.
     *
     * @param ConfigSummary $summary Typed configuration summary.
     *
     * @return Div Readout grid container.
     */
    public static function renderReadoutGrid(ConfigSummary $summary): Div
    {
        $app = $summary->application;
        $php = $summary->php;

        $envMeta = $app->debug
            ? Span::tag()
                ->class('yii-debug-readout-chip')
                ->content("debug\u{00A0}on")
            : Span::tag()
                ->class('yii-debug-readout-chip yii-debug-readout-chip-muted')
                ->content("debug\u{00A0}off");

        $applicationMeta = $app->version !== ''
            ? Span::tag()
                ->class('yii-debug-readout-chip yii-debug-readout-chip-muted')
                ->content("v{$app->version}")
            : 'instance';

        return Div::tag()
            ->class('yii-debug-readout')
            ->html(
                self::renderReadoutCard('Yii', $app->yii, 'framework'),
                self::renderReadoutCard('PHP', $php->version, 'runtime'),
                self::renderReadoutCard('Environment', $app->env, $envMeta),
                self::renderReadoutCard('Application', $app->name !== '' ? $app->name : '—', $applicationMeta),
            );
    }

    /**
     * Returns a BCP-47 tag annotated with its English display name, or the em-dash placeholder when the locale is
     * empty.
     *
     * @param string $locale BCP-47 tag to annotate.
     *
     * @return string Annotated tag, the bare tag when no display name resolves, or `—` for an empty locale.
     */
    private static function formatLanguage(string $locale): string
    {
        if ($locale === '') {
            return '—';
        }

        $candidates = [
            Locale::getDisplayLanguage($locale, 'en'),
            Locale::getDisplayRegion($locale, 'en'),
        ];

        $parts = [];

        foreach ($candidates as $part) {
            if (is_string($part) && $part !== '') {
                $parts[] = $part;
            }
        }

        if ($parts === []) {
            return $locale;
        }

        return "{$locale} (" . implode(', ', $parts) . ')';
    }

    /**
     * Builds the four decorative corner glyphs that frame every readout card.
     *
     * @return list<Span> Corner spans in `tl`, `tr`, `bl`, `br` order.
     */
    private static function renderCorners(): array
    {
        return array_map(
            static fn(string $corner): Span => Span::tag()
                ->class('yii-debug-readout-corner')
                ->addDataAttribute('corner', $corner)
                ->addAriaAttribute('hidden', 'true'),
            self::CORNERS,
        );
    }

    /**
     * Renders one `<dt>term</dt><dd>value</dd>` row inside the application-details description list.
     *
     * @param string $term Row label.
     * @param string $value Row value.
     *
     * @return Div Description-list row.
     */
    private static function renderDlRow(string $term, string $value): Div
    {
        return Div::tag()
            ->class('yii-debug-dl-row')
            ->html(
                Dt::tag()->content($term),
                Dd::tag()->content($value),
            );
    }

    /**
     * Renders one extension pill with an on/off state and a label.
     *
     * @param string $name Extension name shown in the pill.
     * @param bool $enabled Whether the extension is loaded.
     *
     * @return Span Extension pill element.
     */
    private static function renderExtensionPill(string $name, bool $enabled): Span
    {
        return ExtensionPill::render(
            $name,
            $enabled ? 'on' : 'off',
            $enabled,
        );
    }

    /**
     * Renders one Composer vendor group as a compact dependency ledger.
     *
     * @param string $vendor Composer vendor name.
     * @param array<string, string> $packages Package names mapped to their installed version.
     *
     * @return Article Vendor group element.
     */
    private static function renderPackageGroup(string $vendor, array $packages): Article
    {
        $items = [];

        foreach ($packages as $name => $version) {
            $items[] = self::renderPackageItem($name, $version);
        }

        $count = count($packages);

        return Article::tag()
            ->class('yii-debug-package-group')
            ->html(
                Header::tag()
                    ->class('yii-debug-package-group-header')
                    ->html(
                        H3::tag()
                            ->class('yii-debug-package-vendor')
                            ->content("{$vendor}/"),
                        Span::tag()
                            ->class('yii-debug-package-group-count')
                            ->content("{$count} " . ($count === 1 ? 'package' : 'packages')),
                    ),
                Dl::tag()
                    ->class('yii-debug-package-list')
                    ->html(...$items),
            );
    }

    /**
     * Renders one package name and version row inside its Composer vendor group.
     *
     * @param string $name Package name without its vendor prefix.
     * @param string $version Installed version.
     *
     * @return Div Package row.
     */
    private static function renderPackageItem(string $name, string $version): Div
    {
        return Div::tag()
            ->class('yii-debug-package-row')
            ->html(
                Dt::tag()
                    ->class('yii-debug-package-name')
                    ->content($name),
                Dd::tag()
                    ->class('yii-debug-package-version')
                    ->content("v{$version}"),
            );
    }

    /**
     * Builds one readout card with a label, value, and either a plain-text meta line or a chip.
     *
     * @param string $label Card label.
     * @param string $value Card value.
     * @param Span|string $meta Chip element or plain-text meta line shown under the value.
     *
     * @return Article Readout card element.
     */
    private static function renderReadoutCard(string $label, string $value, Span|string $meta): Article
    {
        $metaWrap = Span::tag()->class('yii-debug-readout-meta');

        $metaWrap = $meta instanceof Stringable
            ? $metaWrap->html($meta)
            : $metaWrap->content($meta);

        $children = [
            ...self::renderCorners(),
            Span::tag()
                ->class('yii-debug-readout-label')
                ->content($label),
            Span::tag()
                ->class('yii-debug-readout-value')
                ->content($value),
            $metaWrap,
        ];

        return Article::tag()
            ->class('yii-debug-readout-card')
            ->html(...$children);
    }

    /**
     * Builds the shared section shell: a marked title followed by the section body.
     *
     * @param string $mark Glyph rendered inside the section mark.
     * @param list<string|Stringable> $title Title nodes rendered after the mark.
     * @param string|Stringable ...$body Section body nodes.
     *
     * @return Section Section element.
     */
    private static function renderSection(string $mark, array $title, string|Stringable ...$body): Section
    {
        return Section::tag()
            ->class('yii-debug-section')
            ->html(
                H2::tag()
                    ->class('yii-debug-section-title')
                    ->html(
                        Span::tag()
                            ->class('yii-debug-section-mark')
                            ->content($mark),
                        ...$title,
                    ),
                ...$body,
            );
    }
}

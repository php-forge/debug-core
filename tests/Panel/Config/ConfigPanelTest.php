<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Config;

use PHPForge\Debug\{ColumnStyle, PanelView, Tone};
use PHPForge\Debug\Panel\Config\{ConfigPanel, ConfigSnapshot};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_keys;

/**
 * Unit tests for {@see ConfigPanel} covering the identity overview, runtime sections, and extension roster.
 *
 * @phpstan-import-type BadgeInline from PanelView
 * @phpstan-import-type Block from PanelView
 * @phpstan-import-type Inline from PanelView
 * @phpstan-import-type OverviewBlock from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type TableBlock from PanelView
 */
#[Group('panel')]
#[Group('config')]
final class ConfigPanelTest extends TestCase
{
    public function testASingleInstalledExtensionUsesTheSingularLabel(): void
    {
        $view = self::present(
            [
                'extensions' => [
                    'php-forge/debug' => [
                        'name' => 'php-forge/debug',
                        'version' => '0.1.0',
                    ],
                ],
            ],
        );

        self::assertSame(
            ' extension',
            ($view->summaryMetrics()[2] ?? self::fail('The roster size must stay in the summary.'))['label'],
            'A single package must use the singular label.',
        );
        self::assertSame(
            'Installed extensions (1)',
            self::heading(self::blockAt($view, 5))['title'],
            'The roster heading must report the package count.',
        );
    }
    public function testEmptyCaptureFallsBackToPlaceholdersAndAnEmptyRoster(): void
    {
        $view = self::present([]);

        self::assertSame(
            [
                ['label' => '', 'value' => ['kind' => 'text', 'value' => '—', 'style' => 'strong']],
                ['label' => '', 'value' => ['kind' => 'text', 'value' => '—', 'style' => 'plain']],
                ['label' => ' extensions', 'value' => ['kind' => 'text', 'value' => '0', 'style' => 'strong']],
            ],
            $view->summaryMetrics(),
            'Missing versions must show the placeholder.',
        );

        $fields = self::fields(self::overview(self::blockAt($view, 0)));

        self::assertSame(
            '—',
            self::textValue($fields['Yii'] ?? self::fail('The identity must keep the framework row.')),
            'A missing framework version must show the placeholder.',
        );
        self::assertSame(
            'off',
            self::badge($fields['Debug mode'] ?? self::fail('The identity must keep the debug row.'))['label'],
            'A missing debug flag must read as disabled.',
        );

        $details = self::fields(self::overview(self::blockAt($view, 4)));

        self::assertSame(
            '—',
            self::textValue($details['Current language'] ?? self::fail('Details must keep the language row.')),
            'A missing locale must show the placeholder.',
        );
        self::assertSame(
            'Installed extensions (0)',
            self::heading(self::blockAt($view, 5))['title'],
            'The roster heading must report zero packages.',
        );
        self::assertSame(
            'emptyState',
            self::blockAt($view, 6)['kind'],
            'An empty roster must be explained instead of rendering a table.',
        );
        self::assertCount(
            7,
            $view->blocks(),
            'An adapter without a phpinfo route must not add the call to action.',
        );
    }

    public function testLanguageOnlyTagIsAnnotatedWithoutARegion(): void
    {
        $view = self::present(['application' => ['language' => 'zz']]);
        $details = self::fields(self::overview(self::blockAt($view, 4)));

        self::assertSame(
            'zz (zz)',
            self::textValue($details['Current language'] ?? self::fail('Details must keep the language row.')),
            'A tag with no region must be annotated with the language alone.',
        );
    }

    public function testMetadataMatchesTheBuiltInConfigurationPanel(): void
    {
        $panel = new ConfigPanel();

        self::assertSame(
            'config',
            $panel->id(),
            'The persisted panel identifier must stay stable.',
        );
        self::assertSame(
            'Configuration',
            $panel->name(),
            'The navigation title must stay stable.',
        );
        self::assertSame(
            'config',
            $panel->icon(),
            'The panel must reuse the existing icon.',
        );
    }

    public function testPhpInfoUrlIsOptionalAndReturnsANewInstance(): void
    {
        $panel = new ConfigPanel();

        self::assertNotSame(
            $panel,
            $panel->phpInfoUrl('/debug/php-info'),
            'New instance must be returned (immutability).',
        );

        $view = $panel->phpInfoUrl('/debug/php-info')->present(self::capture());

        $paragraph = self::paragraph($view->blocks()[7] ?? self::fail('The call to action must close the panel.'));

        self::assertSame(
            [
                'kind' => 'link',
                'label' => 'View full phpinfo',
                'href' => '/debug/php-info',
                'external' => true,
            ],
            $paragraph['content'][0] ?? self::fail('The call to action must carry a link.'),
            'The phpinfo route must open in a new browsing context.',
        );
    }

    public function testPopulatedCaptureDescribesIdentityRuntimeAndRoster(): void
    {
        $view = self::present(
            [
                'application' => [
                    'yii' => '22.0.x-dev',
                    'name' => 'My Application',
                    'version' => '1.4.0',
                    'language' => 'en-US',
                    'sourceLanguage' => 'en',
                    'charset' => 'UTF-8',
                    'env' => 'dev',
                    'debug' => true,
                ],
                'php' => [
                    'version' => '8.5.9',
                    'xdebug' => true,
                    'apcu' => false,
                    'memcache' => false,
                    'memcached' => false,
                ],
                'extensions' => [
                    'broken' => 'not an extension',
                    'yiisoft/yii2-symfonymailer' => ['name' => 'yiisoft/yii2-symfonymailer', 'version' => '22.0.0'],
                    'partial' => ['name' => 'php-forge/partial'],
                    'php-forge/debug-core' => ['name' => 'php-forge/debug-core', 'version' => '0.1.0'],
                ],
            ],
        );

        self::assertSame(
            [
                ['label' => '', 'value' => ['kind' => 'text', 'value' => 'Yii 22.0.x-dev', 'style' => 'strong']],
                ['label' => '', 'value' => ['kind' => 'text', 'value' => 'PHP 8.5.9', 'style' => 'plain']],
                ['label' => ' extensions', 'value' => ['kind' => 'text', 'value' => '2', 'style' => 'strong']],
            ],
            $view->summaryMetrics(),
            'Only the framework version may be emphasized.',
        );
        self::assertSame(
            [],
            $view->toolbarMetrics(),
            'Configuration must stay out of the toolbar.',
        );

        $identity = self::overview(self::blockAt($view, 0));

        self::assertTrue(
            $identity['compact'],
            'The identity must use the compact presentation.',
        );
        self::assertSame(
            ['Yii', 'PHP', 'Environment', 'Debug mode', 'Application', 'Application version'],
            array_keys(self::fields($identity)),
            'The identity row order must stay stable.',
        );

        $debug = self::badge(
            self::fields($identity)['Debug mode'] ?? self::fail('The identity must keep the debug row.'),
        );

        self::assertSame(
            'on',
            $debug['label'],
            'An enabled debug flag must read as enabled.',
        );
        self::assertSame(
            Tone::SUCCESS,
            $debug['tone'],
            'An enabled debug flag must use the success tone.',
        );
        self::assertSame(
            ['PHP extensions', 'Application details', 'Installed extensions (2)'],
            [
                self::heading(self::blockAt($view, 1))['title'],
                self::heading(self::blockAt($view, 3))['title'],
                self::heading(self::blockAt($view, 5))['title'],
            ],
            'Every section must keep its heading, in order.',
        );
        self::assertSame(
            [true, true, true],
            [
                self::heading(self::blockAt($view, 1))['section'],
                self::heading(self::blockAt($view, 3))['section'],
                self::heading(self::blockAt($view, 5))['section'],
            ],
            'Every section must open a section-level heading.',
        );

        $runtimeBlock = self::overview(self::blockAt($view, 2));

        self::assertTrue(
            $runtimeBlock['compact'],
            'The runtime section must use the compact presentation.',
        );

        $runtime = self::fields($runtimeBlock);

        self::assertSame(
            ['Xdebug', 'APCu', 'Memcache', 'Memcached'],
            array_keys($runtime),
            'The bundled extension order must stay stable.',
        );
        self::assertSame(
            'loaded',
            self::badge($runtime['Xdebug'] ?? self::fail('The runtime must keep the Xdebug row.'))['label'],
            'A loaded extension must read as loaded.',
        );
        self::assertSame(
            Tone::MUTED,
            self::badge($runtime['APCu'] ?? self::fail('The runtime must keep the APCu row.'))['tone'],
            'A missing extension must stay de-emphasized.',
        );

        $detailsBlock = self::overview(self::blockAt($view, 4));

        self::assertTrue(
            $detailsBlock['compact'],
            'The application details must use the compact presentation.',
        );

        $details = self::fields($detailsBlock);

        self::assertSame(
            ['Charset', 'Current language', 'Source language'],
            array_keys($details),
            'The application detail rows must stay complete and ordered.',
        );
        self::assertSame(
            'en-US (English, United States)',
            self::textValue($details['Current language'] ?? self::fail('Details must keep the language row.')),
            'A locale must be annotated with its English display name.',
        );
        self::assertSame(
            'en (English)',
            self::textValue($details['Source language'] ?? self::fail('Details must keep the source row.')),
            'A language-only tag must be annotated without a region.',
        );
        $table = self::table(self::blockAt($view, 6));

        self::assertSame(
            ['Package', 'Version'],
            $table['headers'],
            'The roster column order must stay stable.',
        );
        self::assertSame(
            [0 => ColumnStyle::IDENTIFIER, 1 => ColumnStyle::MONOSPACE],
            $table['styles'],
            'Each style must stay attached to the column it formats.',
        );
        self::assertTrue(
            $table['collapsible'],
            'A long roster must stay collapsible.',
        );
        self::assertSame(
            ['php-forge/debug-core', 'yiisoft/yii2-symfonymailer'],
            [
                self::textValue($table['rows'][0][0] ?? self::fail('The roster must list every package.')),
                self::textValue($table['rows'][1][0] ?? self::fail('The roster must list every package.')),
            ],
            'Packages must be sorted by name, and malformed entries dropped.',
        );
    }

    /**
     * @param Inline $inline Field value to narrow.
     *
     * @return BadgeInline Narrowed state badge.
     */
    private static function badge(array $inline): array
    {
        return match ($inline['kind']) {
            'badge' => $inline,
            default => self::fail('A captured flag must be a badge.'),
        };
    }

    /**
     * @param PanelView $view View to read.
     * @param int $index Position of the block in display order.
     *
     * @return Block Block declared at the requested position.
     */
    private static function blockAt(PanelView $view, int $index): array
    {
        return $view->blocks()[$index] ?? self::fail('The declared presentation structure must be complete.');
    }

    /**
     * Builds a capture payload holding a complete configuration.
     *
     * @return array<string, mixed> Payload accepted by {@see ConfigPanel::present()}.
     */
    private static function capture(): array
    {
        return ConfigSnapshot::capture(
            [
                'application' => ['yii' => '22.0.x-dev'],
                'php' => ['version' => '8.5.9'],
                'extensions' => [],
            ],
        )->jsonSerialize();
    }

    /**
     * @param OverviewBlock $block Overview whose fields are indexed.
     *
     * @return array<string, Inline> Field values keyed by their label, in display order.
     */
    private static function fields(array $block): array
    {
        $fields = [];

        foreach ($block['fields'] as $field) {
            $fields[$field['label']] = $field['value'];
        }

        return $fields;
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return array{kind: 'heading', title: string, section: bool} Narrowed section heading.
     */
    private static function heading(array $block): array
    {
        return match ($block['kind']) {
            'heading' => $block,
            default => self::fail('Each section must have a visible heading.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return OverviewBlock Narrowed overview.
     */
    private static function overview(array $block): array
    {
        return match ($block['kind']) {
            'overview' => $block,
            default => self::fail('Configuration must remain inspectable.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return ParagraphBlock Narrowed paragraph.
     */
    private static function paragraph(array $block): array
    {
        return match ($block['kind']) {
            'paragraph' => $block,
            default => self::fail('The call to action must be a paragraph.'),
        };
    }

    /**
     * Presents a plain configuration array through the panel under test.
     *
     * @param array<string, mixed> $config Configuration captured for the request.
     *
     * @return PanelView Description built by the panel.
     */
    private static function present(array $config): PanelView
    {
        return (new ConfigPanel())->present(ConfigSnapshot::capture($config)->jsonSerialize());
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return TableBlock Narrowed roster table.
     */
    private static function table(array $block): array
    {
        return match ($block['kind']) {
            'table' => $block,
            default => self::fail('The roster must use the shared table contract.'),
        };
    }

    /**
     * @param Inline $inline Cell or field value to read.
     *
     * @return string Text carried by the value.
     */
    private static function textValue(array $inline): string
    {
        return match ($inline['kind']) {
            'text' => $inline['value'],
            default => self::fail('The value must be plain text.'),
        };
    }
}

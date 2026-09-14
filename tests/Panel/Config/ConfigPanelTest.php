<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Config;

use PHPForge\Debug\Panel\Config\{ConfigPanel, ConfigSnapshot};
use PHPForge\Debug\PanelView;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Unit tests for {@see ConfigPanel} covering the identity readouts, runtime sections, and extension roster.
 *
 * @phpstan-import-type Block from PanelView
 * @phpstan-import-type EmptyStateBlock from PanelView
 * @phpstan-import-type FactsBlock from PanelView
 * @phpstan-import-type LinkInline from PanelView
 * @phpstan-import-type ManifestBlock from PanelView
 * @phpstan-import-type ParagraphBlock from PanelView
 * @phpstan-import-type PillsBlock from PanelView
 * @phpstan-import-type ReadoutsBlock from PanelView
 * @phpstan-import-type SectionBlock from PanelView
 */
#[Group('panel')]
#[Group('config')]
final class ConfigPanelTest extends TestCase
{
    public function testARosterEntryThatIsNotAnObjectIsSkipped(): void
    {
        $view = self::present(
            [
                'extensions' => [
                    'php-forge/debug',
                    'yiisoft/arrays' => ['name' => 'yiisoft/arrays', 'version' => '3.2.1'],
                ],
            ],
        );

        $roster = self::section(self::blockAt($view, 3));

        self::assertSame(
            1,
            $roster['count'],
            'A malformed roster entry must not reach the tally.',
        );
        self::assertSame(
            [['kind' => 'package', 'name' => 'arrays', 'version' => 'v3.2.1']],
            self::manifest(self::blockAt($roster['content'], 0))['packages'],
            'Only the well-formed entry must survive.',
        );
    }
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

        $roster = self::section(self::blockAt($view, 3));

        self::assertSame(
            1,
            $roster['count'],
            'The roster title must carry the package count.',
        );

        $manifest = self::manifest(self::blockAt($roster['content'], 0));

        self::assertSame(
            'php-forge/',
            $manifest['label'],
            'A manifest groups its packages by vendor.',
        );
        self::assertSame(
            [['kind' => 'package', 'name' => 'debug', 'version' => 'v0.1.0']],
            $manifest['packages'],
            'The vendor prefix moves to the group heading.',
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
            'An empty capture must still publish the three summary metrics.',
        );

        $readouts = self::readouts(self::blockAt($view, 0));

        self::assertSame(
            ['—', '—', '—', '—'],
            array_map(static fn(array $readout): string => $readout['value'], $readouts['readouts']),
            'Every unrecorded identity value must fall back to the placeholder.',
        );

        self::assertSame(
            ['framework', 'runtime', 'debug off', 'instance'],
            array_map(static fn(array $readout): string => $readout['caption'], $readouts['readouts']),
            'An unrecorded debug flag must read as disabled.',
        );

        $pills = self::pills(self::blockAt(self::section(self::blockAt($view, 2))['content'], 0));

        self::assertSame(
            [false, false, false, false],
            array_map(static fn(array $pill): bool => $pill['enabled'], $pills['pills']),
            'An unrecorded bundled extension must read as missing.',
        );

        $details = self::facts(self::blockAt(self::section(self::blockAt($view, 1))['content'], 0));

        self::assertSame(
            ['—', '—', '—', '—'],
            array_map(static fn(array $fact): string => $fact['value'], $details['facts']),
            'An unrecorded locale must fall back to the placeholder.',
        );

        $roster = self::section(self::blockAt($view, 3));

        self::assertSame(
            0,
            $roster['count'],
            'An empty roster must report zero packages.',
        );
        self::assertSame(
            'No installed extensions recorded',
            self::emptyState(self::blockAt($roster['content'], 0))['title'],
            'An empty roster must explain the absent capture.',
        );
    }

    public function testLanguageOnlyTagIsAnnotatedWithoutARegion(): void
    {
        $view = self::present(['application' => ['language' => 'es']]);
        $details = self::facts(self::blockAt(self::section(self::blockAt($view, 1))['content'], 0));

        self::assertSame(
            'es (Spanish)',
            $details['facts'][1]['value'] ?? '',
            'A language-only tag must be annotated without a region.',
        );
    }

    public function testPhpInfoUrlIsOptionalAndReturnsANewInstance(): void
    {
        $panel = new ConfigPanel();

        $linked = $panel->phpInfoUrl('/debug/php-info');

        self::assertNotSame(
            $panel,
            $linked,
            'The wither must return a new instance.',
        );
        self::assertCount(
            4,
            $panel->present(self::capture())->blocks(),
            'Without a phpinfo URL the panel must omit the call to action.',
        );

        $blocks = $linked->present(self::capture())->blocks();

        self::assertCount(
            5,
            $blocks,
            'A phpinfo URL must append the call to action.',
        );

        $link = self::link(self::paragraph(self::blockAt($linked->present(self::capture()), 4)));

        self::assertSame(
            '/debug/php-info',
            $link['href'],
            'The call to action must link to the configured phpinfo URL.',
        );
        self::assertFalse(
            $link['external'],
            'The phpinfo page must open in the debugger, not a new window.',
        );
    }

    public function testPopulatedCaptureDescribesIdentityRuntimeAndRoster(): void
    {
        $view = self::present(
            [
                'application' => [
                    'yii' => '22.0.x-dev',
                    'name' => 'My Application',
                    'version' => '1.0',
                    'charset' => 'UTF-8',
                    'language' => 'en-US',
                    'sourceLanguage' => 'en-US',
                    'env' => 'prod',
                    'debug' => true,
                ],
                'php' => [
                    'version' => '8.5.9',
                    'xdebug' => true,
                    'apcu' => true,
                    'memcache' => false,
                    'memcached' => false,
                ],
                'extensions' => [
                    'yiisoft/arrays' => ['name' => 'yiisoft/arrays', 'version' => '3.2.1'],
                    'yiisoft/aliases' => ['name' => 'yiisoft/aliases', 'version' => '3.1.1'],
                ],
            ],
        );

        $readouts = self::readouts(self::blockAt($view, 0));

        self::assertSame(
            [
                ['kind' => 'readout', 'label' => 'Yii', 'value' => '22.0.x-dev', 'caption' => 'framework'],
                ['kind' => 'readout', 'label' => 'PHP', 'value' => '8.5.9', 'caption' => 'runtime'],
                ['kind' => 'readout', 'label' => 'Environment', 'value' => 'prod', 'caption' => 'debug on'],
                ['kind' => 'readout', 'label' => 'Application', 'value' => 'My Application', 'caption' => 'instance'],
            ],
            $readouts['readouts'],
            'The identity row must lead with framework, runtime, environment, and application.',
        );

        $runtime = self::section(self::blockAt($view, 2));

        self::assertSame(
            '::',
            $runtime['mark'],
            'A primary section must carry the primary mark.',
        );
        self::assertSame(
            [
                ['kind' => 'pill', 'label' => 'APCu', 'state' => 'on', 'enabled' => true],
                ['kind' => 'pill', 'label' => 'Memcache', 'state' => 'off', 'enabled' => false],
                ['kind' => 'pill', 'label' => 'Memcached', 'state' => 'off', 'enabled' => false],
                ['kind' => 'pill', 'label' => 'Xdebug', 'state' => 'on', 'enabled' => true],
            ],
            self::pills(self::blockAt($runtime['content'], 0))['pills'],
            'Bundled extensions must be listed alphabetically with their load state.',
        );

        $details = self::section(self::blockAt($view, 1));

        self::assertSame(
            '//',
            $details['mark'],
            'A continuation section must carry the continuation mark.',
        );
        self::assertSame(
            [
                ['kind' => 'fact', 'label' => 'Charset', 'value' => 'UTF-8'],
                ['kind' => 'fact', 'label' => 'Current language', 'value' => 'en-US (English, United States)'],
                ['kind' => 'fact', 'label' => 'Source language', 'value' => 'en-US (English, United States)'],
                ['kind' => 'fact', 'label' => 'Application version', 'value' => '1.0'],
            ],
            self::facts(self::blockAt($details['content'], 0))['facts'],
            'Application details must keep charset, languages, and the application version, each annotated.',
        );

        $roster = self::section(self::blockAt($view, 3));

        self::assertSame(
            2,
            $roster['count'],
            'The roster title must carry the package count.',
        );

        $manifest = self::manifest(self::blockAt($roster['content'], 0));

        self::assertSame(
            'yiisoft/',
            $manifest['label'],
            'Packages of one vendor share a manifest.',
        );
        self::assertSame(
            [
                ['kind' => 'package', 'name' => 'aliases', 'version' => 'v3.1.1'],
                ['kind' => 'package', 'name' => 'arrays', 'version' => 'v3.2.1'],
            ],
            $manifest['packages'],
            'Packages must stay alphabetical inside their vendor.',
        );
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
     * @param Block $block Block to narrow.
     *
     * @return EmptyStateBlock Narrowed empty state.
     */
    private static function emptyState(array $block): array
    {
        return match ($block['kind']) {
            'emptyState' => $block,
            default => self::fail('The roster must explain an absent capture.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return FactsBlock Narrowed fact strip.
     */
    private static function facts(array $block): array
    {
        return match ($block['kind']) {
            'facts' => $block,
            default => self::fail('Application details must be presented as a fact strip.'),
        };
    }

    /**
     * @param ParagraphBlock $block Paragraph carrying the call to action.
     *
     * @return LinkInline Narrowed call to action.
     */
    private static function link(array $block): array
    {
        $inline = $block['content'][0] ?? self::fail('The call to action must carry a link.');

        return match ($inline['kind']) {
            'link' => $inline,
            default => self::fail('The call to action must be a link.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return ManifestBlock Narrowed vendor manifest.
     */
    private static function manifest(array $block): array
    {
        return match ($block['kind']) {
            'manifest' => $block,
            default => self::fail('The roster must group packages into vendor manifests.'),
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
     * @param Block $block Block to narrow.
     *
     * @return PillsBlock Narrowed pill strip.
     */
    private static function pills(array $block): array
    {
        return match ($block['kind']) {
            'pills' => $block,
            default => self::fail('Bundled extensions must be presented as pills.'),
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
     * @return ReadoutsBlock Narrowed readout row.
     */
    private static function readouts(array $block): array
    {
        return match ($block['kind']) {
            'readouts' => $block,
            default => self::fail('The panel must lead with the identity readouts.'),
        };
    }

    /**
     * @param Block $block Block to narrow.
     *
     * @return SectionBlock Narrowed section.
     */
    private static function section(array $block): array
    {
        return match ($block['kind']) {
            'section' => $block,
            default => self::fail('Each part of the panel must be a titled section.'),
        };
    }
}

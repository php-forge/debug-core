<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Panel\Config;

use PHPForge\Debug\Panel\Config\{ConfigPanel, ConfigSnapshot};
use PHPForge\Debug\PanelView;
use PHPForge\Debug\Presenter\{FactEntry, PackageEntry, PillEntry, ReadoutEntry, SummaryMetric, TextInline, TextStyle};
use PHPForge\Debug\Tests\Support\PanelViewAccessors;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Unit tests for {@see ConfigPanel} covering the identity readouts, runtime sections, and extension roster.
 */
#[Group('panel')]
#[Group('config')]
final class ConfigPanelTest extends TestCase
{
    use PanelViewAccessors;

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
            $roster->count,
            'A malformed roster entry must not reach the tally.',
        );
        self::assertEquals(
            [new PackageEntry('arrays', 'v3.2.1')],
            self::manifest(self::blockAt($roster->content, 0))->packages,
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
            ($view->summaryMetrics()[2] ?? self::fail('The roster size must stay in the summary.'))->label,
            'A single package must use the singular label.',
        );

        $roster = self::section(self::blockAt($view, 3));

        self::assertSame(
            1,
            $roster->count,
            'The roster title must carry the package count.',
        );

        $manifest = self::manifest(self::blockAt($roster->content, 0));

        self::assertSame(
            'php-forge/',
            $manifest->label,
            'A manifest groups its packages by vendor.',
        );
        self::assertEquals(
            [new PackageEntry('debug', 'v0.1.0')],
            $manifest->packages,
            'The vendor prefix moves to the group heading.',
        );
    }

    public function testEmptyCaptureFallsBackToPlaceholdersAndAnEmptyRoster(): void
    {
        $view = self::present([]);

        self::assertEquals(
            [
                new SummaryMetric('', new TextInline('—', TextStyle::STRONG)),
                new SummaryMetric('', new TextInline('—', TextStyle::PLAIN)),
                new SummaryMetric(' extensions', new TextInline('0', TextStyle::STRONG)),
            ],
            $view->summaryMetrics(),
            'An empty capture must still publish the three summary metrics.',
        );

        $readouts = self::readouts(self::blockAt($view, 0));

        self::assertSame(
            ['—', '—', '—', '—'],
            array_map(static fn(ReadoutEntry $readout): string => $readout->value, $readouts->readouts),
            'Every unrecorded identity value must fall back to the placeholder.',
        );

        self::assertSame(
            ['framework', 'runtime', 'debug off', 'instance'],
            array_map(static fn(ReadoutEntry $readout): string => $readout->caption, $readouts->readouts),
            'An unrecorded debug flag must read as disabled.',
        );

        $pills = self::pills(self::blockAt(self::section(self::blockAt($view, 2))->content, 0));

        self::assertSame(
            [false, false, false, false],
            array_map(static fn(PillEntry $pill): bool => $pill->enabled, $pills->pills),
            'An unrecorded bundled extension must read as missing.',
        );

        $details = self::facts(self::blockAt(self::section(self::blockAt($view, 1))->content, 0));

        self::assertSame(
            ['—', '—', '—', '—'],
            array_map(static fn(FactEntry $fact): string => $fact->value, $details->facts),
            'An unrecorded locale must fall back to the placeholder.',
        );

        $roster = self::section(self::blockAt($view, 3));

        self::assertSame(
            0,
            $roster->count,
            'An empty roster must report zero packages.',
        );
        self::assertSame(
            'No installed extensions recorded',
            self::emptyState(self::blockAt($roster->content, 0))->title,
            'An empty roster must explain the absent capture.',
        );
    }

    public function testLanguageOnlyTagIsAnnotatedWithoutARegion(): void
    {
        $view = self::present(['application' => ['language' => 'es']]);
        $details = self::facts(self::blockAt(self::section(self::blockAt($view, 1))->content, 0));

        self::assertSame(
            'es (Spanish)',
            $details->facts[1]->value ?? '',
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

        $link = self::link(
            self::paragraph(self::blockAt($linked->present(self::capture()), 4))->content[0]
                ?? self::fail('The call to action must carry a link.'),
        );

        self::assertSame(
            '/debug/php-info',
            $link->href,
            'The call to action must link to the configured phpinfo URL.',
        );
        self::assertFalse(
            $link->external,
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

        self::assertEquals(
            [
                new ReadoutEntry('Yii', '22.0.x-dev', 'framework'),
                new ReadoutEntry('PHP', '8.5.9', 'runtime'),
                new ReadoutEntry('Environment', 'prod', 'debug on'),
                new ReadoutEntry('Application', 'My Application', 'instance'),
            ],
            $readouts->readouts,
            'The identity row must lead with framework, runtime, environment, and application.',
        );

        $runtime = self::section(self::blockAt($view, 2));

        self::assertSame(
            '::',
            $runtime->mark,
            'A primary section must carry the primary mark.',
        );
        self::assertEquals(
            [
                new PillEntry('APCu', 'on', true),
                new PillEntry('Memcache', 'off', false),
                new PillEntry('Memcached', 'off', false),
                new PillEntry('Xdebug', 'on', true),
            ],
            self::pills(self::blockAt($runtime->content, 0))->pills,
            'Bundled extensions must be listed alphabetically with their load state.',
        );

        $details = self::section(self::blockAt($view, 1));

        self::assertSame(
            '//',
            $details->mark,
            'A continuation section must carry the continuation mark.',
        );
        self::assertEquals(
            [
                new FactEntry('Charset', 'UTF-8'),
                new FactEntry('Current language', 'en-US (English, United States)'),
                new FactEntry('Source language', 'en-US (English, United States)'),
                new FactEntry('Application version', '1.0'),
            ],
            self::facts(self::blockAt($details->content, 0))->facts,
            'Application details must keep charset, languages, and the application version, each annotated.',
        );

        $roster = self::section(self::blockAt($view, 3));

        self::assertSame(
            2,
            $roster->count,
            'The roster title must carry the package count.',
        );

        $manifest = self::manifest(self::blockAt($roster->content, 0));

        self::assertSame(
            'yiisoft/',
            $manifest->label,
            'Packages of one vendor share a manifest.',
        );
        self::assertEquals(
            [
                new PackageEntry('aliases', 'v3.1.1'),
                new PackageEntry('arrays', 'v3.2.1'),
            ],
            $manifest->packages,
            'Packages must stay alphabetical inside their vendor.',
        );
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
}

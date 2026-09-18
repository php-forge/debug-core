<?php

declare(strict_types=1);

namespace PHPForge\Debug\Tests\Toolbar;

use PHPForge\Debug\Registration\{PanelOverride, PanelRegistration, PanelRegistry};
use PHPForge\Debug\Toolbar\{ToolbarData, ToolbarItem, ToolbarPanel};
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function array_map;
use function file_get_contents;
use function file_put_contents;
use function getenv;
use function is_dir;
use function json_encode;
use function mkdir;
use function rtrim;

/**
 * Pins the toolbar wire contract as JSON fixtures produced by the real {@see PanelRegistry} to {@see ToolbarData}
 * pipeline, so the JavaScript suites replay exactly what PHP emits.
 */
#[Group('toolbar')]
final class ToolbarFixtureTest extends TestCase
{
    /**
     * @var string Directory holding the generated wire fixtures, resolved from the repository root.
     */
    private const string DIRECTORY = __DIR__ . '/../../resources/tests/fixtures/toolbar';
    /**
     * @var string Captured request tag shared by every fixture.
     */
    private const string TAG = 'tag-fixture';
    /**
     * @var array<string, string> Chip titles the host sends on the wire, by panel ID.
     *
     * `PanelRegistration` requires a non-empty registration title, while the Yii2 profiling chip ships an empty one,
     * so the wire value is pinned here instead of in the catalog.
     */
    private const array WIRE_TITLES = ['profiling' => ''];

    public function testConfiguredExtensionsPayloadMatchesItsFixture(): void
    {
        $registry = PanelRegistry::resolve(
            [
                ...self::builtIns(),
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('cache', 'Cache', 'db'),
                PanelRegistration::extension('inertia', 'Inertia', 'inertia'),
            ],
            [
                'inertia' => PanelOverride::fromArray(['title' => 'Inertia visits', 'position' => 1]),
                'vite' => PanelOverride::fromArray(['title' => 'Vite assets', 'icon' => 'asset']),
                'cache' => PanelOverride::fromArray(['title' => 'Cache operations']),
            ],
        );

        self::assertSame(
            ['request', 'log', 'event', 'profiling', 'inertia', 'cache', 'vite'],
            self::ids($registry),
            'Built-ins, then the positioned entry, then the rest by effective title.',
        );
        self::assertFixture(
            'configured-extensions.json',
            self::payload(
                $registry,
                [
                    ...self::builtInItems(),
                    'inertia' => [ToolbarItem::create('2')->withLabel('visits')],
                    'cache' => [ToolbarItem::create('7')->withLabel('hits')],
                    'vite' => [ToolbarItem::create('3')->withLabel('assets')],
                ],
                '/debug/assets/svg/',
            ),
        );
    }

    public function testDisabledAndMinimalPayloadMatchesItsFixture(): void
    {
        $registry = PanelRegistry::resolve(
            [
                ...self::builtIns(),
                PanelRegistration::extension('vite', 'Vite', 'brand-javascript'),
                PanelRegistration::extension('inertia', 'Inertia', 'inertia'),
            ],
            [
                'vite' => PanelOverride::fromArray(['enabled' => false]),
                'inertia' => PanelOverride::fromArray(['enabled' => false]),
            ],
        );

        self::assertSame(
            ['request', 'log', 'event', 'profiling'],
            self::ids($registry),
            'Disabled entries must leave no gap in the display order.',
        );
        self::assertSame(['inertia', 'vite'], $registry->disabled(), 'Both removals must be listed, alphabetically.');
        self::assertFixture('disabled-and-minimal.json', self::payload($registry, self::builtInItems(), ''));
    }

    public function testExtensionFailurePayloadMatchesItsFixture(): void
    {
        $registry = PanelRegistry::resolve(
            [...self::builtIns(), PanelRegistration::extension('cache', 'Cache', 'db')],
        );

        self::assertSame(
            ['request', 'log', 'event', 'profiling', 'cache'],
            self::ids($registry),
            'A failing extension must keep its place in the display order.',
        );
        self::assertFixture(
            'extension-failure.json',
            self::payload(
                $registry,
                [
                    ...self::builtInItems(),
                    'cache' => [
                        ToolbarItem::create('error')
                            ->withStatus('danger')
                            ->withLabel('Cache')
                            ->withTitle("Debug panel 'cache' returned an invalid toolbar envelope: 'items'."),
                    ],
                ],
                '/debug/assets/svg/',
            ),
        );
    }

    /**
     * Compares the payload with its fixture, rewriting the file first when regeneration is requested.
     *
     * @param string $name Fixture file name.
     * @param ToolbarData $data Payload to serialize.
     */
    private static function assertFixture(string $name, ToolbarData $data): void
    {
        $path = self::DIRECTORY . '/' . $name;
        $json = json_encode(
            $data->jsonSerialize(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        if (getenv('DEBUG_UPDATE_FIXTURES') === '1') {
            if (is_dir(self::DIRECTORY) === false && mkdir(self::DIRECTORY, 0o775, true) === false) {
                self::fail('Unable to create the fixture directory: ' . self::DIRECTORY . '.');
            }

            if (file_put_contents($path, $json . "\n") === false) {
                self::fail("Unable to write the fixture: {$path}.");
            }
        }

        $fixture = file_get_contents($path);

        if ($fixture === false) {
            self::fail("Unable to read the fixture: {$path}.");
        }

        self::assertSame(rtrim($fixture, "\n"), $json, "Payload must match `{$name}`.");
    }

    /**
     * Creates the metrics every fixture attaches to its built-in panels.
     *
     * @return array<string, list<ToolbarItem>> Built-in metrics keyed by panel ID.
     */
    private static function builtInItems(): array
    {
        return [
            'request' => [ToolbarItem::create('GET /')],
            'log' => [ToolbarItem::create('12')->withLabel('messages')],
            'event' => [ToolbarItem::create('3')],
            'profiling' => [ToolbarItem::create('42 ms')],
        ];
    }

    /**
     * Creates the host built-ins in their fixed display order.
     *
     * @return list<PanelRegistration> Built-in registrations with provider defaults.
     */
    private static function builtIns(): array
    {
        return [
            PanelRegistration::builtIn('request', 'Request', 'request'),
            PanelRegistration::builtIn('log', 'Logs', 'logs'),
            PanelRegistration::builtIn('event', 'Events', 'events'),
            PanelRegistration::builtIn('profiling', 'Profiling', 'profiling'),
        ];
    }

    /**
     * Extracts the stable keys from the resolved display order.
     *
     * @param PanelRegistry $registry Resolved catalog.
     *
     * @return list<string> Panel IDs in display order.
     */
    private static function ids(PanelRegistry $registry): array
    {
        return array_map(static fn(PanelRegistration $panel): string => $panel->id, $registry->enabled());
    }

    /**
     * Maps the resolved catalog onto the payload the toolbar runtime loads.
     *
     * @param PanelRegistry $registry Resolved catalog.
     * @param array<string, list<ToolbarItem>> $items Metrics keyed by panel ID.
     * @param string $iconBaseUrl Base URL for the SVG inventory, or `''` when the host inlines icons.
     *
     * @return ToolbarData Payload carrying the panels in display order.
     */
    private static function payload(PanelRegistry $registry, array $items, string $iconBaseUrl): ToolbarData
    {
        $panels = array_map(
            static fn(PanelRegistration $panel): ToolbarPanel => ToolbarPanel::create(
                $panel->id,
                self::WIRE_TITLES[$panel->id] ?? $panel->title,
            )
                ->withIcon($panel->icon)
                ->withExtension($panel->extension)
                ->withUrl('/debug/view?tag=' . self::TAG . '&panel=' . $panel->id)
                ->withItems($items[$panel->id] ?? []),
            $registry->enabled(),
        );

        return ToolbarData::create(self::TAG, 'Debugger')
            ->withNavigation('/debug/index', '/debug/view?tag=' . self::TAG . '&panel=config', '/debug/phpinfo')
            ->withPresentation('bottom', 50, $iconBaseUrl)
            ->withPanels($panels);
    }
}

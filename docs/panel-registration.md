# Panel registration contract

Status: **implemented** (Phase 3, 2026-09-17). `PHPForge\Debug\Registration` lives in `php-forge/debug-core`;
`php-forge/debug` 0.3.0 removed the activity flag; both adapters resolve registration through `PanelRegistry` and
pass their acceptance fixtures. Host suites were verified against the local sources with a PHPUnit bootstrap because
Packagist does not yet carry the coordinated `dev-main` branches.

## Problem

Both adapters read a panel's title and icon from constants on the provider class, so an application cannot rename
or re-icon Vite, Inertia, or its own panel without subclassing a `final` class. Yii3 ships a closed provider
catalog (`extensions.inertia`, `extensions.vite`), and both adapters classify and order extension panels twice
(toolbar and sidebar) with duplicated code. Yii2 silently drops a portable panel configured as a class string or an
array definition.

## Decisions

1. **Three concerns stay separate.** Collection (`CollectorInterface`), snapshot-only presentation
   (`Panel::present()`), and host registration metadata (this contract). A presenter never sees registration data.
2. **`Panel::id()` stays on the presenter.** It is the stable association with captured data and is validated
   against the registration key. Renaming a title never changes the stored key or any URL.
3. **`Panel::name()` and `Panel::icon()` become provider defaults.** The constants remain mandatory so that a
   provider works with zero configuration, but the application's override wins. `php-forge/debug` needs no API
   change for this; only its documentation changes.
4. **`PanelView::active()` and `isActive()` are removed** in `php-forge/debug` 0.3.0. No host, renderer, or provider
   reads the flag; visibility is decided from capture presence and failures. Removal lands in Phase 2.
5. **The policy lives in `php-forge/debug-core`**, namespace `PHPForge\Debug\Registration`. Adapters keep object
   construction, DI references, routes, and event wiring. `php-forge/debug` gains no dependency on core.
6. **One `enabled` flag per concern, no inference.** Disabling a panel entry removes its presenter; disabling a
   collector entry removes its capture. Disabling a provider completely means disabling both entries. A disabled
   entry never instantiates its class, so an uninstalled optional package is not an error.
7. **Ordering.** Host built-ins keep their fixed order (History, Request, Logs, Events, Profiling, then the host's
   remaining built-ins). Extensions follow: entries with `position` ascending, then the rest by effective title,
   case-insensitive; ties break by ID. `position` on a built-in is rejected.
8. **Icon keys are validated by shape only** (`/\A[a-z0-9][a-z0-9-]*\z/`, the rule `Helper\Icon` already applies).
   No raw SVG, URL, or HTML enters configuration. Decided in Phase 2: existence stays a render-time concern.
   `Helper\Icon::render()` returns `''` for a key with no file, and the toolbar falls back to the same on-disk
   inventory, so an unknown key renders no icon instead of failing registration. A registration-time existence
   check would couple the policy to asset publishing and is not needed while both hosts read one inventory.

## Configuration shape (both hosts)

```php
'collectors' => [
    'vite' => ViteCollector::class,                              // class or service ID; Yii2 also accepts an instance
    'cache' => ['class' => CacheCollector::class, 'enabled' => true],
],
'panels' => [
    'vite' => ['class' => VitePanel::class, 'title' => 'Vite assets', 'icon' => 'asset', 'position' => 1],
    'inertia' => ['class' => InertiaPanel::class, 'enabled' => false],   // package may be uninstalled
    'cache' => CachePanel::class,                                        // provider defaults apply
],
```

- The array key is the stable ID. It must equal the collector's or provider's `id()`; a mismatch is rejected.
- Panel entry keys: `class` (required unless the value is a class string or, in Yii2, an instance), `title`, `icon`,
  `enabled`, `position`. Collector entry keys: `class`, `enabled`. Any other key is rejected by name.
- Yii2: `Module::$collectors` and `Module::$panels` keep their names and accept these forms in addition to Yii panel
  definitions and instances. An instance registers with provider defaults; use the array form to override.
- Yii3: `params['yii3/debug']['collectors']` and `params['yii3/debug']['panels']` replace
  `params['yii3/debug']['extensions']`. The package configuration references only enabled entries through
  `Reference::to()`, so the same container instance serves the event listener and the capture coordinator. Event
  listeners are declared by the application in its own `events-web`, not by the debugger package.

### Adapter decisions recorded in Phase 3

- **Overrides of `title` and `icon` apply to portable panels only.** A host-native panel (`yii\debug\Panel`
  subclass, `ExtensionPanelInterface` implementation) renders its own title, so both adapters reject those two options
  for it with an explicit exception instead of half-applying them. `enabled` and `position` follow the shared policy
  for every entry; `position` on a built-in is rejected by the policy itself.
- **Unknown keys are rejected by name for portable definitions.** For a Yii2 host-native panel or collector, the
  definition remains ordinary component configuration: only `PanelOverride::KEYS` (panels) and `enabled`
  (collectors) are peeled, the rest are component properties.
- **Yii2 falls back to the ID when a host panel reports an empty name**, because `yii\debug\Panel::getName()` may
  return `''` while `PanelRegistration` requires a title.
- **Yii2 exposes the resolved catalog** through `Module::getPanelRegistry()`; `Module::$panels` is re-keyed in
  `enabled()` order, so `ToolbarDataMapper` and `SidebarDataNormalizer` no longer sort.
- **Yii3 validates key versus `id()` inside `ExtensionRegistry`**, after the container resolved the entry; the
  configuration layer cannot know an instance ID without instantiating it, and a disabled entry must never be
  instantiated. Disabled IDs travel in a dedicated constructor argument and are reported by
  `ExtensionRegistry::disabled()`. `ProviderPanel::withMetadata()` carries the effective title and icon.
- **Yii3 assembles the built-in panel list once** in `Panel\BuiltInPanelList`, referenced by the page and toolbar
  definitions; `ToolbarDataFactory` and `DebugPageRenderer` keep the incoming order and append unregistered raw
  captures after the registered extensions.

## Core API (proposed)

```php
namespace PHPForge\Debug\Registration;

final readonly class PanelOverride
{
    public const array KEYS = ['enabled', 'icon', 'position', 'title'];

    public function __construct(
        public string|null $title = null,
        public string|null $icon = null,
        public bool|null $enabled = null,
        public int|null $position = null,
    );

    /** Rejects unknown keys and wrong types by name. Accepts `[]`. */
    public static function fromArray(array $config): self;
}

final readonly class PanelRegistration
{
    public function __construct(
        public string $id,          // non-empty, no surrounding whitespace, never normalized
        public string $title,       // non-empty
        public string $icon,        // '' or a valid icon key
        public bool $extension,     // false = host built-in, true = grouped under Extensions
        public int|null $position = null,
    );

    public static function builtIn(string $id, string $title, string $icon): self;
    public static function extension(string $id, string $title, string $icon): self;

    /** Applies title, icon, and position. Throws when position is set on a built-in. */
    public function withOverride(PanelOverride $override): self;
}

final readonly class PanelRegistry
{
    /**
     * @param iterable<PanelRegistration> $defaults Host built-ins first, then providers, with provider defaults.
     * @param iterable<string, PanelOverride> $overrides Application configuration keyed by ID.
     */
    public static function resolve(iterable $defaults, iterable $overrides = []): self;

    /** @return list<PanelRegistration> Effective registrations in display order. */
    public function enabled(): array;

    /** @return list<string> IDs disabled by configuration, sorted; includes entries with no default. */
    public function disabled(): array;

    public function get(string $id): PanelRegistration|null;
    public function isDisabled(string $id): bool;
}
```

Validation performed by `PanelRegistry::resolve()`:

- Duplicate default ID: `InvalidArgumentException` naming the ID.
- Override for an ID with no default: accepted only when `enabled === false` (uninstalled optional package);
  otherwise `InvalidArgumentException` naming the ID, so a typo never disappears silently.
- Effective title empty: rejected.

## Distinct states the hosts must keep apart

| State | Source | Host behavior |
| --- | --- | --- |
| Disabled by configuration | `enabled: false` | Not instantiated, not listed, `PanelRegistry::disabled()` |
| Provider removed, capture retained | snapshot key without registration | Listed under a raw group, inspectable |
| `null` capture | collector returned `null` | Panel listed, no payload for that request |
| Empty capture | collector returned `[]` | Panel listed, presenter renders its empty state |
| Capture failed | `DebugSnapshot::$failures` | Panel listed with the failure |
| Presentation failed | `present()` threw | Panel listed with the failure |

## Acceptance fixtures written in Phase 1

- Core: `tests/Registration/PanelOverrideTest.php`, `tests/Registration/PanelRegistryTest.php` (group
  `registration`; 26 tests, all red until the namespace exists).
- Yii2: `tests/module/ExtensionConfigurationTest.php`, `tests/ToolbarDataMapperExtensionOrderTest.php`,
  `tests/widgets/sidebar/SidebarDataNormalizerExtensionOrderTest.php`, and the application-owned fixture under
  `tests/support/stub/cache/` (`Cache`, `CacheCollector`, `CachePanel`, `AlphaPanel`, `BetaPanel`). 14 tests: 12 red,
  2 characterization green.
- Yii3: `tests/Config/ExtensionConfigurationTest.php`, `tests/ToolbarDataFactoryExtensionOrderTest.php`,
  `tests/Web/DebugPageRendererExtensionOrderTest.php`, the harness `tests/Support/PackageConfiguration.php` that
  loads the packaged `config/*.php` with application-modified params into a real `Yiisoft\Di\Container`, and the
  fixture under `tests/Support/Stubs/Cache/`. 9 tests: 7 red, 2 characterization green.

All of them pass since Phase 3. Characterization tests that passed from the start (historical replay ignores live
services; the packaged events configuration declares no provider listeners) are kept in the same files and marked as
such in their docblocks.

Open detail for Phase 2: an ID with surrounding whitespace is rejected, not trimmed. The fixtures pin the empty and
whitespace-only cases; the padded case (`' vite '`) follows the same rule.

## Follow-ups this contract leaves open

- Consolidate the icon-key regex duplicated in `PanelOverride`, `PanelRegistration`, and `Helper\Icon`.
- `config/events-web.php` in Yii3 names `Yiisoft\Yii\Http\Event\ApplicationShutdown` while `yiisoft/yii-http` is
  not a dependency of the package; review during the Phase 6 dependency pass.
- Host vendor directories still install debug 0.2.0 and vite/inertia 0.4.0 until the provider branches are pushed;
  until then run the host suites with the scratchpad bootstraps that prepend the local sources.

# Debug Core

Framework-agnostic contracts, snapshots, storage primitives, normalization and presentation helpers, and complete
frontend for PHP debugger adapters.

This package is the shared engine used by framework-specific integrations. Applications should install an adapter
instead of requiring this package directly.

## Installation

Adapter packages install Debug Core transitively. If you develop an adapter, run:

```shell
composer require php-forge/debug-core
```

## Architecture

The core package owns portable collector contracts and coordination, debug data, persistence, normalization and
presentation primitives under `PHPForge\Debug\Helper`, the frontend source and compiled files, shared fonts and icons,
the toolbar data contract, and framework-neutral PHP templates composed with the agnostic UI Awesome HTML helpers. It
does not register assets, render responses, inject toolbar markup, or depend on Yii2, Yii3, an application container, a
view implementation, or a framework request lifecycle.

Shared adapter UI contracts include `PHPForge\Debug\Data\FilterEngine`, `FilterPrefix`, `PageSize`, and `QueryInput`,
plus `PHPForge\Debug\Panel\PanelRenderContext`. Adapters provide a
`PHPForge\Debug\Routing\DebugUrlGeneratorInterface` implementation so portable panel renderers can build history,
panel, and action links without importing a framework URL manager.

Adapters collect framework data, convert it into immutable snapshots, expose toolbar data endpoints, define and
publish assets through their framework, and render the shared templates with their framework view component. They also
own toolbar response injection. Routes, controllers or actions, URL generation, panel metadata, and framework-specific
panel views remain in each adapter. Yii adapters resolve the packaged frontend at
`@vendor/php-forge/debug-core/resources/assets` and configure their own alias for `resources/views`.

The visual and behavioral synchronization contract for the Yii adapters is documented in the
[Yii Debug UI parity baseline](docs/ui-parity-baseline.md).

Persistent adapters apply `PHPForge\Debug\Capture\CapturePolicy` before snapshot capture. Its secure defaults redact
common credentials, authorization and cookie values recursively, suppress raw bodies whose decoded form changed,
truncate opaque bodies at 64 KiB, and sanitize query strings and diagnostic assignments. Tagged-value capture and
hydration also enforce depth and node budgets, while newly captured exception traces intentionally omit arguments.

`SnapshotStore::loadManifest()` and `readSnapshot()` retain their fail-closed `[]` / `null` behavior. Integrations that
need to report filesystem, lock, recovery, corruption, or envelope-integrity failures can use the additive
`loadManifestResult()` and `readSnapshotResult()` methods and inspect the result's nullable `error` property.

Current adapters:

- `yii2-extensions/debug`
- `yii3/debug`

## Request view models

Request models keep only identity data in their constructors and `::create()` factories. Use the factories to start
fluent chains without wrapping `new` in parentheses. Optional metadata is configured with `with...` methods
that return independent copies; retain the returned object or chain the calls. Read values through `get...` methods
and use `RouteInventoryView::isLive()` for inventory provenance.

```php
use PHPForge\Debug\Panel\Request\RequestHero;
use PHPForge\Debug\Panel\Request\Routing\{CurrentRouteView, RouteDefinition, RouteInventoryView};

$definition = RouteDefinition::create('orders', '/orders/{id}')
    ->withMethods(['GET'])
    ->withAction('App\\OrderAction');
$current = CurrentRouteView::create('orders')
    ->withDefinition($definition)
    ->withParameters(['id' => 42]);
$inventory = RouteInventoryView::create([$definition])
    ->withSource('Captured configuration')
    ->withLive(false);
$hero = RequestHero::create('GET', '/orders/42')
    ->withStatus(200, '2xx')
    ->withTiming('12:00:00', '3.5 ms');
```

Migration: optional constructor arguments and public properties on these four models have been replaced by the
immutable methods and getters. `RouteDefinition::fromArray()` and `toArray()` retain the existing capture schema,
including the distinction between unavailable middleware metadata (`null`) and no middleware (`[]`).

## Frontend development

The complete frontend source lives in `resources/src`. Vite produces the full-page stylesheet and runtime together
with the toolbar Web Component under `resources/assets/dist`. Rebuild and verify the packaged assets with:

```shell
npm install
npm run format:check
npm run lint:js
npm run lint:css
npm run test:js
npm run build
```

The toolbar drawer moves focus to its close control, restores the activating chip on close, closes with `Escape`, and
supports `ArrowUp`, `ArrowDown`, `Home`, and `End` on its resize separator.

## License

The package is released under the BSD-3-Clause license. See `LICENSE`.

## Fluent toolbar models

`ToolbarItem::create($value)` and `ToolbarPanel::create($id, $title)` start immutable configuration chains.
Their existing constructors and public readonly properties remain supported, including named arguments.

```php
use PHPForge\Debug\Toolbar\{ToolbarItem, ToolbarPanel};

$item = ToolbarItem::create('200')
    ->withId('status')
    ->withLabel('Status')
    ->withStatus('success')
    ->withTitle('Status code: 200 OK');
$panel = ToolbarPanel::create('request', 'Request')
    ->withIcon('request')
    ->withUrl('/debug/view?tag=request-1&panel=request')
    ->withItems([$item]);
```

Items offer `withId()`, `withLabel()`, `withIcon()`, `withStatus()`, `withTitle()`, and `withUrl()`.
Panels offer `withIcon()`, `withUrl()`, and `withItems()`. Every method returns a new instance and preserves all other
fields. Nullable options accept `null` to remove the field from JSON; `''` and `'0'` remain present. `withItems()` replaces
rather than appends metrics, preserves their order, and accepts `[]` to clear them. The default item status remains
`default`; the default panel metric list remains empty. Serialization and escaping responsibilities are unchanged.

## Structural payload comparison

`PHPForge\Debug\Comparison\PayloadDifference::between($baseline, $target)` returns an immutable result with four integer
properties: `added`, `removed`, `changed`, and `unchanged`. Arguments are captured payload arrays, or `null` for absence.
An empty array is a captured leaf, not absence. Nested `null`, `false`, integer zero, float zero, and string zero remain
distinct. Leaf paths escape `~` and `/`; list positions matter, while map insertion order does not affect the counts.

The comparison fingerprints typed leaves temporarily and retains only counts in its result. It does not alter or redact
the source payloads. Adapters retain responsibility for capture/failure precedence, state-only changes, panel ordering,
labels, and metric presentation. See the [architecture review](docs/architecture-review.md) for boundaries and follow-up work.

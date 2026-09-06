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
the source payloads. `PanelComparison` combines these counts with capture states and ordered panel identities.
See the [architecture review](docs/architecture-review.md) for boundaries and follow-up work.

## Panel comparison

`PHPForge\Debug\Comparison\PanelComparison::between($baseline, $target, $panelLabels)` accepts two `DebugSnapshot`
instances and an optional map of labels in display order. It returns an ordered list of immutable results exposing
`id`, `label`, `baselineState`, `targetState`, `added`, `removed`, `changed`, and `unchanged`.

Only IDs observed in either snapshot's payloads or failures are included, once each. Observed IDs with configured labels
come first in configuration order; remaining IDs use PHP's existing regular ascending sort and their ID as the label.
Unknown configured IDs do not create rows, and an explicitly empty label remains empty.

Failure envelopes take precedence even when a payload exists for the same ID. States remain `Failed`, `Captured`, and
`Not captured`; captured empty arrays are not absence. Structural counts come from `PayloadDifference` unchanged.
If states differ but `added + removed + changed` is zero, `changed` becomes one; `unchanged` is preserved. A transition
with structural differences does not add another change. The result retains no diagnostic values and applies no redaction.

Yii2 and Yii3 map these results into their existing public `HistoryPanelComparison` models. Yii3 retains
`HistoryPanelStates` and `HistoryPanelDifferenceCounts`; neither adapter exposes Core results in place of its public models.

## Request-summary metric comparison

`PHPForge\Debug\Comparison\SummaryMetricComparison::between($baseline, $target)` accepts two `RequestSummary`
instances and returns an ordered list of immutable comparisons. Each result exposes `label`, `baseline`, `target`,
`delta`, `trend`, and nullable `panelId`. Adapters map these fields into their own public models; no framework dependency,
capture policy, payload comparison, or snapshot mutation is involved.

The canonical order is Status, Method, AJAX, Duration, Peak memory, SQL queries, Mail messages, and Excessive DB callers.
Duration uses milliseconds and memory uses bytes divided by 1,048,576 with the existing `MB` label. Both use two decimal
places; counters use none. Decimal points, comma grouping, signs, one-decimal percentages, and related panel IDs remain
identical to the original adapters.

Missing profiling values remain `Not captured`; one missing side produces `Not comparable` with a neutral trend.
Two missing values produce `No change`. Status zero means `Not captured`, AJAX `false` means `No`, and an empty method
remains an empty string. Captured numeric zero is never treated as missing, and zero baselines omit percentages.
Deltas subtract the scaled values before formatting, while percentages use the original values. Comparisons use exact
floating-point results, not rounded display values or an epsilon: a displayed zero delta may still have a direction.

### Coordinated publication

Publish the Core revision containing `SummaryMetricComparison` and `PanelComparison` before either adapter revision
that consumes it.
Both adapters currently require `php-forge/debug-core` at `^0.1@dev`; this constraint alone does not ensure that an
installed or locked development revision includes these classes. Update and verify consuming application locks together.
Local adapter installations linked to this workspace verify integration but do not validate older published artifacts.
No adapter constructor, property, getter, return type, template, asset, or persisted representation changes.

### Event table and diagnostics

Events uses one filterable, sortable table with native diagnostic controls in the event column. Original observation
numbers, offsets from the first captured event, and previous-observation gaps remain stable across filtering, sorting,
and pagination. Event/source shortcuts show whole-capture counts and retain the adapter's substring filter semantics.

`EventInspectorRenderer::renderControls()` renders group shortcuts and capture guidance. Adapters reuse one
`EventSequence` for the complete capture and call `renderTimeCell()` and `renderEventCell()` for each visible row.
Append `renderDetailRow()` immediately after each event row, passing the table's column count. The native disclosure
reveals context and source trace across the table width, side by side on larger screens and stacked on narrow screens.
Diagnostics do not repeat the timestamp, event name, class, source, or static flag already available in the table.
There is no standalone execution-flow renderer or secondary event table.

`PanelMessage` centralizes static presentation text, starting with Events. Shared labels have unprefixed case names;
event-specific guidance and capture-state descriptions use `EVENT_`. Pass cases directly to `content()` without
`->value`; `ui-awesome/html-mixin ^0.8.1` normalizes the enum value before HTML encoding. Captured values, filter keys,
and dynamic text remain outside the catalog. The rendered wording and snapshot format are unchanged.

```php
use PHPForge\Debug\Panel\PanelMessage;
use UIAwesome\Html\Flow\P;

echo P::tag()->content(PanelMessage::EVENT_CAPTURE_GUIDANCE)->render();
```

`EventRow::withInspection()` creates an enriched copy without changing the captured row. `EventInspection` supplies
optional bounded scalar context, argument-free source locations, capture states, and request-local lifecycle correlation.
Rows without diagnostics omit `inspection` from JSON; enriched rows include it. Construct `EventInspection` without
arguments, configure optional groups through immutable methods, and read values through getters.

```php
use PHPForge\Debug\Panel\Event\EventInspection;

$inspection = (new EventInspection())
    ->withContext(['View file' => '/views/site.php'], 'captured')
    ->withTrace(['/app/action.php:42'], 'captured')
    ->withLifecycle(1, 'enter', 0, 10.25);
```

Omit groups that are not captured. Context and trace methods require an explicit capture state; lifecycle metadata
must describe an actual observation rather than an inferred pair. Capture bounds and hydration validation are unchanged.

Lifecycle intervals are shown only for one explicitly correlated entry/leave pair with a consistent source, depth,
and monotonic clock. They include nested work and dispatch overhead; they are not listener or exclusive middleware
durations. Missing or ambiguous observations remain unavailable, never zero or successful. Context and trace capture
are adapter opt-ins; listener execution and final propagation results are not captured. See each adapter's Events
configuration for its capture coverage and selected fields.

Run `npm run test:events` against the configured local applications for keyboard, filter, responsive layout, and
light/dark accessibility checks on fresh captures. These checks do not require seeded history fixtures.

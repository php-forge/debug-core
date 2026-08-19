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

Current adapters:

- `yii2-extensions/debug`
- `yii3/debug`

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

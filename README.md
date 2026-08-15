# Debug Core

Framework-agnostic contracts, snapshots, storage primitives, and complete frontend for PHP debugger adapters.

This package is the shared engine used by framework-specific integrations. Applications should install an adapter
instead of requiring this package directly.

## Installation

Adapter packages install Debug Core transitively. If you develop an adapter, run:

```shell
composer require php-forge/debug-core
```

## Architecture

The core package owns portable debug data, persistence, the full-page stylesheet and runtime, shared fonts and icons,
the self-contained toolbar Web Component, and a framework-neutral page shell. It does not depend on Yii2, Yii3, an
application container, or a framework request lifecycle. Adapters collect framework data, convert it into immutable
snapshots, expose toolbar data endpoints, publish or embed the shared assets, and keep only their framework-specific
panels and normalization logic.

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

## License

The package is released under the BSD-3-Clause license. See `LICENSE`.

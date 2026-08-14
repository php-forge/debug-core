# Debug Core

Framework-agnostic contracts, snapshots, and storage primitives for PHP debugger adapters.

This package is the shared engine used by framework-specific integrations. Applications should install an adapter
instead of requiring this package directly.

## Installation

```shell
composer require php-forge/debug-core
```

## Architecture

The core package owns portable debug data and persistence. It does not depend on Yii2, Yii3, an application container,
or a framework request lifecycle. Adapters collect framework data and convert it into the immutable snapshots provided
by this package.

Current adapters:

- `yii2-extensions/debug`
- `yii3/debug`

## License

The package is released under the BSD-3-Clause license. See `LICENSE`.

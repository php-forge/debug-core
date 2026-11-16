---
name: php-forge-debug-core
description: Use when building or maintaining a debugger adapter on php-forge/debug-core, covering snapshot storage, capture redaction, panel URL generation, toolbar injection and the shared frontend sources. Not for installing the debugger in an application, which goes through yii2-extensions/debug or yii3/debug, nor for framework routes, access policy or asset publishing.
license: BSD-3-Clause
---

# Use php-forge/debug-core

## Resolve the package

Inspect the installed package path, actual source and Composer metadata before choosing APIs. The current PHP constraint
is `>=8.3`. A lockfile or branch alias alone does not prove the installed files match it. Use
local guides at that revision; if a guide is excluded from the archive, consult the matching repository revision, not
unverified current documentation. Installing this skill does not install dependencies or authorize mutations.

## Package contracts

Applications normally install a framework adapter; this package owns common storage, snapshots, renderers and
frontend resources. Adapters own routing, access policy, assets and the decision to inject the toolbar. Collectors
implement `PHPForge\Debug\CollectorInterface` from `php-forge/debug`. Implement
`PHPForge\Debug\Routing\DebugUrlGeneratorInterface` rather than importing a framework URL manager into the core.

These behaviors are intended; do not "fix" them as incidental cleanups:

- `Storage\SnapshotStore::readSnapshot()` returns `null` for a missing, corrupt or invalid-tag snapshot and hides the
  cause. Use `readSnapshotResult()` when the diagnostic matters: a missing snapshot yields no snapshot and no error,
  while a corrupt file or an invalid tag yields a `StorageException` in `error`.
- `Panel\Log\LogContext::normalize()` applies the host `Capture\CapturePolicy` to the PSR-3 context only. The core
  does not redact the log message text; do not add redaction there.
- `Toolbar\ToolbarInjector::inject()` inserts the markup before the last case-insensitive `</body>` found by string
  search, not by parsing HTML, and appends it when there is none.

Keep the debugger limited to local/development use. Test corrupt or missing snapshots, request isolation and response
injection separately. PHP storage tests do not prove browser toolbar behavior; frontend source is under
`resources/src`, with generated output under `resources/assets/dist`.

## Verify the task

Run the relevant existing package checks and independent assertions for the selected operation. Read `composer.json`
scripts before executing them: fixers, installers and cleanup commands can mutate files. Use isolated synthetic inputs
for side-effecting behavior. Report the PHP version and missing dependencies.

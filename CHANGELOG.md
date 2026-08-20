# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.0 Under development

- feat: add framework-agnostic debug snapshot contracts, strict JSON hydration, and filesystem persistence.
- fix: bundle toolbar control icons and retry snapshot data while Yii3 finishes its post-response persistence.
- feat: add shared framework-neutral normalization and presentation helpers for debug adapters.
- feat: add validated collector contracts, lifecycle coordination, and isolated typed snapshot capture for adapters.
- fix: guard the user-switch panel against concurrent submits so a double-click (or set + reset in the same tick) sends a single identity-switch request instead of racing two session regenerations.
- test: strengthen mutation coverage and clear PHPStan's result cache before static mutation analysis.
- feat(router): implement Router panel with Current Route and Rules sections.
- fix(toolbar): follow debug tags through adapter query URLs and enforce complete JavaScript mutation coverage.
- feat(panel): add `UserRbacRow` typed view-model so adapters render RBAC role and permission rows from a single normalized shape.
- feat: add shared UI parity contracts, Asset composition, and cross-adapter acceptance documentation.
- feat(ui): share database EXPLAIN markup across debugger adapters.
- feat(ui): add shared profiler normalization and Timeline rendering contracts.
- feat(tests): add unit tests for panel snapshots and enhance existing test coverage.
- feat(ui): share User guest and RBAC section rendering and support selecting filtered tabs.
- feat(ui): add sensitive queue-payload redaction and recognize Yii3 queue producers for Dump, Mail, and Queue parity.
- fix(ui): add keyboard-resizable drawers with Escape handling and focus restoration.
- fix: harden packaging, privacy, lifecycle, snapshot recovery, dump and toolbar security, and accelerate value hydration.
- refactor: simplify strict value hydration, collector cleanup reporting, sensitive-key lookup, and toolbar message validation without changing public contracts.

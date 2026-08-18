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

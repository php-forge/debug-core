# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 0.1.3 Under development

- docs(skills): add the package skill and AI guide; add `README.md` Security and Built with AI sections and Scorecard badge.
- chore(git): ignore local agent state and environment secrets and exclude them and development artifacts from archives.
- chore(git): update `.gitattributes` for improved text and binary file handling.
- chore: standardize comments in configuration files and update dependency ignore rules.
- build(deps): pair Stylelint `18` with its SCSS configuration and rebuild the distributed debug bundle with the updated Vite.

## 0.1.2 November 6, 2026

- feat(log): render multi-line log messages with preserved whitespace, `print_r()` dumps as a tree, and `var_export()` values highlighted.
- feat(log): render `var_export()` values and PSR-3 context as typed dump trees; rename the `PrintR*` tree model and renderer to `Dump*`.

## 0.1.1 October 28, 2026

- fix(event): skip `InstrumentationGuard` trace frames on Windows by joining the skip path with `DIRECTORY_SEPARATOR`.

## 0.1.0 October 16, 2026

- feat: initial development release.

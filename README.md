<!-- markdownlint-disable MD041 -->
<p align="center">
    <a href="https://github.com/php-forge/debug-core" target="_blank">
      <img src="https://avatars.githubusercontent.com/u/103309199?s=400&u=ca3561c692f53ed7eb290d3bb226a2828741606f&v=4" width="30%" alt="PHP Forge">
    </a>
    <h1 align="center">Debug Core</h1>
    <br>
</p>
<!-- markdownlint-enable MD041 -->

<p align="center">
    <a href="https://github.com/php-forge/debug-core/actions/workflows/build.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/build.yml?style=for-the-badge&label=PHPUnit&logo=github" alt="PHPUnit">
    </a>
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/php-forge/debug-core/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=for-the-badge&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fphp-forge%2Fdebug-core%2Fmain" alt="Mutation Testing">
    </a>
    <a href="https://github.com/php-forge/debug-core/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/static.yml?style=for-the-badge&label=PHPStan&logo=github" alt="PHPStan">
    </a>
    <a href="https://github.com/php-forge/debug-core/actions/workflows/security.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/security.yml?style=for-the-badge&label=Security&logo=github" alt="Security">
    </a>
</p>

<p align="center">
    <strong>A framework-agnostic PHP core providing snapshots, storage, and the complete frontend for debugger adapters.</strong>
</p>

## Features

<picture>
    <source media="(min-width: 768px)" srcset="./docs/svgs/features.svg">
    <img src="./docs/svgs/features-mobile.svg" alt="Feature overview" style="width: 100%;">
</picture>

## Installation

Install an adapter, not this package. Debug Core is pulled in transitively:

- [`yii2-extensions/debug`](https://github.com/yii2-extensions/debug)
- [`yii3/debug`](https://github.com/yii3/debug)

If you develop an adapter:

```bash
composer require php-forge/debug-core
```

PHP 8.3 or later and the `ctype`, `intl`, and `mbstring` extensions are required.

## Adapter boundary

The core owns snapshot capture, persistence, comparison, and the shared UI. A framework adapter remains responsible
for:

- collecting framework data and converting it into immutable snapshots;
- exposing the toolbar data endpoints and deciding when a response receives the toolbar;
- defining and publishing assets through its own framework;
- rendering the shared templates with its view component;
- routes, controllers, URL generation, panel metadata, and framework-specific panel views;
- implementing `Routing\DebugUrlGeneratorInterface` so portable renderers build panel links without a framework URL
  manager.

The adapter-facing API is documented in the source PHPDoc under `src/`.

## Frontend development

The frontend source lives in `resources/src` and Vite builds it into `resources/assets/dist`. Rebuild and verify with:

```bash
npm install
npm run format:check
npm run lint:js
npm run lint:css
npm run test:js
npm run build
```

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/php-forge/debug-core/actions/workflows/static.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/php-forge/debug-core.svg?style=for-the-badge&logo=packagist&logoColor=white&label=Stable)](https://packagist.org/packages/php-forge/debug-core)
[![Total Downloads](https://img.shields.io/packagist/dt/php-forge/debug-core.svg?style=for-the-badge&logo=composer&logoColor=white&label=Downloads)](https://packagist.org/packages/php-forge/debug-core)

## Code quality

[![Codecov](https://img.shields.io/codecov/c/github/php-forge/debug-core.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage)](https://codecov.io/gh/php-forge/debug-core)
[![Quality](https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/quality.yml?style=for-the-badge&label=Quality&logo=github)](https://github.com/php-forge/debug-core/actions/workflows/quality.yml)
[![Assets](https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/assets.yml?style=for-the-badge&label=Assets&logo=github)](https://github.com/php-forge/debug-core/actions/workflows/assets.yml)
[![StyleCI](https://img.shields.io/badge/StyleCI-Passed-44CC11.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.styleci.io/repos/php-forge/debug-core?branch=main)

## Social networks

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)

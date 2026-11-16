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
    <a href="https://codecov.io/github/php-forge/debug-core" target="_blank">
        <img src="https://img.shields.io/codecov/c/github/php-forge/debug-core.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage" alt="Coverage">
    </a>
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/php-forge/debug-core/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=for-the-badge&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fphp-forge%2Fdebug-core%2Fmain" alt="Mutation Testing">
    </a>
    <a href="https://github.com/php-forge/debug-core/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/static.yml?style=for-the-badge&label=PHPStan&logo=github" alt="PHPStan">
    </a>
</p>

<p align="center">
    <strong>A framework-agnostic PHP core providing snapshots, storage, and the complete frontend for debugger adapters.</strong>
</p>

## Features

<picture>
    <source media="(min-width: 768px)" srcset="https://raw.githubusercontent.com/php-forge/debug-core/main/docs/svgs/features.svg">
    <img src="https://raw.githubusercontent.com/php-forge/debug-core/main/docs/svgs/features-mobile.svg" alt="Feature overview" style="width: 100%;">
</picture>

## Installation

Install an adapter, not this package. Debug Core is pulled in transitively:

- [`yii2-extensions/debug`](https://github.com/yii2-extensions/debug)
- [`yii3/debug`](https://github.com/yii3/debug)

If you develop an adapter:

```bash
composer require php-forge/debug-core:^0.1
```

PHP 8.3 or later and the `ctype`, `intl`, and `mbstring` extensions are required.

## Adapter boundary

The core owns snapshot capture, persistence, comparison, and the shared UI. A framework adapter remains responsible
for:

- collecting framework data through `PHPForge\Debug\CollectorInterface`, the single collector contract shared with
  provider-owned collectors, and returning the persisted payload from `capture()`;
- exposing the toolbar data endpoints and deciding when a response receives the toolbar;
- defining and publishing assets through its own framework;
- rendering the shared templates with its view component;
- routes, controllers, URL generation, panel metadata, and framework-specific panel views;
- implementing `Routing\DebugUrlGeneratorInterface` so portable renderers build panel links without a framework URL
  manager.

The adapter-facing API is documented in the source PHPDoc under `src/`.

## Frontend development

The frontend source lives in `resources/src` and Vite builds it into `resources/assets/dist`. Two entries are built:

- `resources/src/toolbar/index.js` — the `<yii-debug-toolbar>` custom element. `element.js` owns the view state, the
  shadow skeleton, and the render cycle; it composes one controller per responsibility (`loader.js`,
  `theme-controller.js`, `drawer.js`) and renders through the stateless builders in `render.js`.
- `resources/src/core/debug.js` — the debugger page bootstrap. It resolves the theme, initializes the page
  controllers (`theme.js`, `disclosure.js`, `live-filter.js`, `grid-navigation.js`, `tabs.js`, `deep-links.js`,
  `clipboard.js`, `features.js`), and owns the keyboard precedence between the layers.

Rebuild and verify with:

```bash
npm install
npm run format:check
npm run lint:js
npm run lint:css
npm run test:js
npm run build
npm run check:size
npm run check:icons
```

`check:icons` compares the toolbar's inline glyphs with `resources/assets/svg`; the toolbar-only chrome glyphs
(`chevron-left`, `chevron-right`, `close`, `external-link`) have no panel file by design and never fail `--strict`.

## Documentation

This package has no separate guides; its documentation topics live in this README and the source PHPDoc.

- 📦 [Installation](#installation)
- 🧩 [Adapter boundary](#adapter-boundary)
- 🎨 [Frontend development](#frontend-development)

## AI assistance

AI agents: read the [documentation index](https://yii2-framework.github.io/docs/llms.txt) and fetch the Markdown version
of a page by appending `index.md` to its URL. The
[AI agents guide](https://github.com/yii2-framework/docs/blob/main/src/content/docs/project/ai-agents.md) covers skill
installation, client setup and verification.

- 🤖 [`php-forge-debug-core` skill](https://github.com/php-forge/debug-core/blob/main/docs/agents.md): building a
  debugger adapter on the core, with snapshot storage and read diagnostics, log context redaction, the routing
  contract and the toolbar frontend sources.

See the [repository guides](#documentation), the [skill source](skills/php-forge-debug-core/SKILL.md), the source PHPDoc
and the [changelog](CHANGELOG.md).

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/php-forge/debug-core/actions/workflows/static.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/php-forge/debug-core.svg?style=for-the-badge&logo=packagist&logoColor=white&label=Stable)](https://packagist.org/packages/php-forge/debug-core)
[![Total Downloads](https://img.shields.io/packagist/dt/php-forge/debug-core.svg?style=for-the-badge&logo=composer&logoColor=white&label=Downloads)](https://packagist.org/packages/php-forge/debug-core)

## Code quality

[![Quality](https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/quality.yml?style=for-the-badge&label=Quality&logo=github)](https://github.com/php-forge/debug-core/actions/workflows/quality.yml)
[![Assets](https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/assets.yml?style=for-the-badge&label=Assets&logo=github)](https://github.com/php-forge/debug-core/actions/workflows/assets.yml)
[![StyleCI](https://img.shields.io/badge/dynamic/xml?url=https%3A%2F%2Fgithub.styleci.io%2Frepos%2F1334500828%2Fshield%3Fbranch%3Dmain&query=%2F%2F*%5Blocal-name%28%29%3D%27text%27%5D%5B2%5D&label=StyleCI&style=for-the-badge&logo=github&logoColor=white&color=4F5D95)](https://github.styleci.io/repos/1334500828?branch=main)

## Security

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/php-forge/debug-core/badge?style=for-the-badge)](https://scorecard.dev/viewer/?uri=github.com/php-forge/debug-core)
[![Zizmor](https://img.shields.io/github/actions/workflow/status/php-forge/debug-core/security.yml?style=for-the-badge&label=Zizmor&logo=github)](https://github.com/php-forge/debug-core/actions/workflows/security.yml)

## Built with AI

[![Built with Claude Code](https://img.shields.io/badge/Built%20with-Claude%20Code-D97757.svg?style=for-the-badge&logo=claude&logoColor=white)](https://claude.com/claude-code)
[![Built with Codex](https://img.shields.io/badge/Built%20with-Codex-10A37F.svg?style=for-the-badge&logo=openaigym&logoColor=white)](https://openai.com/codex)
[![Reviewed by CodeRabbit](https://img.shields.io/badge/Reviewed%20by-CodeRabbit-FF570A.svg?style=for-the-badge&logo=coderabbit&logoColor=white)](https://www.coderabbit.ai)

## Social networks

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)

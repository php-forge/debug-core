# Browser quality workflow

## Prerequisites

- Node.js from `.nvmrc` and the locked npm dependencies.
- PHP and Composer dependencies for Debug Core, `../../app-vue`, and
  `../../app-react`.
- The local applications at their contract paths, or two compatible origins
  supplied through environment variables.
- A Playwright Chromium installation or a compatible system Chromium binary.

Install the browser managed by Playwright:

```shell
npm ci
npx playwright install --with-deps chromium
```

For a preinstalled browser, set its absolute path instead:

```shell
export PLAYWRIGHT_CHROMIUM_EXECUTABLE=/usr/bin/chromium
```

The custom-executable mode disables Playwright video recording because its
managed FFmpeg companion is not guaranteed to be installed. Failure screenshots,
traces, diagnostics, and all explicit visual-atlas images remain available.

## Seed deterministic snapshots

```shell
npm run fixtures:seed
```

The command upserts the dense and empty quality tags into both applications and
validates the synthetic redaction sentinel after persistence. Override the two
application paths in contract order when necessary:

```shell
php tools/seed-debug-fixtures.php \
  --app=/workspace/app-vue \
  --app=/workspace/app-react \
  --rows=80
```

`--rows` accepts 1 through 5,000. Use the default for the full visual suite; use
the dedicated offline performance harness for repeatable high-row-count checks.
The dense Database fixture targets the shared local `user` schema with
plan-valid SELECT, INSERT, UPDATE, and DELETE statements. Its DML predicates are
deliberate no-ops, and the browser smoke workflow opens each verb before running
Explain All, rejecting any inline request failure, and verifying that each local
SQLite database remains byte-for-byte unchanged.

## Start the applications

Playwright can own both PHP development servers:

```shell
DEBUG_UI_START_SERVERS=1 npm run test:e2e
```

This starts:

```text
app-vue   http://localhost:8080
app-react http://localhost:8081
```

Alternatively, start the applications in separate terminals and omit
`DEBUG_UI_START_SERVERS`. Existing compatible servers are reused.

To inspect already-running remote or containerized origins, keep the order stable:

```shell
DEBUG_UI_BASE_URLS=http://127.0.0.1:9080,http://127.0.0.1:9081 \
DEBUG_UI_APP_NAMES=vue-container,react-container \
npm run test:e2e
```

Only HTTP(S) origins are accepted. Fixture seeding still uses the local contract
paths unless it is disabled with `DEBUG_UI_SEED_FIXTURES=0`.

## Commands

```shell
# Smoke, keyboard, traversal, runtime diagnostics, and the privacy sentinel.
npm run test:e2e

# WCAG-tagged axe scans of the document and toolbar shadow DOM.
npm run test:a11y

# Reproducible PNG atlas without golden comparison.
npm run test:visual

# Compare with existing Playwright screenshot baselines.
npm run test:visual:compare

# Explicitly create or update screenshot baselines.
npm run test:visual:update

# Offline 50/1,000/5,000-row browser benchmark.
npm run test:perf

# Dense live-panel navigation and DOM envelope.
npm run test:perf:live

# Distribution and design-token budgets.
npm run check:size
npm run check:contrast
npm run check:contrast:strict

# Discovery-only configuration validation; no applications or browser required.
npm run test:e2e:list
```

Select a project or test while iterating:

```shell
DEBUG_UI_START_SERVERS=1 npm run test:e2e -- --project=desktop-1440
DEBUG_UI_START_SERVERS=1 npm run test:a11y -- --project=mobile-390 --grep='light dense panels'
DEBUG_UI_START_SERVERS=1 npm run test:visual -- --project=tablet-1024 --grep='db is stable'
```

## Artifacts and screenshot names

Generated outputs are local and ignored by Git:

```text
artifacts/playwright/report/
artifacts/playwright/results/
artifacts/ui/<project>/<app>-<theme>-<surface>.png
artifacts/ui/desktop-1440/<app>-light-empty-<panel>.png
```

Open `artifacts/playwright/report/index.html` after a failure to inspect traces,
attachments, axe JSON, performance JSON, videos, and failure screenshots.

Golden screenshots, when explicitly enabled, use:

```text
e2e/snapshots/<project>/<name>.png
```

Do not update goldens as a way to silence an unexplained difference. Compare the
Vue and React pair, inspect all themes and breakpoints affected by the change,
then record the intended visual change in the project changelog.

## Reproducibility controls

The Playwright configuration fixes locale to `en-US`, time zone to UTC, reduced
motion, explicit viewport sizes, and the default light color scheme. The fixtures
use stable tags, timestamps, ordering, row counts, paths, and synthetic data.
Visual capture waits for document fonts, disables animations and transitions,
and waits for two animation frames.

The screenshot environment is still platform-sensitive because font rasterization
and browser versions differ. Generate and compare goldens with the same OS,
Chromium build, device scale factor, and dependency lock. Artifact-only capture is
the safer default for local design review.

## Size and contrast maintenance

`tools/check-asset-size.mjs` scans every file under `resources/assets/dist`,
compresses it with gzip level 9, and fails for missing, unexpected, per-file, or
aggregate budget violations. Update `tools/quality/asset-size-budget.json` only
after rebuilding assets and recording a justified before/after measurement.

`tools/check-token-contrast.mjs` parses hex `light-dark()` token pairs and `var()`
aliases directly from `resources/src/styles/tokens.css`. It uses WCAG relative
luminance against the canonical light `#ffffff` and dark `#121a15` surfaces. It
also checks the actual semantic ink/background token compositions used by solid
success, warning, danger, and information badges. `npm run check:contrast`
permits only explicitly documented baseline floors; none are configured in the
current baseline. `npm run check:contrast:strict` requires every checked token to
meet its role's target and must remain green. Axe independently verifies the
browser-computed composition.

## CI boundary

The asset workflow performs formatting, JavaScript/style tests, a clean Vite
build, size and contrast enforcement, Playwright discovery, and the offline
large-data browser harness. It does not run the live cross-adapter matrix because
the two sibling applications and their path Composer repositories are not part of
the Debug Core checkout.

A future mandatory visual CI job should version the demo applications, pin PHP,
Chromium, fonts, Node.js, locale, and OS image, seed the same contract, retain
private artifacts, and review baseline changes explicitly.

## Privacy cautions

The deterministic panels are synthetic, but the application root, incidental
developer captures, PHP Info, trace files, and videos can reveal local paths or
environment information. Keep browser artifacts private. Do not point the suite
at a production debugger, and never put real secrets into the fixture contract.

## Troubleshooting

### A port is already in use

Either stop the unrelated process, run compatible existing applications on the
contract ports, or use `DEBUG_UI_BASE_URLS` with two alternate origins. Do not swap
the Vue and React order.

### The fixture generator cannot find an application

Verify `../../app-vue` and `../../app-react`, or pass two `--app` overrides in
that order. Each path must contain `composer.json`, `vendor/`, and `runtime/`.

### Playwright cannot launch Chromium

Run `npx playwright install chromium`, or set
`PLAYWRIGHT_CHROMIUM_EXECUTABLE` to a compatible executable. Remove the override
when comparing Playwright-managed screenshot baselines.

### A test reports runtime diagnostics

Treat document, script, stylesheet, font, console, and page errors as product
failures. Inspect the trace and network log before changing the assertion.
Aborted requests caused by navigation are ignored; other critical failures are
not.

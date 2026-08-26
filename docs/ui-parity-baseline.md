# Yii Debug UI parity baseline

## Purpose

This document is the review contract for modernizing the shared Debug Core UI
without allowing the Yii adapter implementations to drift. It defines the
surfaces, fixture states, viewports, themes, interaction rules, accessibility
checks, privacy boundary, and reproducible artifacts that must remain covered.
See [Browser quality workflow](browser-quality.md) for setup and command details.

The baseline is intentionally framework-neutral. The same packaged CSS,
JavaScript, fonts, PHP view models, and renderers are exercised through the two
local adapter applications:

| Adapter fixture | Application       | Origin                  |
| --------------- | ----------------- | ----------------------- |
| Vue client      | `../../app-vue`   | `http://localhost:8080` |
| React client    | `../../app-react` | `http://localhost:8081` |

The port assignment is part of
`tools/quality/fixture-contract.json`. Do not infer it from the client name or
swap it in local scripts.

## Deterministic capture states

`php tools/seed-debug-fixtures.php` upserts two storage-version 4 snapshots into
each application's `runtime/debug` store. Existing developer captures are not
deleted.

| State | Stable tag                 | Intent                                                                                                              |
| ----- | -------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Empty | `quality-fixture-empty-v1` | Valid zero-row and optional-data states for every panel                                                             |
| Dense | `quality-fixture-dense-v1` | Representative content, 80 rows in data-heavy panels, long text, Unicode, traces, successes, warnings, and failures |

The generator hydrates and serializes all panel payloads through the production
snapshot DTOs before writing them through `SnapshotStore`. Schema drift therefore
fails setup instead of silently producing a misleading screenshot.

The dense fixture also contains an end-to-end privacy sentinel. A synthetic value
under the default exact key `password` and another under the configured prefix
`fixture_blocked_` must be replaced by `[redacted]`. A non-sensitive control value
must remain. The seeder inspects the persisted JSON after the write, and
`e2e/security.spec.js` inspects both the stored tagged-value envelope and rendered
DOM. The sentinel strings are test markers, not real credentials.

The fixed capture timestamps begin at `2026-08-24T00:00:00Z`. The history query
uses `Debug[tag]=quality-fixture` so unrelated local requests do not affect row
counts or screenshots.

## Surface inventory

The browser suite treats the following 17 document surfaces as the complete UI
inventory. A new panel is incomplete until it is added to this table,
`e2e/support/environment.js`, the fixture generator, accessibility traversal,
and the visual atlas.

| Surface             | Route kind        | Dense-state emphasis                                               | Empty-state expectation                                            |
| ------------------- | ----------------- | ------------------------------------------------------------------ | ------------------------------------------------------------------ |
| Request history     | `/debug/index`    | Two deterministic captures and summary metrics                     | Filtered history remains understandable with no unrelated captures |
| Snapshot comparison | `/debug/compare`  | Empty baseline versus dense target across all 14 panels            | Missing and unchanged values remain distinct from changes          |
| PHP Info            | `/debug/php-info` | Standalone environment table and navigation shell                  | Not fixture-backed; layout must remain bounded                     |
| Configuration       | `panel=config`    | Application, PHP, and extension metadata                           | Sections render without malformed placeholders                     |
| Request             | `panel=request`   | Hero, parameters, body, headers, session, and server tabs          | Valid empty collections and 204 status                             |
| Router              | `panel=router`    | Matching rule and rule trace                                       | No route or rule entries                                           |
| Inertia             | `panel=inertia`   | Component, props, headers, and shared data                         | No component or props                                              |
| User                | `panel=user`      | Identity, attributes, roles, and permissions                       | Guest/no identity                                                  |
| Logs                | `panel=log`       | 80 mixed-level messages and long diagnostics                       | Empty table state                                                  |
| Database            | `panel=db`        | 80 plan-valid mixed query types, durations, duplicates, and traces | Empty table state                                                  |
| Profiling           | `panel=profiling` | 80 entries, memory samples, and categories                         | Summary with no entries                                            |
| Timeline            | `panel=timeline`  | Duration and memory overview                                       | Minimal valid interval                                             |
| Events              | `panel=event`     | 80 static and instance events                                      | Empty table state                                                  |
| Mail                | `panel=mail`      | 16 success/failure messages and metadata                           | Empty table state                                                  |
| Queue               | `panel=queue`     | Push, execute, and error events                                    | Empty table state                                                  |
| Dump                | `panel=dump`      | 24 traced values                                                   | Empty table state                                                  |
| Asset Bundles       | `panel=asset`     | Bundles plus Vite manifest chunks                                  | No bundles and no Vite data                                        |

## URL contract

Examples for the Vue application are shown below; replace only the origin for
the React application.

```text
http://localhost:8080/debug/index?yii_debug_theme=light&Debug%5Btag%5D=quality-fixture
http://localhost:8080/debug/compare?yii_debug_theme=light&baseline=quality-fixture-empty-v1&target=quality-fixture-dense-v1
http://localhost:8080/debug/php-info?yii_debug_theme=dark
http://localhost:8080/debug/view?yii_debug_theme=light&tag=quality-fixture-dense-v1&panel=request
http://localhost:8080/debug/view?yii_debug_theme=dark&tag=quality-fixture-empty-v1&panel=db
```

The toolbar is exercised on the application root. Drawer URLs must remain on the
same HTTP(S) origin and target a recognized debug route.

## Theme and responsive matrix

The dense visual atlas covers every surface in both explicit themes and all
three review viewports:

| Playwright project | Viewport   | Review intent                                                    |
| ------------------ | ---------- | ---------------------------------------------------------------- |
| `desktop-1440`     | 1440 × 900 | Full navigation, dense tables, charts, and drawer                |
| `tablet-1024`      | 1024 × 768 | Intermediate wrapping and available table width                  |
| `mobile-390`       | 390 × 844  | Single-column flow, touch-size controls, and horizontal overflow |

The empty atlas is captured in light mode at 1440 px because the dense atlas
already covers theme and breakpoint behavior. Smoke and accessibility tests run
both fixture states at every viewport.

The explicit theme contract is:

- debugger documents set `data-yii-debug-theme="light|dark"` on `<html>`;
- the toolbar sets `data-theme="light|dark"` on its Web Component host;
- light and dark tokens originate in `resources/src/styles/tokens.css`;
- a theme switch must update its label and selected theme without reloading the
  inspected application.

## Behavioral parity contract

Both adapters must preserve the following observable behavior:

1. All routes return a successful HTTP response and render `.yii-debug-page`.
2. When a panel is registered in the sidebar, exactly one link is active and it
   targets the rendered panel. Configuration remains a header action, and a
   directly addressable optional panel may be absent from adapter navigation.
3. Documents do not create page-level horizontal overflow at any review width.
   A deliberately scrollable table wrapper is allowed.
4. The toolbar loads its data without console, page, script, stylesheet, or font
   failures.
5. The expand and collapse controls expose their current action through an
   accessible name.
6. A keyboard-activated toolbar chip opens the drawer and moves focus to the
   close control.
7. `Escape` closes the drawer and restores focus to the activating chip.
8. `ArrowUp`, `ArrowDown`, `Home`, and `End` operate on the drawer resize
   separator while its ARIA value reflects the resulting height.
9. Repeated toolbar data refreshes must not discard an open iframe's document,
   focus, or scroll position.
10. Runtime diagnostics are empty after each traversal step.

Framework-specific data may differ for developer-generated captures. Fixture
screenshots, structure, spacing, typography, controls, focus behavior, and empty
states must not differ merely because the host page uses Vue or React.

## Visual identity baseline

The current design direction is an engineering instrument rather than a generic
administration dashboard:

- IBM Plex Sans for interface text and JetBrains Mono for code, values, and the
  display accent;
- viridian/emerald primary actions over green-tinted neutral surfaces;
- compact cards, metrics, pills, tables, tab strips, trace disclosures, and
  semantic colors shared by every panel;
- explicit color vocabularies for HTTP verbs and status classes, log levels, SQL
  tokens, and timeline categories;
- one focus-ring token and a reduced-motion-compatible interaction layer;
- one toolbar implementation isolated in shadow DOM but driven by the same
  tokens.

A modernization may refine hierarchy, density, responsive navigation, and data
visualization. It should not introduce panel-specific typography or unrelated
color systems.

## Automated acceptance gates

| Gate                                  | Command                         | Pass condition                                                                                           |
| ------------------------------------- | ------------------------------- | -------------------------------------------------------------------------------------------------------- |
| Fixture schema and persisted sentinel | `npm run fixtures:seed`         | Both stable tags are written; forbidden sentinels are absent; exact and prefixed values are placeholders |
| Smoke and keyboard                    | `npm run test:e2e`              | Both apps, 14 panels, dense/empty, history, comparison, PHP Info, toolbar, and privacy checks pass       |
| Accessibility                         | `npm run test:a11y`             | No serious or critical axe WCAG A/AA violations and no runtime diagnostics                               |
| Reproducible visual atlas             | `npm run test:visual`           | PNG artifacts are produced under `artifacts/ui/<project>/` with stable names                             |
| Optional golden comparison            | `npm run test:visual:compare`   | Opted-in screenshots match reviewed baselines                                                            |
| Offline large-data harness            | `npm run test:perf`             | 50, 1,000, and 5,000 row render/filter/DOM budgets pass                                                  |
| Live panel envelope                   | `npm run test:perf:live`        | Dense panel navigation timing and DOM-node budgets pass                                                  |
| Asset size                            | `npm run check:size`            | Every expected distribution asset and aggregate raw/gzip size is within budget                           |
| Token contrast                        | `npm run check:contrast:strict` | Every configured text, non-text, and semantic badge composition meets its WCAG role target               |

The default visual run writes review artifacts but does not force binary goldens
into version control. Setting `DEBUG_UI_VISUAL_COMPARE=1` enables Playwright's
pixel comparison. `npm run test:visual:update` is an explicit baseline-update
operation and must be followed by paired Vue/React review.

The dense atlas produces 204 document screenshots (2 apps × 2 themes × 3
viewports × 17 surfaces), 12 toolbar-drawer screenshots, and 28 desktop empty
panel screenshots: 244 review images in total.

## Accessibility baseline

`e2e/accessibility.spec.js` scans the debugger document and toolbar shadow DOM
with axe rules tagged WCAG 2.0 A/AA, WCAG 2.1 A/AA, and WCAG 2.2 AA. Serious and
critical violations fail immediately; full JSON results are attached to the
Playwright report for review.

This severity gate is a starting point rather than a claim of complete WCAG
conformance. Manual review is still required for:

- meaningful reading and focus order at all widths;
- visible focus, target size, and zoom/reflow at 200% and 400%;
- chart and semantic-color comprehension without color alone;
- screen-reader announcements for dynamic filters, drawer loading, and errors;
- keyboard reachability of disclosures, tabs, filters, pagination, and copied
  values.

## Privacy and artifact handling

Debug captures can contain request bodies, headers, cookies, SQL, mail, identity
data, filesystem paths, environment configuration, and PHP Info. The deterministic
fixtures use only synthetic values, but PHP Info and the inspected application
root still describe the local machine.

- `artifacts/` is ignored by Git and is local-only by default.
- Do not upload Playwright traces, videos, reports, or PHP Info screenshots to a
  public artifact store without a separate sanitization decision.
- Never replace the synthetic sentinel strings with working credentials.
- A UI must not recover, embed, or expose data that was redacted before storage.
- Modernization must preserve text escaping and safe URL handling; visual
  convenience does not override those boundaries.

## Performance and size baseline

The packaged modernization baseline is 400,604 raw bytes and 204,510 bytes using
gzip level 9. Dynamically loaded database, DOM, PHP Info search, and user-switch
chunks are budgeted separately from the main debugger bundle. The file-specific ceilings in
`tools/quality/asset-size-budget.json` provide approximately five percent
headroom and reject missing or unexpected distribution files. Any budget
increase requires a before/after measurement and an explanation of user value.

`tools/quality/performance-budget.json` establishes intentionally generous,
cross-machine guardrails. It is a regression sentinel, not a user-perceived
performance claim. Record median and tail behavior separately before tightening
budgets or selecting virtualization thresholds.

## Known baseline limitations

1. The required axe gate excludes moderate and minor findings so current debt can
   be triaged without making the suite unusable. Each modernization phase should
   reduce, not expand, the full attached violation set.
2. Goldens are opt-in. Core CI detects fixture, script, contrast, size, and
   offline performance regressions, but it cannot run the local sibling demo
   applications. A versioned demo environment is required before pixel diffs can
   be a reliable mandatory CI gate.
3. Empty and dense captures do not yet cover every loading, storage-corruption,
   transport-error, permission-denied, or very-long-localization state. Add
   deterministic fixtures before redesigning those states.
4. The 5,000-row harness isolates table cost. Live fixtures are capped at 80 rows
   to keep the complete visual matrix practical; large real panel datasets still
   need profiling and, likely, pagination or virtualization.

## Modernization sequence

### Phase 0: protect contracts

- Keep the deterministic tags, cross-adapter URL mapping, sentinel, asset budget,
  and browser diagnostics green.
- Fix serious/critical accessibility failures and unsafe URL or escaping behavior
  before visual expansion.
- Review paired Vue/React images for every changed shared primitive.

### Phase 1: foundations

- Keep the strict token contrast gate green, validate compound colors in the DOM
  with axe, and document surface, border, text, semantic, spacing, radius,
  elevation, and motion roles.
- Standardize focus, hover, active, disabled, selected, loading, empty, error,
  and truncation states across controls.
- Establish responsive navigation behavior rather than relying on incidental
  wrapping.

### Phase 2: shared composition

- Modernize shell hierarchy, navigation, page header, metric cards, filters,
  tables, tabs, disclosures, pagination, copy actions, and drawer chrome as
  shared patterns.
- Preserve one consistent, readable information density across panels.
- Ensure mobile views expose data through progressive disclosure instead of
  document-level overflow.

### Phase 3: data-heavy panels

- Prioritize Logs, Database, Profiling, Timeline, Events, Mail, Queue, and Dump.
- Benchmark filter latency and DOM size with 1,000 and 5,000 rows before choosing
  pagination, server filtering, windowing, or virtualization.
- Pair semantic color with labels, icons, patterns, or position.

### Phase 4: resilience and polish

- Add deterministic loading, stale-refresh, endpoint-error, corrupt-storage,
  no-permission, long-value, and localization fixtures.
- Add screen-reader announcements and reduced-motion coverage for dynamic state.
- Promote reviewed screenshot baselines to CI only after the rendering environment
  and demo applications are versioned and stable.

## Review checklist

- [ ] Dense and empty fixture tags exist in both stores.
- [ ] The Vue origin is 8080 and the React origin is 8081.
- [ ] All 14 panels plus History, Snapshot Comparison, and PHP Info were
      inspected.
- [ ] Light and dark results were inspected at 1440, 1024, and 390 px.
- [ ] Toolbar expand, keyboard activation, resize, theme, refresh, close, and focus
      restoration were inspected.
- [ ] Paired Vue/React screenshots differ only where host data legitimately differs.
- [ ] No console, page, script, stylesheet, or font errors occurred.
- [ ] Serious/critical axe output is empty and remaining findings are triaged.
- [ ] Synthetic sensitive sentinel values are absent from storage and DOM while
      `[redacted]` is present.
- [ ] Asset and performance budgets did not regress without an approved rationale.
- [ ] Traces, videos, reports, and PHP Info images remain private unless sanitized.

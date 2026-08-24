import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

import {
  collectRuntimeDiagnostics,
  expectNoRuntimeDiagnostics,
  formatAxeViolations,
  openDebugPage,
  seriousAxeViolations,
  waitForToolbar,
} from "./support/debug-ui.js";
import {
  debugApps,
  panelEntries,
  standaloneEntries,
} from "./support/environment.js";

const wcagTags = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

async function auditDebuggerPage(page, testInfo, label) {
  const results = await new AxeBuilder({ page }).withTags(wcagTags).analyze();
  const violations = seriousAxeViolations(results);

  await testInfo.attach(`${label}-axe.json`, {
    body: Buffer.from(JSON.stringify(results, null, 2)),
    contentType: "application/json",
  });
  expect.soft(violations, formatAxeViolations(violations)).toEqual([]);
}

for (const app of debugApps()) {
  test.describe(`${app.name} accessibility`, () => {
    test.setTimeout(180_000);

    for (const theme of ["light", "dark"]) {
      for (const state of ["dense", "empty"]) {
        test(`${theme} ${state} panels have no serious axe violations`, async ({
          page,
        }, testInfo) => {
          const diagnostics = collectRuntimeDiagnostics(page);

          for (const entry of panelEntries) {
            await test.step(entry.label, async () => {
              await openDebugPage(page, app, entry, { state, theme });
              await auditDebuggerPage(
                page,
                testInfo,
                `${app.name}-${theme}-${state}-${entry.id}`,
              );
              await expectNoRuntimeDiagnostics(
                diagnostics.splice(0),
                `${app.name} ${theme} ${state} ${entry.id}`,
              );
            });
          }
        });
      }

      test(`${theme} standalone pages have no serious axe violations`, async ({
        page,
      }, testInfo) => {
        const diagnostics = collectRuntimeDiagnostics(page);

        for (const entry of standaloneEntries) {
          await test.step(entry.label, async () => {
            await openDebugPage(page, app, entry, { theme });
            await auditDebuggerPage(
              page,
              testInfo,
              `${app.name}-${theme}-${entry.id}`,
            );
            await expectNoRuntimeDiagnostics(
              diagnostics.splice(0),
              `${app.name} ${theme} ${entry.id}`,
            );
          });
        }
      });
    }

    test("toolbar shadow DOM has no serious axe violations", async ({
      page,
    }, testInfo) => {
      const diagnostics = collectRuntimeDiagnostics(page);
      const response = await page.goto(app.baseURL, {
        waitUntil: "domcontentloaded",
      });

      expect(response?.ok(), `${app.baseURL} must load successfully`).toBe(
        true,
      );
      await waitForToolbar(page);

      const results = await new AxeBuilder({ page })
        .include("yii-debug-toolbar")
        .withTags(wcagTags)
        .analyze();
      const violations = seriousAxeViolations(results);

      await testInfo.attach(`${app.name}-toolbar-axe.json`, {
        body: Buffer.from(JSON.stringify(results, null, 2)),
        contentType: "application/json",
      });
      expect(violations, formatAxeViolations(violations)).toEqual([]);
      await expectNoRuntimeDiagnostics(diagnostics, `${app.name} toolbar`);
    });
  });
}

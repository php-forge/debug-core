import { mkdir } from "node:fs/promises";
import { resolve } from "node:path";

import { expect, test } from "@playwright/test";

import {
  collectRuntimeDiagnostics,
  expandToolbar,
  expectDebuggerLayout,
  expectNoRuntimeDiagnostics,
  freezeVisualMotion,
  openDebugPage,
  waitForStableUI,
  waitForToolbar,
} from "./support/debug-ui.js";
import {
  debugApps,
  panelEntries,
  repositoryRoot,
  visualEntries,
} from "./support/environment.js";

const compareWithBaselines = process.env.DEBUG_UI_VISUAL_COMPARE === "1";

function screenshotOptions() {
  return {
    animations: "disabled",
    caret: "hide",
    fullPage: true,
    scale: "css",
  };
}

async function capture(page, testInfo, parts, overrides = {}) {
  const name = `${parts.join("-")}.png`;
  const directory = resolve(
    repositoryRoot,
    "artifacts",
    "ui",
    testInfo.project.name,
  );
  const file = resolve(directory, name);

  await mkdir(directory, { recursive: true });
  const options = { ...screenshotOptions(), ...overrides };

  await page.screenshot({ path: file, ...options });
  await testInfo.attach(name, { path: file, contentType: "image/png" });

  if (compareWithBaselines) {
    await expect(page).toHaveScreenshot(name, options);
  }
}

for (const app of debugApps()) {
  for (const theme of ["light", "dark"]) {
    test.describe(`${app.name} ${theme} visual atlas`, () => {
      for (const entry of visualEntries) {
        test(`${entry.id} is stable`, async ({ page }, testInfo) => {
          const diagnostics = collectRuntimeDiagnostics(page);

          await openDebugPage(page, app, entry, {
            state: "dense",
            theme,
          });
          await freezeVisualMotion(page);
          await waitForStableUI(page);
          await expectDebuggerLayout(page, entry, theme);
          await capture(page, testInfo, [app.name, theme, entry.id]);
          await expectNoRuntimeDiagnostics(
            diagnostics,
            `${app.name} ${theme} ${entry.id}`,
          );
        });
      }

      test("toolbar drawer is stable", async ({ page }, testInfo) => {
        const diagnostics = collectRuntimeDiagnostics(page);

        await page.addInitScript((selectedTheme) => {
          localStorage.setItem("theme", selectedTheme);
          localStorage.setItem("yii-debug-toolbar-theme", selectedTheme);
        }, theme);

        const response = await page.goto(app.baseURL, {
          waitUntil: "domcontentloaded",
        });

        expect(response?.ok(), `${app.baseURL} must load successfully`).toBe(
          true,
        );

        const toolbar = await waitForToolbar(page);
        await expandToolbar(toolbar);
        await toolbar.locator("[data-debug-url]").first().click();
        const frame = toolbar.locator("iframe[title='Yii debug panel']");

        await expect(frame).toBeVisible();
        await expect(
          frame.contentFrame().locator(".yii-debug-page").first(),
        ).toBeVisible();
        await freezeVisualMotion(page);
        await waitForStableUI(page);
        await capture(page, testInfo, [app.name, theme, "toolbar-drawer"], {
          fullPage: false,
        });
        await expectNoRuntimeDiagnostics(
          diagnostics,
          `${app.name} ${theme} toolbar drawer`,
        );
      });
    });
  }

  test(`${app.name} captures the empty-state panel atlas at desktop width`, async ({
    page,
  }, testInfo) => {
    test.skip(
      testInfo.project.name !== "desktop-1440",
      "The dense atlas already covers every viewport; empty states use the review viewport.",
    );

    const diagnostics = collectRuntimeDiagnostics(page);

    for (const entry of panelEntries) {
      await test.step(entry.label, async () => {
        await openDebugPage(page, app, entry, {
          state: "empty",
          theme: "light",
        });
        await freezeVisualMotion(page);
        await waitForStableUI(page);
        await capture(page, testInfo, [app.name, "light", "empty", entry.id]);
        await expectNoRuntimeDiagnostics(
          diagnostics.splice(0),
          `${app.name} light empty ${entry.id}`,
        );
      });
    }
  });
}

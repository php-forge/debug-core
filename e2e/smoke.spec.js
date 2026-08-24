import { createHash } from "node:crypto";
import { readFile } from "node:fs/promises";
import { resolve } from "node:path";

import { expect, test } from "@playwright/test";

import {
  collectRuntimeDiagnostics,
  expandToolbar,
  expectDebuggerLayout,
  expectNoRuntimeDiagnostics,
  openDebugPage,
  waitForStableUI,
  waitForToolbar,
} from "./support/debug-ui.js";
import {
  debugApps,
  fixtureApplicationPaths,
  panelEntries,
  standaloneEntries,
} from "./support/environment.js";

const databaseEntry = panelEntries.find((entry) => entry.id === "db");
const applicationPaths = fixtureApplicationPaths();

if (!databaseEntry) {
  throw new Error("The Database panel must be registered in the UI contract.");
}

function isExplainResponse(response) {
  const url = new URL(response.url());

  return (
    url.pathname.endsWith("/debug/db-explain") ||
    url.searchParams.get("r")?.endsWith("debug/db-explain")
  );
}

async function fileDigest(file) {
  const contents = await readFile(file);

  return createHash("sha256").update(contents).digest("hex");
}

for (const [appIndex, app] of debugApps().entries()) {
  test.describe(`${app.name} debugger`, () => {
    test("toolbar drawer preserves keyboard focus and closes with Escape", async ({
      page,
    }) => {
      const diagnostics = collectRuntimeDiagnostics(page);
      const response = await page.goto(app.baseURL, {
        waitUntil: "domcontentloaded",
      });

      expect(response, `${app.baseURL} must return a response`).not.toBeNull();
      expect(response?.ok(), `${app.baseURL} must load successfully`).toBe(
        true,
      );

      const toolbar = await waitForToolbar(page);
      await expandToolbar(toolbar);

      const trigger = toolbar.locator("[data-debug-url]").first();
      await expect(trigger).toBeVisible();
      const triggerURL = await trigger.getAttribute("data-debug-url");

      expect(
        triggerURL,
        "A toolbar panel must expose its drawer URL",
      ).toBeTruthy();
      await trigger.focus();
      await page.keyboard.press("Enter");

      const close = toolbar.getByRole("button", { name: "Close panel" });
      const frame = toolbar.locator("iframe[title='Yii debug panel']");
      const resize = toolbar.getByRole("separator", {
        name: "Resize debug panel",
      });

      await expect(close).toBeVisible();
      await expect(frame).toBeVisible();
      await expect(frame).toHaveAttribute("src", /\/debug\/view/);
      await expect(
        frame.contentFrame().locator(".yii-debug-page").first(),
      ).toBeVisible();
      await expect
        .poll(() =>
          toolbar.evaluate(
            (element) => element.shadowRoot.activeElement?.className,
          ),
        )
        .toContain("close-drawer");

      const drawerStateBeforeAjaxRefresh = await frame.evaluate((element) => {
        const document = element.contentDocument;
        const window = element.contentWindow;
        const focusTarget = document?.querySelector(
          "a[href], button, input, select, textarea, [tabindex]:not([tabindex='-1'])",
        );

        if (!document || !window || !focusTarget) {
          return null;
        }

        element.dataset.qualityDrawerIdentity = "preserved";
        focusTarget.dataset.qualityDrawerFocus = "preserved";
        focusTarget.focus();

        const maximumScroll = Math.max(
          0,
          document.documentElement.scrollHeight - window.innerHeight,
        );
        window.scrollTo(0, Math.min(120, maximumScroll));

        return {
          focused:
            document.activeElement?.dataset.qualityDrawerFocus === "preserved",
          scrollY: window.scrollY,
        };
      });

      expect(
        drawerStateBeforeAjaxRefresh,
        "The open drawer must expose a focusable document",
      ).not.toBeNull();
      expect(drawerStateBeforeAjaxRefresh?.focused).toBe(true);

      await toolbar.evaluate((element) => {
        element.setAjaxRequests([...(element.ajaxRequests ?? [])]);
      });

      await expect(frame).toHaveAttribute(
        "data-quality-drawer-identity",
        "preserved",
      );
      const drawerStateAfterAjaxRefresh = await frame.evaluate((element) => ({
        focused:
          element.contentDocument?.activeElement?.dataset.qualityDrawerFocus ===
          "preserved",
        scrollY: element.contentWindow?.scrollY ?? null,
      }));

      expect(drawerStateAfterAjaxRefresh).toEqual(drawerStateBeforeAjaxRefresh);

      await resize.focus();
      const heightBefore = await resize.getAttribute("aria-valuenow");
      await page.keyboard.press("ArrowUp");
      const heightAfter = await resize.getAttribute("aria-valuenow");

      expect(heightBefore).not.toBeNull();
      expect(heightAfter).not.toBe(heightBefore);

      await page.keyboard.press("Escape");
      await expect(frame).toHaveCount(0);
      await expect
        .poll(() =>
          toolbar.evaluate(
            (element) =>
              element.shadowRoot.activeElement?.getAttribute(
                "data-debug-url",
              ) ?? null,
          ),
        )
        .toBe(triggerURL);

      const themeBefore = await toolbar.getAttribute("data-theme");
      await toolbar.getByRole("button", { name: /Switch to .* theme/ }).click();
      const themeAfter = await toolbar.getAttribute("data-theme");

      expect(themeAfter).not.toBe(themeBefore);
      await waitForStableUI(page);
      await expectNoRuntimeDiagnostics(diagnostics, `${app.name} toolbar`);
    });

    for (const state of ["dense", "empty"]) {
      test(`traverses all 14 panels in the ${state} fixture`, async ({
        page,
      }) => {
        const diagnostics = collectRuntimeDiagnostics(page);

        for (const entry of panelEntries) {
          await test.step(entry.label, async () => {
            await openDebugPage(page, app, entry, {
              state,
              theme: "light",
            });
            await expectDebuggerLayout(page, entry, "light");

            if (entry.id !== "config") {
              const activeNavigation = page.locator(
                ".yii-debug-nav-link.is-active",
              );
              const panelNavigation = page.locator(
                `.yii-debug-nav-link[href*="panel=${entry.id}"]`,
              );

              if ((await panelNavigation.count()) > 0) {
                await expect(activeNavigation).toHaveCount(1);
                await expect(activeNavigation).toHaveAttribute(
                  "href",
                  new RegExp(`(?:[?&]|%26)panel(?:=|%3D)${entry.id}`),
                );
              }
            }

            await expect(page.locator("main h1").first()).toBeVisible();
            await expect(page.locator("main h1").first()).not.toBeEmpty();

            await expectNoRuntimeDiagnostics(
              diagnostics.splice(0),
              `${app.name} ${state} ${entry.id}`,
            );
          });
        }
      });
    }

    test("opens deterministic history, comparison, and phpinfo", async ({
      page,
    }) => {
      const diagnostics = collectRuntimeDiagnostics(page);

      for (const entry of standaloneEntries) {
        await test.step(entry.label, async () => {
          await openDebugPage(page, app, entry, { theme: "light" });
          await expectDebuggerLayout(page, entry, "light");

          if (entry.kind === "history") {
            await expect(page.locator("tr[data-key]")).toHaveCount(2);
          }

          await expectNoRuntimeDiagnostics(
            diagnostics.splice(0),
            `${app.name} ${entry.id}`,
          );
        });
      }
    });

    test("explains every fixture query without inline request failures", async ({
      page,
    }, testInfo) => {
      test.skip(
        testInfo.project.name !== "desktop-1440",
        "The EXPLAIN workflow is viewport-independent and runs once per adapter.",
      );

      const diagnostics = collectRuntimeDiagnostics(page);
      const failedResponses = [];
      const databaseFile = resolve(
        applicationPaths[appIndex],
        "runtime",
        "db.sqlite",
      );
      const databaseDigest = await fileDigest(databaseFile);

      page.on("response", (response) => {
        if (isExplainResponse(response) && !response.ok()) {
          failedResponses.push(`${response.status()} ${response.url()}`);
        }
      });

      await openDebugPage(page, app, databaseEntry, {
        pageSize: "all",
        state: "dense",
        theme: "light",
      });
      await expectDebuggerLayout(page, databaseEntry, "light");

      const toggles = page.locator(".yii-debug-db-explain-toggle");

      await expect(toggles).toHaveCount(80);

      for (const verb of ["SELECT", "INSERT", "UPDATE", "DELETE"]) {
        await test.step(`explains ${verb}`, async () => {
          const rows = page.locator(".yii-debug-grid-db tbody tr").filter({
            has: page.locator(".yii-debug-db-type", { hasText: verb }),
          });

          await expect(rows).toHaveCount(20);

          const row = rows.first();
          const toggle = row.locator(".yii-debug-db-explain-toggle");
          const target = row.locator(".yii-debug-db-explain-text");

          await toggle.click();
          await expect(target).toHaveAttribute("data-loaded", "1");
          await expect(target).not.toHaveClass(/\bis-error\b/);
          await expect(target.locator(".yii-debug-explain")).toBeVisible();
          await expect(target).not.toContainText("EXPLAIN failed:");

          await toggle.click();
          await expect(toggle).toHaveAttribute("aria-expanded", "false");
        });
      }

      const explainAll = page.locator(".yii-debug-db-explain-all-toggle");

      await explainAll.click();
      await expect(
        page.locator(".yii-debug-db-explain-text[data-loaded='1']"),
      ).toHaveCount(80, { timeout: 30_000 });
      await expect(
        page.locator(".yii-debug-db-explain-text.is-error"),
      ).toHaveCount(0);
      await expect(
        page.locator(".yii-debug-db-explain.is-loading"),
      ).toHaveCount(0);
      await expect(explainAll).toHaveText("Collapse all");
      await expect(failedResponses).toEqual([]);
      expect(
        await fileDigest(databaseFile),
        "EXPLAIN must not mutate the local application database",
      ).toBe(databaseDigest);
      await expectNoRuntimeDiagnostics(
        diagnostics,
        `${app.name} database EXPLAIN`,
      );
    });
  });
}

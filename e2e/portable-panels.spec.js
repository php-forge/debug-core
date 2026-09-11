import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

import { debugApps } from "./support/environment.js";

for (const app of debugApps()) {
  for (const theme of ["light", "dark"]) {
    test(`${app.name} portable Vite and Inertia panels ${theme}`, async ({
      page,
      request,
    }, testInfo) => {
      const errors = [];
      page.on("pageerror", (error) => errors.push(error.message));
      const capture = await request.get(`${app.baseURL}/`);
      expect(capture.ok()).toBe(true);
      const tag = capture.headers()["x-debug-tag"];
      expect(tag).toBeTruthy();

      for (const panel of ["vite", "inertia"]) {
        const response = await page.goto(
          `${app.baseURL}/debug/view?tag=${encodeURIComponent(tag)}&panel=${panel}&yii_debug_theme=${theme}`,
        );
        expect(response.status()).toBe(200);
        await expect(
          page.getByRole("heading", {
            level: 1,
            name: panel === "vite" ? "Vite" : "Inertia",
            exact: true,
          }),
        ).toHaveCount(1);
        await expect(page.locator(".yii-debug-table").first()).toBeVisible();
        await expect(
          page.locator(".yii-debug-grid-summary").first(),
        ).not.toBeEmpty();
        expect(
          await page.locator(".yii-debug-table-wrap").count(),
        ).toBeGreaterThan(0);
        if (panel === "vite") {
          await expect(
            page.locator(".yii-debug-panel-group").first(),
          ).toBeVisible();
          await expect(
            page.getByRole("heading", { name: "Build chunks" }).first(),
          ).toBeVisible();
        } else {
          const raw = page
            .locator("details.yii-debug-disclosure")
            .filter({ hasText: "Raw payload" });
          await raw.locator("summary").click();
          await expect(raw.locator("pre")).toBeVisible();
        }
        const accessibility = await new AxeBuilder({ page })
          .withTags(["wcag2a", "wcag2aa"])
          .analyze();
        expect(accessibility.violations).toEqual([]);
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
          ),
        ).toBe(true);
        await page.screenshot({
          path: testInfo.outputPath(`${panel}-${theme}.png`),
          fullPage: true,
        });
      }
      expect(errors).toEqual([]);
    });
  }
}

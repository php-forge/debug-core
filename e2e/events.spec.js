import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

import { debugApps } from "./support/environment.js";
import { expandToolbar, waitForToolbar } from "./support/debug-ui.js";

for (const app of debugApps()) {
  for (const theme of ["light", "dark"]) {
    test(`${app.name} ${theme} event inspector preserves chronology and accessible controls`, async ({
      page,
    }) => {
      await page.goto(app.baseURL);
      const toolbar = await waitForToolbar(page);
      await expandToolbar(toolbar);
      const eventLink = toolbar.locator('a[href*="panel=event"]').first();
      await expect(eventLink).toBeAttached();
      const href = await eventLink.getAttribute("href");
      expect(href).toBeTruthy();
      const url = new URL(href, app.baseURL);
      url.searchParams.set("yii_debug_theme", theme);
      url.searchParams.set("per-page", "10");
      await page.goto(url.href);

      const inspector = page.locator(".yii-debug-event-inspector");
      const items = inspector.locator(".yii-debug-event-item");
      await expect(inspector).toBeVisible();
      await expect(items.first()).toHaveAttribute("id", "event-1");
      await expect(items.first()).toContainText("+0.000 ms");
      const summary = items.first().locator(":scope > summary");
      await summary.focus();
      await page.keyboard.press("Enter");
      await expect(items.first()).toHaveAttribute("open", "");
      await expect(items.first()).toContainText("Listeners / outcome");
      await expect(items.first()).toContainText("Not captured");

      const audit = await new AxeBuilder({ page })
        .include(".yii-debug-event-inspector")
        .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"])
        .analyze();
      expect(audit.violations).toEqual([]);
      const overflow = await inspector.evaluate(
        (element) => element.scrollWidth > element.clientWidth + 1,
      );
      expect(overflow, "The inspector must fit its responsive container").toBe(
        false,
      );

      await page.locator(".yii-debug-event-raw > summary").click();
      await expect(page.locator(".yii-debug-event-raw table")).toBeVisible();
      await expect(
        page.locator('.yii-debug-event-raw input[name="Event[class]"]'),
      ).toBeVisible();

      const secondPage = page.getByRole("link", { name: "2", exact: true });
      await expect(secondPage).toBeVisible();
      await secondPage.click();
      await expect(
        page.locator(".yii-debug-event-item").first(),
      ).toHaveAttribute("id", "event-11");

      const firstGroup = inspector
        .locator(".yii-debug-event-group[href]")
        .first();
      const filterURL = await firstGroup.getAttribute("href");
      expect(filterURL).toBeTruthy();
      await page.goto(new URL(filterURL, app.baseURL).href);
      await expect(page.locator(".yii-debug-event-inspector")).toBeVisible();
      expect(new URL(page.url()).searchParams.has("page")).toBe(false);
      await expect(
        page.locator(".yii-debug-event-item").first(),
      ).toHaveAttribute("id", /event-\d+/);
    });
  }
}

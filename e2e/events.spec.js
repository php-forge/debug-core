import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

import { debugApps } from "./support/environment.js";
import { expandToolbar, waitForToolbar } from "./support/debug-ui.js";

for (const app of debugApps()) {
  for (const theme of ["light", "dark"]) {
    test(`${app.name} ${theme} event table preserves chronology and accessible controls`, async ({
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

      const grid = page.locator(".yii-debug-grid-event");
      const items = grid.locator(".yii-debug-event-item");
      await expect(grid).toBeVisible();
      await expect(grid.locator("table")).toHaveCount(1);
      await expect(items).toHaveCount(10);
      await expect(
        page.locator(".yii-debug-event-raw, .yii-debug-event-flow"),
      ).toHaveCount(0);
      await expect(grid.locator('input[name="Event[class]"]')).toBeVisible();
      await expect(items.first()).toHaveAttribute("id", "event-1");
      await expect(grid.locator("tbody tr").first()).toContainText("+0.000 ms");
      const summary = items.first().locator(":scope > summary");
      const detail = grid.locator("#event-1-detail");
      await expect(detail).toBeHidden();
      await summary.focus();
      await page.keyboard.press("Enter");
      await expect(items.first()).toHaveAttribute("open", "");
      await expect(summary).toHaveAttribute("aria-controls", "event-1-detail");
      await expect(detail).toBeVisible();
      await expect(detail).toContainText("Context");
      await expect(detail).toContainText("Source trace");
      await expect(detail).toContainText("Not captured");
      await expect(detail).not.toContainText("Event name");
      await expect(detail).not.toContainText("Observed at");

      const layout = await detail.evaluate((element) => {
        const context = element.querySelector(".yii-debug-event-context");
        const trace = element.querySelector(".yii-debug-event-trace");
        const table = element.closest("table");
        return {
          width: element.getBoundingClientRect().width,
          tableWidth: table.getBoundingClientRect().width,
          context: context.getBoundingClientRect().toJSON(),
          trace: trace.getBoundingClientRect().toJSON(),
          stacked: window.matchMedia("(width < 768px)").matches,
        };
      });
      expect(Math.abs(layout.width - layout.tableWidth)).toBeLessThan(4);
      if (layout.stacked) {
        expect(layout.trace.top).toBeGreaterThanOrEqual(layout.context.bottom);
      } else {
        expect(layout.trace.left).toBeGreaterThan(layout.context.right);
        expect(layout.trace.top).toEqual(layout.context.top);
      }

      const audit = await new AxeBuilder({ page })
        .include(".yii-debug-grid-event")
        .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"])
        .analyze();
      expect(audit.violations).toEqual([]);
      const overflow = await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth + 1,
      );
      expect(overflow, "The event table must not overflow the document").toBe(
        false,
      );
      await summary.press("Enter");
      await expect(items.first()).not.toHaveAttribute("open", "");
      await expect(detail).toBeHidden();

      const secondPage = page.getByRole("link", { name: "2", exact: true });
      await expect(secondPage).toBeVisible();
      await secondPage.click();
      await expect(
        page.locator(".yii-debug-event-item").first(),
      ).toHaveAttribute("id", "event-11");

      await page.getByText("Group filters", { exact: true }).click();
      const firstGroup = page.locator(".yii-debug-event-group[href]").first();
      const filterURL = await firstGroup.getAttribute("href");
      expect(filterURL).toBeTruthy();
      await page.goto(new URL(filterURL, app.baseURL).href);
      await expect(page.locator(".yii-debug-grid-event")).toBeVisible();
      expect(new URL(page.url()).searchParams.has("page")).toBe(false);
      const classFilter = page.locator('input[name="Event[class]"]');
      await classFilter.fill("__no_such_event__");
      await classFilter.press("Enter");
      await expect(page.locator(".yii-debug-event-item")).toHaveCount(0);
      await expect(page.locator(".yii-debug-active-filters")).toBeVisible();
      await page.goto(new URL(filterURL, app.baseURL).href);
      await expect(
        page.locator(".yii-debug-event-item").first(),
      ).toHaveAttribute("id", /event-\d+/);
    });
  }
}

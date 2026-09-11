import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

// Start the application-owned consumer fixtures documented in the php-forge/debug capture guide.
for (const [host, baseURL] of [
  ["Yii2", "http://127.0.0.1:8092"],
  ["Yii3", "http://127.0.0.1:8093"],
]) {
  for (const theme of ["light", "dark"]) {
    test(`${host} external cache capture, history, second provider ${theme}`, async ({
      page,
      request,
    }, testInfo) => {
      const errors = [];
      page.on("pageerror", (error) => errors.push(error.message));
      const capture = await request.get(`${baseURL}/?state=dense`);
      expect(capture.status()).toBe(200);
      const tag = capture.headers()["x-debug-tag"];
      expect(tag).toBeTruthy();
      await request.get(`${baseURL}/?state=changed`);
      for (const [id, title] of [
        ["cache-operations", "Cache operations"],
        ["independent-second-cache", "Secondary cache"],
      ]) {
        const response = await page.goto(
          `${baseURL}/debug/view?tag=${tag}&panel=${id}&yii_debug_theme=${theme}`,
        );
        expect(response.status()).toBe(200);
        await expect(
          page.locator(
            ".yii-debug-nav-link.is-active .yii-debug-nav-link-icon svg",
          ),
        ).toHaveCount(id === "cache-operations" ? 1 : 0);
        await expect(
          page.getByRole("heading", { name: title, level: 1, exact: true }),
        ).toHaveCount(1);
        await expect(
          page.getByText("Extensions", { exact: true }),
        ).toBeVisible();
        await expect(
          page.getByRole("region", { name: "Operation, Key, Result" }),
        ).toContainText("historical-key0");
        await expect(page.locator("body")).not.toContainText(
          "changed-live-key",
        );
        await expect(page.locator("body")).not.toContainText(
          "private contents",
        );
        const more = page.locator("[data-yii-debug-toggle=cell-more]").last();
        await expect(more).toHaveAttribute("aria-expanded", "false");
        await more.click();
        await expect(more).toHaveAttribute("aria-expanded", "true");
        await more.click();
        await expect(more).toHaveAttribute("aria-expanded", "false");
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
          path: testInfo.outputPath(`${host}-${id}-${theme}.png`),
          fullPage: true,
        });
      }
      const raw = await page.goto(
        `${baseURL}/debug/view?tag=${tag}&panel=cache-operations&_fixture_raw=1&yii_debug_theme=${theme}`,
      );
      expect(raw.status()).toBe(200);
      await expect(page.locator("body")).toContainText("historical-key0");
      await expect(page.locator("body")).not.toContainText("private contents");
      expect(
        (
          await new AxeBuilder({ page })
            .withTags(["wcag2a", "wcag2aa"])
            .analyze()
        ).violations,
      ).toEqual([]);
      const empty = await request.get(`${baseURL}/?state=empty`);
      await page.goto(
        `${baseURL}/debug/view?tag=${empty.headers()["x-debug-tag"]}&panel=cache-operations&yii_debug_theme=${theme}`,
      );
      await expect(
        page.getByRole("heading", { name: /^No cache operations/ }),
      ).toBeVisible();
      expect(errors).toEqual([]);
    });
  }
}

import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

for (const [host, baseURL] of [
  ["Yii2", "http://127.0.0.1:8092"],
  ["Yii3", "http://127.0.0.1:8093"],
]) {
  for (const theme of ["light", "dark"]) {
    test(`${host} PSR-14 Vite and Inertia capture ${theme}`, async ({
      page,
      request,
    }, testInfo) => {
      const errors = [];
      page.on("pageerror", (error) => errors.push(error.message));
      const capture = await request.get(`${baseURL}/`);
      expect(capture.status()).toBe(200);
      const tag = capture.headers()["x-debug-tag"];
      expect(tag).toBeTruthy();
      expect((await request.get(`${baseURL}/?state=changed`)).status()).toBe(
        200,
      );
      for (const panel of ["vite", "inertia"]) {
        const response = await page.goto(
          `${baseURL}/debug/view?tag=${tag}&panel=${panel}&yii_debug_theme=${theme}`,
        );
        expect(response.status()).toBe(200);
        await expect(
          page.getByRole("heading", {
            level: 1,
            name: panel === "vite" ? "Vite" : "Inertia",
            exact: true,
          }),
        ).toBeVisible();
        await expect(
          page.getByText("Extensions", { exact: true }),
        ).toBeVisible();
        await expect(page.locator("body")).not.toContainText("private-prop");
        await expect(page.locator("body")).not.toContainText("ChangedLivePage");
        await expect(page.locator("body")).not.toContainText("changed-live.js");
        if (panel === "vite") {
          await expect(page.locator("body")).toContainText("observed-entry.js");
          await expect(
            page.getByRole("heading", { name: "Build chunks" }),
          ).toBeVisible();
        } else {
          await expect(page.locator("body")).toContainText("ObservedPage");
          await expect(page.locator("body")).toContainText("resolved-value");
          const more = page.locator("[data-yii-debug-toggle=cell-more]").last();
          await expect(more).toHaveAttribute("aria-expanded", "false");
          await more.click();
          await expect(more).toHaveAttribute("aria-expanded", "true");
          const raw = page
            .locator("details.yii-debug-disclosure")
            .filter({ hasText: "Raw payload" });
          await raw.locator("summary").click();
          await expect(raw.locator("pre")).toBeVisible();
        }
        expect(
          (
            await new AxeBuilder({ page })
              .withTags(["wcag2a", "wcag2aa"])
              .analyze()
          ).violations,
        ).toEqual([]);
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
          ),
        ).toBe(true);
        await page.screenshot({
          path: testInfo.outputPath(`${host}-${panel}-${theme}.png`),
          fullPage: true,
        });
      }
      const navigation = await request.get(`${baseURL}/?state=location`);
      expect(navigation.status()).toBe(200);
      await page.goto(
        `${baseURL}/debug/view?tag=${navigation.headers()["x-debug-tag"]}&panel=inertia&yii_debug_theme=${theme}`,
      );
      await expect(
        page.getByRole("heading", { name: /^External location visit/ }),
      ).toBeVisible();
      await expect(page.locator("body")).not.toContainText("Version conflict");
      await expect(page.locator("body")).not.toContainText("token=secret");
      const empty = await request.get(`${baseURL}/?state=empty`);
      expect(empty.status()).toBe(200);
      await page.goto(
        `${baseURL}/debug/view?tag=${empty.headers()["x-debug-tag"]}&panel=vite&yii_debug_theme=${theme}`,
      );
      await expect(
        page.getByRole("heading", { name: /^No Vite integrations captured/ }),
      ).toBeVisible();
      expect(errors).toEqual([]);
    });
  }
}

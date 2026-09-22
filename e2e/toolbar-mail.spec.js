import { expect, test } from "@playwright/test";

import {
  collectRuntimeDiagnostics,
  expandToolbar,
  expectNoRuntimeDiagnostics,
  waitForToolbar,
} from "./support/debug-ui.js";
import { debugApps, fixtureTag } from "./support/environment.js";

const postTag = fixtureTag("prgPost");
const getTag = fixtureTag("prgGet");
const postTagPattern = new RegExp(`[?&]tag=${postTag}(?:&|$)`);

for (const app of debugApps()) {
  test(`${app.name} Mail chip label opens the capture that sent the mail`, async ({
    page,
  }) => {
    test.skip(
      app.name === "yii3",
      "Yii3 has no Mail panel until the Phase 5 port.",
    );

    const diagnostics = collectRuntimeDiagnostics(page);

    // Serve the page toolbar from the seeded GET that follows the mail-sending POST; later AJAX loads are untouched.
    await page.route(
      /toolbar-data/,
      (route) => {
        const url = new URL(route.request().url());

        url.searchParams.set("tag", getTag);

        return route.continue({ url: url.href });
      },
      { times: 1 },
    );

    const response = await page.goto(app.baseURL, {
      waitUntil: "domcontentloaded",
    });

    expect(response?.ok(), `${app.baseURL} must load successfully`).toBe(true);

    const toolbar = await waitForToolbar(page);
    await expandToolbar(toolbar);

    const mail = toolbar.getByRole("group", { name: "Mail" });
    const label = mail.locator(".panel-link");
    const badge = mail.locator(".metric");

    await expect(label).toHaveAttribute("data-debug-url", postTagPattern);
    await expect(badge).toHaveAttribute(
      "data-debug-url",
      (await label.getAttribute("data-debug-url")) ?? "",
    );

    await label.click();

    const frame = toolbar.locator("iframe[title='Yii debug panel']");

    await expect(frame).toHaveAttribute("src", postTagPattern);
    await expect(
      frame.contentFrame().locator(".yii-debug-page").first(),
    ).toContainText("Toolbar PRG fixture message 0");
    await expectNoRuntimeDiagnostics(diagnostics, `${app.name} Mail chip`);
  });
}

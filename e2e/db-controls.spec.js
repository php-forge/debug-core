import { readFile, readdir } from "node:fs/promises";

import { expect, test } from "@playwright/test";

const assets = new Map([
  ["/db.js", new URL("../resources/assets/dist/js/db.min.js", import.meta.url)],
  [
    "/dom.min.js",
    new URL("../resources/assets/dist/js/dom.min.js", import.meta.url),
  ],
  [
    "/deep-links.js",
    new URL("../resources/src/core/deep-links.js", import.meta.url),
  ],
  [
    "/debug.css",
    new URL("../resources/assets/dist/css/debug.min.css", import.meta.url),
  ],
]);

const fonts = new URL("../resources/assets/dist/fonts/", import.meta.url);
for (const name of await readdir(fonts)) {
  assets.set(`/fonts/${name}`, new URL(name, fonts));
}

function databaseFixture(theme, filtered) {
  const rows = ["SELECT", "SELECT", "SELECT", "UPDATE"]
    .map(
      (verb, index) => `
    <tr><td><span class="yii-debug-db-type">${verb}</span></td>
      <td>10:21:10.956</td><td>0.2 ms</td><td>0 rows</td><td>1</td><td>
      <div class="yii-debug-db-sql" ${index < 3 ? 'data-yii-debug-n1-group="group-a"' : ""}
        ${index === 0 ? 'id="group-a"' : ""}>${verb} name FROM demo_items WHERE id = ${index + 1}</div>
      ${index < 3 ? '<a class="yii-debug-db-n1-row-link" href="#group-a" data-yii-debug-n1-filter="group-a">Potential N+1 · 3 similar</a>' : ""}
    </td></tr>`,
    )
    .join("");

  return `<!doctype html><html lang="en" data-yii-debug-theme="${theme}"><head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="/debug.css"><title>Database controls regression</title>
    </head><body class="yii-debug" data-theme="${theme}"><main id="yii-debug-main">
    ${
      filtered
        ? `<div class="yii-debug-active-filters" role="group" aria-label="Active filters">
      <span class="yii-debug-active-filters-label">1 filter active</span>
      <span class="yii-debug-active-filters-list"><a class="yii-debug-active-filter-pill" href="/?panel=db&sort=-duration">
      <span class="yii-debug-active-filter-attr">query</span><span class="yii-debug-active-filter-sep">:</span>
      <span class="yii-debug-active-filter-value">demo_items</span><span class="yii-debug-active-filter-x">×</span></a></span>
      <a class="yii-debug-active-filters-clear" href="/?panel=db&sort=-duration" aria-label="Clear all active filters">Clear all</a></div>`
        : ""
    }
    <div class="yii-debug-db-n1-summary"><div class="yii-debug-db-n1-heading">
      <strong>Potential N+1 queries</strong><a href="#" data-yii-debug-n1-clear hidden>Show all queries</a>
    </div><a class="yii-debug-db-n1-link" href="#group-a" data-yii-debug-n1-filter="group-a">3 similar queries</a>
    <span data-yii-debug-n1-status aria-live="polite"></span></div>
    <div class="yii-debug-grid yii-debug-grid-db"><div class="yii-debug-table-wrap">
    <table class="yii-debug-table"><thead><tr>
    <th>Type</th><th>Time</th><th>Duration</th><th>Rows</th><th>Dup</th><th>Query</th></tr>
    <tr class="filters">
    <td><select class="yii-debug-select" aria-label="Filter by Type">
    <option></option><option>SELECT</option><option>UPDATE</option></select></td>
    <td></td><td></td><td></td><td></td>
    <td><input class="yii-debug-input" aria-label="Filter by Query"></td>
    </tr></thead><tbody>${rows}</tbody></table></div></div></main>
    <script type="module">
      import '/db.js';
      import { initSectionPermalinks } from '/deep-links.js';
      initSectionPermalinks(document, window);
      document.body.dataset.ready = 'true';
    </script></body></html>`;
}

for (const theme of ["light", "dark"]) {
  test(`${theme} database controls fit and clear N+1 without reloading`, async ({
    page,
  }, testInfo) => {
    const errors = [];
    page.on("pageerror", (error) => errors.push(error.message));
    await page.route("http://debug.test/**", async (route) => {
      const path = new URL(route.request().url()).pathname;
      const asset = assets.get(path);
      await route.fulfill({
        contentType: path.endsWith(".woff2")
          ? "font/woff2"
          : path === "/debug.css"
            ? "text/css"
            : asset
              ? "text/javascript"
              : "text/html",
        body: asset
          ? await readFile(asset)
          : databaseFixture(
              theme,
              new URL(route.request().url()).searchParams.has("Db[query]"),
            ),
      });
    });
    await page.goto("http://debug.test/?panel=db&sort=-duration#group-a");
    await expect(page.locator("body")).toHaveAttribute("data-ready", "true");
    await expect(page.locator("#group-a")).toHaveClass(
      /yii-debug-deep-link-target/,
    );

    await page.evaluate(() => document.fonts.ready);
    const select = page.getByLabel("Filter by Type");
    await select.selectOption("SELECT");
    await select.focus();
    const spacing = await select.evaluate((element) => {
      const style = getComputedStyle(element);
      const context = document.createElement("canvas").getContext("2d");
      context.font = style.font;
      return {
        width: element.getBoundingClientRect().width,
        text: Math.max(
          ...Array.from(
            element.options,
            (option) => context.measureText(option.text).width,
          ),
        ),
        left: parseFloat(style.paddingLeft),
        right: parseFloat(style.paddingRight),
        outline: style.outlineWidth,
        offset: style.outlineOffset,
        shadow: style.boxShadow,
      };
    });
    expect(spacing.width).toBeGreaterThanOrEqual(
      spacing.text + spacing.left + spacing.right + 16,
    );
    expect(spacing.width).toBeLessThanOrEqual(
      spacing.text + spacing.left + spacing.right + 32,
    );
    expect(spacing.left).toBe(spacing.right);
    expect(spacing.outline).toBe("2px");
    expect(spacing.offset).toBe("1px");
    expect(spacing.shadow).toBe("none");
    await select.screenshot({
      path: testInfo.outputPath("sql-type-focus.png"),
    });

    const rowTags = page.locator(".yii-debug-db-n1-row-link");
    const clear = page.locator("[data-yii-debug-n1-clear]");
    const banner = page.getByRole("group", { name: "Active filters" });
    const visibleRows = page.locator(".yii-debug-grid-db tbody tr:visible");
    await rowTags.first().click();
    await expect(visibleRows).toHaveCount(3);
    await expect(clear).toBeVisible();
    await expect(banner).toContainText("1 filter active");
    await expect(banner).toContainText("3 similar queries");
    expect(
      await banner.evaluate((element) =>
        element.nextElementSibling.classList.contains(
          "yii-debug-db-n1-summary",
        ),
      ),
    ).toBe(true);
    await rowTags.nth(1).focus();
    await rowTags.nth(1).press("Enter");
    await expect(rowTags.nth(1)).toBeFocused();
    await expect(visibleRows).toHaveCount(4);
    await expect(clear).toBeHidden();
    await expect(banner).toHaveCount(0);
    await expect(page.locator(".yii-debug-deep-link-target")).toHaveCount(0);
    expect(page.url()).toBe("http://debug.test/?panel=db&sort=-duration");

    await page.locator(".yii-debug-db-n1-link").click();
    await expect(visibleRows).toHaveCount(3);
    await banner
      .getByRole("link", { name: "Clear all active filters" })
      .click();
    await expect(visibleRows).toHaveCount(4);
    await expect(page.locator('[aria-current="true"]')).toHaveCount(0);
    expect(page.url()).toBe("http://debug.test/?panel=db&sort=-duration");
    await expect(select).toHaveValue("SELECT");
    await expect(banner).toHaveCount(0);
    await expect(page.locator(".yii-debug-db-n1-link")).toBeFocused();

    await page.locator(".yii-debug-db-n1-link").press("Enter");
    const groupPill = banner.getByRole("link", {
      name: "Remove N+1: 3 similar queries filter",
    });
    await groupPill.focus();
    await groupPill.press("Enter");
    await expect(banner).toHaveCount(0);
    await expect(page.locator(".yii-debug-db-n1-link")).toBeFocused();

    await page.goto(
      "http://debug.test/?panel=db&sort=-duration&Db%5Bquery%5D=demo_items",
    );
    await expect(page.locator("body")).toHaveAttribute("data-ready", "true");
    await rowTags.first().click();
    await expect(banner).toHaveCount(1);
    await expect(banner).toContainText("2 filters active");
    await expect(banner).toContainText("demo_items");
    expect(
      await banner.evaluate((element) =>
        element.nextElementSibling.classList.contains(
          "yii-debug-db-n1-summary",
        ),
      ),
    ).toBe(true);
    await groupPill.focus();
    await groupPill.press("Enter");
    await expect(page.locator(".yii-debug-db-n1-link")).toBeFocused();
    await expect(banner).toContainText("1 filter active");
    await expect(banner.locator("[data-yii-debug-n1-pill]")).toHaveCount(0);
    await expect(visibleRows).toHaveCount(4);
    expect(new URL(page.url()).searchParams.get("Db[query]")).toBe(
      "demo_items",
    );

    await rowTags.first().click();
    await expect(banner).toContainText("2 filters active");
    await banner
      .getByRole("link", { name: "Clear all active filters" })
      .click();
    await expect(page.locator("body")).toHaveAttribute("data-ready", "true");
    await expect(banner).toHaveCount(0);
    await expect(visibleRows).toHaveCount(4);
    expect(page.url()).toBe("http://debug.test/?panel=db&sort=-duration");
    expect(errors).toEqual([]);
  });
}

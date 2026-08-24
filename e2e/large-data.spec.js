import { readFileSync } from "node:fs";

import { expect, test } from "@playwright/test";

const budget = JSON.parse(
  readFileSync(
    new URL("../tools/quality/performance-budget.json", import.meta.url),
    "utf8",
  ),
).largeData;
const stylesheet = new URL(
  "../resources/assets/dist/css/debug.min.css",
  import.meta.url,
);

test.describe.configure({ mode: "serial" });

for (const rowCount of [50, 1000, 5000]) {
  test(`representative debug grid remains bounded at ${rowCount} rows`, async ({
    page,
  }, testInfo) => {
    await page.setContent(`
      <!doctype html>
      <html lang="en" data-yii-debug-theme="light">
        <head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
        <body class="yii-debug">
          <main class="yii-debug-page">
            <section class="yii-debug-main yii-debug-card">
              <h1>Large-data quality fixture</h1>
              <label for="quality-filter">Filter rows</label>
              <input id="quality-filter" class="yii-debug-input" type="search">
              <div class="yii-debug-table-wrap">
                <table class="yii-debug-table">
                  <thead><tr><th>ID</th><th>Category</th><th>Message</th><th>Duration</th></tr></thead>
                  <tbody id="quality-rows"></tbody>
                </table>
              </div>
            </section>
          </main>
        </body>
      </html>
    `);
    await page.addStyleTag({ path: stylesheet.pathname });

    const metrics = await page.evaluate((count) => {
      const body = document.querySelector("#quality-rows");
      const filter = document.querySelector("#quality-filter");
      const renderStart = performance.now();
      let html = "";

      for (let index = 0; index < count; index += 1) {
        const category = `fixture-${index % 12}`;
        html += `<tr data-search="${category} row-${index}"><td>${index + 1}</td><td>${category}</td><td>Deterministic quality fixture row ${index}</td><td>${(index % 30) + 0.25} ms</td></tr>`;
      }

      body.innerHTML = html;
      const renderMs = performance.now() - renderStart;
      const filterStart = performance.now();
      const needle = "fixture-7";

      filter.value = needle;
      for (const row of body.rows) {
        row.hidden = !row.dataset.search.includes(needle);
      }

      const filterMs = performance.now() - filterStart;

      return {
        rowCount: body.rows.length,
        visibleRows: Array.from(body.rows).filter((row) => !row.hidden).length,
        renderMs,
        filterMs,
        domNodes: document.getElementsByTagName("*").length,
      };
    }, rowCount);
    const threshold = budget[String(rowCount)];

    await testInfo.attach(`large-data-${rowCount}.json`, {
      body: Buffer.from(JSON.stringify(metrics, null, 2)),
      contentType: "application/json",
    });

    expect(metrics.rowCount).toBe(rowCount);
    expect(metrics.visibleRows).toBeGreaterThan(0);
    expect(metrics.renderMs).toBeLessThanOrEqual(threshold.maxRenderMs);
    expect(metrics.filterMs).toBeLessThanOrEqual(threshold.maxFilterMs);
    expect(metrics.domNodes).toBeLessThanOrEqual(threshold.maxDomNodes);
  });
}

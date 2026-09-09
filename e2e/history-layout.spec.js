import { expect, test } from "@playwright/test";

import { waitForStableUI } from "./support/debug-ui.js";

const stylesheet = new URL(
  "../resources/assets/dist/css/debug.min.css",
  import.meta.url,
);

function columns(query, mail) {
  return [
    ["#", "yii-debug-col-num", ""],
    ["ID", "yii-debug-col-id", "tag"],
    ["Time", "", ""],
    ["Duration", "", ""],
    ["Memory", "", ""],
    ["IP", "yii-debug-col-ip", "ip"],
    ...(query ? [["Query", "yii-debug-col-num", "sqlCount"]] : []),
    ...(mail
      ? [["Mail", "yii-debug-col-num yii-debug-col-mail", "mailCount"]]
      : []),
    ["Method", "", "method"],
    ["AJAX", "", "ajax"],
    ["URL", "", "url"],
  ];
}

function filter(attribute) {
  if (!attribute) {
    return "";
  }

  if (attribute === "method" || attribute === "ajax") {
    return `<select name="Debug[${attribute}]"><option></option><option>COMMAND</option></select>`;
  }

  const className = attribute === "tag" ? "yii-debug-col-id-input" : "";

  return `<input class="${className}" name="Debug[${attribute}]">`;
}

function rows(definitions, count) {
  return Array.from({ length: count }, (_, index) => {
    const url = `http://localhost/example/${"long-path-segment/".repeat(index)}`;
    const values = {
      "#": index + 1,
      ID: `<a class="yii-debug-tag-link" href="#">${"a".repeat(32)}</a>`,
      Time: "18:52:23",
      Duration: '<span class="yii-debug-gauge">1234 ms</span>',
      Memory: '<span class="yii-debug-gauge">123.456 MB</span>',
      IP: index ? "2001:0db8:85a3:0000:0000:8a2e:0370:7334" : "::1",
      Query: "3",
      Mail: "0",
      Method: "COMMAND",
      AJAX: "Yes",
      URL: `<span class="yii-debug-url-cell" title="${url}">${url}</span>`,
    };

    return `<tr>${definitions
      .map(
        ([label, className]) =>
          `<td class="${className}">${values[label]}</td>`,
      )
      .join("")}</tr>`;
  }).join("");
}

for (const [adapter, query, mail] of [
  ["Yii2", true, true],
  ["Yii2 without Mail", true, false],
  ["Yii2 without Database", false, true],
  ["Yii3", false, false],
]) {
  for (const theme of ["light", "dark"]) {
    test(`${adapter} History keeps column widths stable in ${theme} theme`, async ({
      page,
    }) => {
      const definitions = columns(query, mail);
      const filterClass = adapter === "Yii3" ? "yii-debug-filter-cell" : "";

      await page.setContent(`
        <!doctype html>
        <html lang="en" data-yii-debug-theme="${theme}">
          <body class="yii-debug">
            <main class="yii-debug-page">
              <section class="yii-debug-main yii-debug-card">
                <div class="yii-debug-grid yii-debug-grid-history">
                  <div class="yii-debug-table-wrap">
                    <table class="yii-debug-table">
                      <thead>
                        <tr>${definitions.map(([label, className]) => `<th class="${className}">${label}</th>`).join("")}</tr>
                        <tr class="${adapter === "Yii3" ? "" : "filters"}">${definitions.map(([, className, attribute]) => `<td class="${className} ${filterClass}">${filter(attribute)}</td>`).join("")}</tr>
                      </thead>
                      <tbody>${rows(definitions, 10)}</tbody>
                    </table>
                  </div>
                </div>
              </section>
            </main>
          </body>
        </html>
      `);
      await page.addStyleTag({ path: stylesheet.pathname });
      await waitForStableUI(page);

      const headers = page.locator("thead th");
      const widths = () =>
        headers.evaluateAll((cells) =>
          cells.map((cell) => cell.getBoundingClientRect().width),
        );
      const baseline = await widths();
      const viewport = page.viewportSize();

      expect(baseline.slice(0, 5)).toEqual([36, 276, 80, 100, 100]);
      expect(baseline[5]).toBe(viewport.width <= 1366 ? 0 : 112);
      expect(baseline.slice(-3, -1)).toEqual([128, 84]);

      // Replacing the result rows models server-rendered filtering and paging.
      for (const count of [50, 1, 0, 10]) {
        await page.locator("tbody").evaluate(
          (body, html) => {
            body.innerHTML = html;
          },
          count
            ? rows(definitions, count)
            : `<tr><td colspan="${definitions.length}">No results found.</td></tr>`,
        );
        expect(
          await widths(),
          `${count} results must preserve every column`,
        ).toEqual(baseline);

        const overflow = await page.evaluate(() => ({
          viewport: document.documentElement.clientWidth,
          document: document.documentElement.scrollWidth,
          previews: [...document.querySelectorAll(".yii-debug-url-cell")].every(
            (element) =>
              element.getBoundingClientRect().right <=
              element.parentElement.getBoundingClientRect().right,
          ),
        }));

        expect(overflow.document).toBeLessThanOrEqual(overflow.viewport);
        expect(overflow.previews).toBe(true);
      }
    });
  }
}

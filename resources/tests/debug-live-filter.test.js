// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test } from "vitest";

import { bootDebugPage, fire, press } from "./debug-page-harness.js";

const ROWS =
  "<tbody><tr><td>SELECT 1</td></tr><tr><td>UPDATE users</td></tr></tbody>";
const TARGET =
  '<table data-yii-debug-filter-target data-yii-debug-filter-unit="queries">' +
  ROWS +
  "</table>";

function filtered(page, value) {
  const input = page.document.querySelector("[data-yii-debug-filter]");

  input.value = value;
  fire(input, "input");

  return input;
}

function status(page) {
  return page.document.querySelector("[data-yii-debug-filter-status]");
}

test("typing hides the rows that do not match and announces the count", async () => {
  const page = await bootDebugPage({
    body:
      '<header class="yii-debug-section-header">' +
      '<input data-yii-debug-filter id="db-filter"></header>' +
      TARGET,
  });
  const input = filtered(page, "select");
  const rows = page.document.querySelectorAll("tbody tr");

  assert.equal(rows[0].hidden, false, "Matching row must stay.");
  assert.equal(rows[1].hidden, true, "Other rows must go.");
  assert.equal(
    status(page).textContent,
    "1 of 2 queries shown.",
    "Count must name the filtered unit.",
  );
  assert.equal(
    status(page).id,
    "db-filter-status",
    "Status id must derive from the field.",
  );
  assert.equal(
    input.getAttribute("aria-describedby"),
    "db-filter-status",
    "Field must point at its own status.",
  );
  assert.equal(
    status(page).parentElement.className,
    "yii-debug-section-header",
    "Status must live in the section header.",
  );
});

test("the status region is reused on every keystroke", async () => {
  const page = await bootDebugPage({
    body:
      '<header class="yii-debug-section-header">' +
      '<input data-yii-debug-filter id="db-filter"></header>' +
      TARGET,
  });

  filtered(page, "select");
  filtered(page, "");

  assert.equal(
    page.document.querySelectorAll("[data-yii-debug-filter-status]").length,
    1,
    "Only one live region may exist.",
  );
  assert.equal(
    status(page).textContent,
    "2 of 2 queries shown.",
    "Clearing must announce the full set.",
  );
});

test("an unnamed filter gets a numbered status region", async () => {
  const page = await bootDebugPage({
    body:
      '<header class="yii-debug-section-header">' +
      "<input data-yii-debug-filter></header>" +
      TARGET,
  });

  filtered(page, "select");

  assert.equal(
    status(page).id,
    "yii-debug-filter-status-1",
    "Status id must fall back to a sequence.",
  );
});

test("a filter already describing its status is not described twice", async () => {
  const page = await bootDebugPage({
    body:
      '<header class="yii-debug-section-header">' +
      '<input data-yii-debug-filter aria-describedby="yii-debug-filter-status-1">' +
      "</header>" +
      TARGET,
  });
  const input = filtered(page, "select");

  assert.equal(
    input.getAttribute("aria-describedby"),
    "yii-debug-filter-status-1",
    "Description list must stay free of duplicates.",
  );
});

test("a filter with no target is ignored", async () => {
  const page = await bootDebugPage({
    body: "<input data-yii-debug-filter>",
  });

  filtered(page, "select");

  assert.equal(status(page), null, "Nothing to filter, nothing to announce.");
});

test("input from a plain field is ignored", async () => {
  const page = await bootDebugPage({
    body: '<input id="search">' + TARGET,
  });
  const input = page.document.getElementById("search");

  input.value = "select";
  fire(input, "input");

  assert.equal(status(page), null, "Only marked filters drive the rows.");
  assert.equal(
    page.document.querySelector("tbody tr").hidden,
    false,
    "Unmarked fields must not touch the target.",
  );
});

test("a scoped filter anchors its status next to itself", async () => {
  const page = await bootDebugPage({
    body:
      "<div data-yii-debug-filter-scope><input data-yii-debug-filter>" +
      TARGET +
      "</div>",
  });

  filtered(page, "select");

  assert.equal(
    status(page).parentElement.getAttribute("data-yii-debug-filter-scope"),
    "",
    "Status must join the scope that owns the filter.",
  );
});

test("Escape empties a live filter and keeps the caret in it", async () => {
  const page = await bootDebugPage({
    body:
      '<header class="yii-debug-section-header">' +
      '<input data-yii-debug-filter id="db-filter"></header>' +
      TARGET,
  });
  const input = filtered(page, "select");
  const event = press(input, "Escape");

  assert.equal(event.defaultPrevented, true, "Escape must be consumed.");
  assert.equal(input.value, "", "Field must be emptied.");
  assert.equal(page.document.activeElement, input, "Caret must stay put.");
  assert.equal(
    status(page).textContent,
    "2 of 2 queries shown.",
    "Full set must be announced again.",
  );
  assert.equal(
    page.document.querySelectorAll("tbody tr")[1].hidden,
    false,
    "Every row must come back.",
  );
});

test("Escape on an empty live filter is left to the page", async () => {
  const page = await bootDebugPage({
    body:
      '<header class="yii-debug-section-header">' +
      '<input data-yii-debug-filter id="db-filter"></header>' +
      TARGET,
  });
  const event = press(
    page.document.querySelector("[data-yii-debug-filter]"),
    "Escape",
  );

  assert.equal(event.defaultPrevented, false, "Nothing to clear.");
});

test("Escape empties a GridView filter cell", async () => {
  const page = await bootDebugPage({
    body:
      '<table class="yii-debug-grid"><thead><tr class="filters"><td>' +
      '<input name="Debug[method]" value="GET"></td></tr></thead></table>',
  });
  const input = page.document.querySelector(".yii-debug-grid .filters input");
  const event = press(input, "Escape");

  assert.equal(event.defaultPrevented, true, "Escape must be consumed.");
  assert.equal(input.value, "", "Cell must be emptied.");
  assert.equal(
    page.navigations.length,
    0,
    "Clearing an unapplied filter must not reload.",
  );
});

test("a filter control without a parent anchors its status on itself", async () => {
  const page = await bootDebugPage({
    html:
      "<!doctype html><html data-yii-debug-filter data-yii-debug-filter-scope>" +
      "<head></head><body>" +
      TARGET +
      "</body></html>",
  });

  press(page.document.documentElement, "Escape");

  assert.equal(
    status(page).parentElement,
    page.document.documentElement,
    "Status must fall back to the control itself.",
  );
  assert.equal(
    status(page).textContent,
    "2 of 2 queries shown.",
    "Cleared filter must announce the full set.",
  );
});

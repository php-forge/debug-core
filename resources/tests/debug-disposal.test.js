// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test } from "vitest";

import { bootDebugPage, fire } from "./debug-page-harness.js";

const LISTED = "https://debug.test/debug/index?page=3";

const GRID =
  '<select data-yii-debug-pagesize><option value="">Default</option>' +
  '<option value="50">50</option></select>' +
  '<div class="yii-debug-grid"><div class="filters">' +
  '<input name="Debug[method]"></div></div>';

/**
 * Resolves the bridge inside the registry the booted page was built from, so
 * the test disposes the very instance that page initialized.
 */
function gridNavigation() {
  return import("../src/core/grid-navigation.js");
}

test("disposing the grid bridge drops the scheduled apply and the listeners", async () => {
  const page = await bootDebugPage({ body: GRID, url: LISTED });
  const bridge = await gridNavigation();
  const input = page.document.querySelector('[name="Debug[method]"]');

  input.value = "GET";
  fire(input, "input");

  bridge.disposeGridNavigation();

  const select = page.document.querySelector("select");

  select.value = "50";
  fire(select, "change");
  fire(input, "change");

  assert.deepEqual(
    page.navigations,
    [],
    "A released bridge must not navigate.",
  );
  assert.equal(
    page.document.documentElement.getAttribute("aria-busy"),
    null,
    "No navigation may be announced.",
  );

  bridge.disposeGridNavigation();
});

// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test, vi } from "vitest";

import { bootDebugPage, fire, press, settle } from "./debug-page-harness.js";

/**
 * The Database panel feature is the failure fixture: it is the only module the
 * bootstrap loads on demand here, and its import must reject for the degraded
 * callout to appear. No other scenario in this file ships a panel marker.
 */
vi.doMock("../src/panels/db.js", () => {
  throw new Error("Chunk load failed.");
});

const CELL_MORE =
  '<div class="yii-debug-cell-more">' +
  '<div class="yii-debug-cell-more-body">Full value</div>' +
  '<button type="button" data-yii-debug-toggle="cell-more"></button>' +
  "</div>";
const CELL_MORE_OPEN =
  '<div class="yii-debug-cell-more is-open">' +
  '<div class="yii-debug-cell-more-body">Full value</div>' +
  '<button type="button" data-yii-debug-toggle="cell-more"></button>' +
  "</div>";
const COPY_CONTROL =
  '<button type="button" data-yii-debug-copy data-yii-debug-copy-value="SELECT 1">' +
  "<span data-yii-debug-copy-label>Copy</span></button>";
const DB_MARKER = '<button class="yii-debug-db-explain-toggle"></button>';
const TABS =
  '<div class="yii-debug-tabs" role="tablist">' +
  '<a role="tab" href="#panel-one" aria-controls="panel-one" ' +
  'data-yii-debug-toggle="tab">One</a>' +
  '<a role="tab" href="#panel-two" aria-controls="panel-two" ' +
  'data-yii-debug-toggle="tab">Two</a></div>' +
  '<div><div class="yii-debug-tab-panel" id="panel-one">One</div>' +
  '<div class="yii-debug-tab-panel" id="panel-two" hidden>Two</div></div>';

test("cell-more controls describe the body they disclose", async () => {
  const page = await bootDebugPage({ body: CELL_MORE + CELL_MORE_OPEN });
  const controls = page.document.querySelectorAll(
    '[data-yii-debug-toggle="cell-more"]',
  );
  const bodies = page.document.querySelectorAll(".yii-debug-cell-more-body");

  assert.equal(
    controls[0].getAttribute("aria-controls"),
    bodies[0].id,
    "Control must point at its own body.",
  );
  assert.equal(controls[0].textContent, "Show more", "Closed box invites.");
  assert.equal(controls[1].textContent, "Show less", "Open box retracts.");
  assert.notEqual(bodies[0].id, bodies[1].id, "Generated ids must be unique.");
});

test("a cell-more box missing its body or its control is left alone", async () => {
  const page = await bootDebugPage({
    body:
      '<div class="yii-debug-cell-more">' +
      '<button type="button" data-yii-debug-toggle="cell-more">Raw</button>' +
      "</div>" +
      '<div class="yii-debug-cell-more">' +
      '<div class="yii-debug-cell-more-body">Orphan</div></div>',
  });
  const control = page.document.querySelector(
    '[data-yii-debug-toggle="cell-more"]',
  );

  assert.equal(control.textContent, "Raw", "Half-rendered box must be left.");
  assert.equal(
    control.getAttribute("aria-controls"),
    null,
    "No body means nothing to reference.",
  );
  assert.equal(
    page.document.querySelector(".yii-debug-cell-more-body").id,
    "",
    "A body without a control needs no id.",
  );
});

test("a server-rendered body id is kept", async () => {
  const page = await bootDebugPage({
    body:
      '<div class="yii-debug-cell-more">' +
      '<div class="yii-debug-cell-more-body" id="db-row-7">Full value</div>' +
      '<button type="button" data-yii-debug-toggle="cell-more"></button>' +
      "</div>",
  });

  assert.equal(
    page.document
      .querySelector('[data-yii-debug-toggle="cell-more"]')
      .getAttribute("aria-controls"),
    "db-row-7",
    "Server id must be reused.",
  );
});

test("generated body ids skip ids already taken on the page", async () => {
  const page = await bootDebugPage({
    body: '<span id="yii-debug-cell-more-1"></span>' + CELL_MORE,
  });

  assert.equal(
    page.document.querySelector(".yii-debug-cell-more-body").id,
    "yii-debug-cell-more-2",
    "Collision must be resolved by the next sequence number.",
  );
});

test("panel headings receive a permalink", async () => {
  const page = await bootDebugPage({
    body: '<main id="yii-debug-main"><h2>Database queries</h2></main>',
  });
  const heading = page.document.querySelector("h2");

  assert.equal(
    heading.id,
    "yii-debug-section-database-queries",
    "Heading must get a stable fragment id.",
  );
  assert.equal(
    heading.querySelector(".yii-debug-heading-permalink").getAttribute("href"),
    "#yii-debug-section-database-queries",
    "Permalink must target the heading.",
  );
});

test("the tab named by the fragment is revealed on load", async () => {
  const page = await bootDebugPage({
    body: TABS,
    url: "https://debug.test/debug/index#panel-two",
  });

  assert.equal(
    page.document.getElementById("panel-two").hidden,
    false,
    "Fragment panel must be shown.",
  );
  assert.equal(
    page.document.getElementById("panel-one").hidden,
    true,
    "Sibling panel must be hidden.",
  );
});

test("copy controls hand their value to the clipboard", async () => {
  const copied = [];
  const page = await bootDebugPage({
    body: COPY_CONTROL,
    clipboard: {
      writeText(text) {
        copied.push(text);

        return Promise.resolve();
      },
    },
  });
  const control = page.document.querySelector("[data-yii-debug-copy]");

  fire(control, "click");

  await settle(page.window);

  assert.deepEqual(copied, ["SELECT 1"], "Literal value must be copied.");
  assert.equal(
    control.getAttribute("aria-label"),
    "Copied",
    "Control must confirm the copy.",
  );
});

test("a host without a navigator reports the clipboard as unavailable", async () => {
  const page = await bootDebugPage({
    body: COPY_CONTROL,
    navigator: "none",
  });
  const control = page.document.querySelector("[data-yii-debug-copy]");

  fire(control, "click");

  await settle(page.window);

  assert.equal(
    control.getAttribute("aria-label"),
    "Copy unavailable",
    "Missing navigator must degrade, not throw.",
  );
});

test("a panel feature that fails to load raises a callout", async () => {
  const page = await bootDebugPage({
    body: '<main id="yii-debug-main"><p>Queries</p></main>' + DB_MARKER,
  });

  await settle(page.window);

  const alert = page.document.querySelector('[role="alert"]');

  assert.equal(
    page.document.documentElement.getAttribute(
      "data-yii-debug-feature-load-error",
    ),
    "true",
    "Failure must be flagged on the document.",
  );
  assert.equal(
    alert.parentElement.id,
    "yii-debug-main",
    "Callout must open the main region.",
  );
  assert.match(alert.textContent, /Reload the page/, "Callout must advise.");
});

test("a panel feature failure without a main region only flags the page", async () => {
  const page = await bootDebugPage({ body: DB_MARKER });

  await settle(page.window);

  assert.equal(
    page.document.documentElement.getAttribute(
      "data-yii-debug-feature-load-error",
    ),
    "true",
    "Failure must be flagged on the document.",
  );
  assert.equal(
    page.document.querySelector('[role="alert"]'),
    null,
    "No region means no callout.",
  );
});

test("Escape asks the parent toolbar to close the drawer", async () => {
  const page = await bootDebugPage({
    body: "<p>Panel</p>",
    parent: "frame",
  });

  press(page.document.querySelector("p"), "Escape");

  await settle(page.window);

  assert.deepEqual(
    page.posts,
    [
      {
        data: { source: "yii-debug-toolbar", type: "close-drawer" },
        origin: "https://debug.test",
      },
    ],
    "Host toolbar must be asked to close.",
  );
});

test("Escape on a standalone page posts nothing", async () => {
  const page = await bootDebugPage({ body: "<p>Panel</p>" });

  press(page.document.querySelector("p"), "Escape");

  await settle(page.window);

  assert.equal(page.posts.length, 0, "There is no drawer to close.");
});

test("a keydown on the document itself is ignored", async () => {
  const page = await bootDebugPage({ body: "<p>Panel</p>", parent: "frame" });
  const event = fire(page.document, "keydown", {
    cancelable: true,
    key: "ArrowDown",
  });

  await settle(page.window);

  assert.equal(event.defaultPrevented, false, "A targetless key stays live.");
  assert.equal(page.posts.length, 0, "Only Escape may close the drawer.");
});

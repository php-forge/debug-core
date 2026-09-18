// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test } from "vitest";

import { bootDebugPage, fire, press, settle } from "./debug-page-harness.js";

const DEFAULT_ITEMS =
  '<a href="/debug/db">Database</a><button type="button">Clear</button>';

/** Renders one server-side dropdown, optionally degraded or already open. */
function dropdown(options) {
  const settings = options || {};
  const expanded = settings.open === true ? "true" : "false";
  const items = settings.items === undefined ? DEFAULT_ITEMS : settings.items;

  return (
    '<div class="yii-debug-dropdown' +
    (settings.open === true ? " is-open" : "") +
    '" id="' +
    (settings.id || "filters") +
    '">' +
    (settings.trigger === false
      ? ""
      : '<button type="button" data-yii-debug-toggle="dropdown" ' +
        'aria-expanded="' +
        expanded +
        '">Menu</button>') +
    (settings.menu === false
      ? ""
      : '<div class="yii-debug-dropdown-menu">' + items + "</div>") +
    "</div>"
  );
}

function trigger(page, id) {
  return page.document
    .getElementById(id || "filters")
    .querySelector('[data-yii-debug-toggle="dropdown"]');
}

function isOpen(page, id) {
  return page.document.getElementById(id).classList.contains("is-open");
}

test("opening a dropdown closes the one already open", async () => {
  const page = await bootDebugPage({
    body: dropdown({ id: "sort", open: true }) + dropdown({ id: "filters" }),
  });

  fire(trigger(page, "filters"), "click");

  assert.equal(isOpen(page, "filters"), true, "Clicked menu must open.");
  assert.equal(isOpen(page, "sort"), false, "Only one menu may stay open.");
  assert.equal(
    trigger(page, "sort").getAttribute("aria-expanded"),
    "false",
    "Closed trigger must report its state.",
  );
  assert.equal(
    trigger(page, "filters").getAttribute("aria-expanded"),
    "true",
    "Open trigger must report its state.",
  );
});

test("clicking an open trigger closes its own menu", async () => {
  const page = await bootDebugPage({ body: dropdown({ open: true }) });

  fire(trigger(page), "click");

  assert.equal(isOpen(page, "filters"), false, "Second click must close.");
  assert.equal(
    trigger(page).getAttribute("aria-expanded"),
    "false",
    "Trigger must report the collapsed state.",
  );
});

test("a dropdown trigger outside a wrapper is inert", async () => {
  const page = await bootDebugPage({
    body: '<button type="button" data-yii-debug-toggle="dropdown">Menu</button>',
  });
  const control = page.document.querySelector(
    '[data-yii-debug-toggle="dropdown"]',
  );
  const event = fire(control, "click");

  assert.equal(event.defaultPrevented, true, "Navigation must be suppressed.");
  assert.equal(
    control.getAttribute("aria-expanded"),
    null,
    "An orphan trigger controls nothing.",
  );
});

test("a dropdown without a menu is inert", async () => {
  const page = await bootDebugPage({ body: dropdown({ menu: false }) });

  fire(trigger(page), "click");

  assert.equal(isOpen(page, "filters"), false, "Nothing to reveal.");
  assert.equal(
    trigger(page).getAttribute("aria-expanded"),
    "false",
    "Trigger state must not change.",
  );
});

test("a click elsewhere closes a dropdown left open without a trigger", async () => {
  const page = await bootDebugPage({
    body: dropdown({ open: true, trigger: false }) + "<p>Elsewhere</p>",
  });

  fire(page.document.querySelector("p"), "click");

  assert.equal(isOpen(page, "filters"), false, "Outside click must close.");
});

test("ArrowDown from the trigger opens the menu on its first item", async () => {
  const page = await bootDebugPage({ body: dropdown() });
  const event = press(trigger(page), "ArrowDown");

  assert.equal(event.defaultPrevented, true, "Scrolling must be suppressed.");
  assert.equal(isOpen(page, "filters"), true, "Navigation must open the menu.");
  assert.equal(
    page.document.activeElement.textContent,
    "Database",
    "Focus must land on the first item.",
  );
});

test("ArrowUp from the trigger opens the menu on its last item", async () => {
  const page = await bootDebugPage({ body: dropdown() });

  press(trigger(page), "ArrowUp");

  assert.equal(
    page.document.activeElement.textContent,
    "Clear",
    "Focus must land on the last item.",
  );
});

test("Home and End jump to the ends of the menu", async () => {
  const page = await bootDebugPage({ body: dropdown({ open: true }) });
  const items = page.document.querySelectorAll(".yii-debug-dropdown-menu > *");

  press(items[0], "End");

  assert.equal(page.document.activeElement, items[1], "End must go last.");

  press(items[1], "Home");

  assert.equal(page.document.activeElement, items[0], "Home must go first.");
});

test("ArrowDown from an item moves to the next item", async () => {
  const page = await bootDebugPage({ body: dropdown({ open: true }) });
  const items = page.document.querySelectorAll(".yii-debug-dropdown-menu > *");

  press(items[0], "ArrowDown");

  assert.equal(page.document.activeElement, items[1], "Focus must advance.");
});

test("hidden menu items are skipped by keyboard navigation", async () => {
  const page = await bootDebugPage({
    body: dropdown({
      items:
        '<a href="/debug/db">Database</a>' +
        '<a href="/debug/log" hidden>Log</a>' +
        '<a href="/debug/mail" aria-hidden="true">Mail</a>' +
        '<button type="button" disabled>Clear</button>' +
        '<button type="button">Reset</button>',
    }),
  });

  press(trigger(page), "End");

  assert.equal(
    page.document.activeElement.textContent,
    "Reset",
    "Only perceivable, enabled items may take focus.",
  );
});

test("an empty menu stays closed under keyboard navigation", async () => {
  const page = await bootDebugPage({ body: dropdown({ items: "" }) });
  const event = press(trigger(page), "ArrowDown");

  assert.equal(isOpen(page, "filters"), false, "Nothing to navigate.");
  assert.equal(event.defaultPrevented, false, "The key must stay available.");
});

test("a dropdown without a trigger ignores arrow keys", async () => {
  const page = await bootDebugPage({ body: dropdown({ trigger: false }) });
  const item = page.document.querySelector(".yii-debug-dropdown-menu > *");

  press(item, "ArrowDown");

  assert.equal(isOpen(page, "filters"), false, "No trigger, no navigation.");
});

test("a dropdown without a menu ignores arrow keys", async () => {
  const page = await bootDebugPage({ body: dropdown({ menu: false }) });

  press(trigger(page), "ArrowDown");

  assert.equal(isOpen(page, "filters"), false, "No menu, no navigation.");
});

test("Escape closes the dropdown and returns focus to its trigger", async () => {
  const page = await bootDebugPage({
    body: dropdown({ open: true }),
    parent: "frame",
  });

  press(page.document.querySelector(".yii-debug-dropdown-menu > *"), "Escape");

  await settle(page.window);

  assert.equal(isOpen(page, "filters"), false, "Escape must close the menu.");
  assert.equal(
    page.document.activeElement,
    trigger(page),
    "Focus must return to the trigger.",
  );
  assert.equal(
    page.posts.length,
    0,
    "Closing a menu must not also close the drawer.",
  );
});

test("Escape closes a triggerless dropdown without moving focus", async () => {
  const page = await bootDebugPage({
    body: dropdown({ open: true, trigger: false }),
  });
  const item = page.document.querySelector(".yii-debug-dropdown-menu > *");

  press(item, "Escape");

  await settle(page.window);

  assert.equal(isOpen(page, "filters"), false, "Escape must close the menu.");
  assert.equal(
    page.document.activeElement,
    page.document.body,
    "There is nothing to focus.",
  );
});

test("Escape with no menu open asks the parent toolbar to close the drawer", async () => {
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
  const page = await bootDebugPage({ body: dropdown() });

  fire(page.document, "keydown", { key: "ArrowDown" });

  assert.equal(
    isOpen(page, "filters"),
    false,
    "A targetless key does nothing.",
  );
});

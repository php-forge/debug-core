// @vitest-environment jsdom
import assert from "node:assert/strict";
import { test } from "vitest";

import { bootDebugPage, fire } from "./debug-page-harness.js";

const CELL_MORE =
  '<div class="yii-debug-cell-more">' +
  '<div class="yii-debug-cell-more-body">Full value</div>' +
  '<button type="button" data-yii-debug-toggle="cell-more"></button></div>';

test("a cell-more control expands and collapses its box", async () => {
  const page = await bootDebugPage({ body: CELL_MORE });
  const box = page.document.querySelector(".yii-debug-cell-more");
  const control = box.querySelector('[data-yii-debug-toggle="cell-more"]');

  fire(control, "click");

  assert.equal(box.classList.contains("is-open"), true, "Box must open.");
  assert.equal(control.getAttribute("aria-expanded"), "true", "State is up.");
  assert.equal(control.textContent, "Show less", "Label must retract.");

  fire(control, "click");

  assert.equal(box.classList.contains("is-open"), false, "Box must close.");
  assert.equal(control.getAttribute("aria-expanded"), "false", "State down.");
  assert.equal(control.textContent, "Show more", "Label must invite.");
});

test("a cell-more control outside a box is inert", async () => {
  const page = await bootDebugPage({
    body: '<button type="button" data-yii-debug-toggle="cell-more">More</button>',
  });
  const control = page.document.querySelector(
    '[data-yii-debug-toggle="cell-more"]',
  );
  const event = fire(control, "click");

  assert.equal(event.defaultPrevented, true, "Navigation must be suppressed.");
  assert.equal(control.textContent, "More", "Orphan control must not change.");
});

test("a reveal control flips its pressed state and its label", async () => {
  const page = await bootDebugPage({
    body:
      '<button type="button" data-yii-debug-reveal ' +
      'data-yii-debug-reveal-label="access token">***</button>',
  });
  const control = page.document.querySelector("[data-yii-debug-reveal]");

  fire(control, "click");

  assert.equal(
    control.classList.contains("is-revealed"),
    true,
    "Value must be revealed.",
  );
  assert.equal(control.getAttribute("aria-pressed"), "true", "State is up.");
  assert.equal(
    control.getAttribute("aria-label"),
    "Hide access token",
    "Label must offer the reverse action.",
  );

  fire(control, "click");

  assert.equal(
    control.classList.contains("is-revealed"),
    false,
    "Value must be masked again.",
  );
  assert.equal(control.getAttribute("aria-pressed"), "false", "State down.");
  assert.equal(
    control.getAttribute("aria-label"),
    "Reveal access token",
    "Label must offer the reverse action.",
  );
});

test("a reveal control without a label describes a generic value", async () => {
  const page = await bootDebugPage({
    body: '<button type="button" data-yii-debug-reveal>***</button>',
  });
  const control = page.document.querySelector("[data-yii-debug-reveal]");

  fire(control, "click");

  assert.equal(
    control.getAttribute("aria-label"),
    "Hide value",
    "Unlabelled fields fall back to a generic noun.",
  );
});

test("a click away from any control leaves the page alone", async () => {
  const page = await bootDebugPage({ body: "<p>Nothing to toggle</p>" });
  const event = fire(page.document.querySelector("p"), "click");

  assert.equal(
    event.defaultPrevented,
    false,
    "Plain content clicks must stay live.",
  );
});

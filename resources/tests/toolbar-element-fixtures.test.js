// @vitest-environment jsdom
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { afterEach, test } from "vitest";

import { builtinIconUrl } from "../src/toolbar/icons.js";
import {
  installLocalStorage,
  installMatchMedia,
  renderToolbar,
  teardownToolbars,
} from "./toolbar-element-harness.js";

/**
 * Contract tests driven by the toolbar payloads the PHP renderers produce.
 *
 * The fixtures are captured from the backend, so a chip that stops rendering
 * here is a real contract break between the two halves of the debugger — not a
 * hand-written payload drifting away from what the server actually sends.
 */

var storage = installLocalStorage();

installMatchMedia();

afterEach(teardownToolbars);

/** Reads a PHP-produced `ToolbarData` snapshot. */
function fixture(name) {
  return JSON.parse(
    readFileSync(
      new URL("./fixtures/toolbar/" + name + ".json", import.meta.url),
      "utf8",
    ),
  );
}

/** Renders a fixture through a connected, expanded toolbar. */
function render(name) {
  storage.set("yii-debug-toolbar-expanded", "1");

  return renderToolbar(fixture(name));
}

/** Title attribute of every node in a node list, in document order. */
function titles(nodes) {
  return Array.from(nodes).map(function (node) {
    return node.getAttribute("title");
  });
}

function inlineChips(element) {
  return element.shadowRoot.querySelectorAll(".panels .panel");
}

function extensionChips(element) {
  return element.shadowRoot.querySelectorAll(".extensions-menu .panel");
}

test("the inline strip keeps the payload order of the built-in panels", () => {
  var element = render("configured-extensions");

  assert.deepEqual(titles(inlineChips(element)), ["Request", "Logs", "Events"]);
});

test("every panel flagged as an extension is grouped, and no built-in is", () => {
  var payload = fixture("configured-extensions");
  var element = render("configured-extensions");
  var expected = payload.items
    .filter(function (panel) {
      return panel.extension === true;
    })
    .map(function (panel) {
      return panel.title;
    });

  assert.deepEqual(titles(extensionChips(element)), expected);
  assert.deepEqual(expected, [
    "Inertia visits",
    "Cache operations",
    "Vite assets",
  ]);
  assert.deepEqual(
    titles(inlineChips(element)).filter(function (title) {
      return expected.indexOf(title) !== -1;
    }),
    [],
  );
});

test("a grouped panel renders the configured title, icon and metric label", () => {
  var element = render("configured-extensions");
  var vite = extensionChips(element)[2];
  var icon = vite.querySelector(".panel-icon");

  assert.equal(vite.querySelector(".panel-title").textContent, "Vite assets");
  assert.equal(
    icon.getAttribute("style").includes(builtinIconUrl("asset")),
    true,
  );
  assert.equal(vite.querySelector(".metric-label").textContent, "assets");
  assert.equal(vite.querySelector(".metric-value").textContent, "3");
});

test("the profiling chip renders titleless and ahead of the inline strip", () => {
  var element = render("configured-extensions");
  var chips = element.shadowRoot.querySelectorAll(".panel");
  var profiling = chips[0];

  assert.equal(profiling.getAttribute("title"), "profiling");
  assert.equal(profiling.querySelector(".panel-title"), null);
  assert.equal(profiling.querySelector(".metric-value").textContent, "42 ms");
  assert.equal(
    profiling.compareDocumentPosition(
      element.shadowRoot.querySelector(".panels"),
    ) & Node.DOCUMENT_POSITION_FOLLOWING,
    Node.DOCUMENT_POSITION_FOLLOWING,
  );
});

test("a panel URL without linked metrics makes the whole chip the link", () => {
  var element = render("configured-extensions");
  var request = inlineChips(element)[0];

  assert.equal(request.tagName, "A");
  assert.equal(
    request.getAttribute("data-debug-url"),
    "/debug/view?tag=tag-fixture&panel=request",
  );
  assert.equal(request.querySelector(".metric").tagName, "SPAN");
});

test("a failed extension surfaces its danger badge and the reported reason", () => {
  var element = render("extension-failure");
  var cache = extensionChips(element)[0];
  var metric = cache.querySelector(".metric");

  assert.deepEqual(titles(extensionChips(element)), ["Cache"]);
  assert.equal(metric.querySelector(".badge-danger").textContent, "error");
  assert.equal(
    metric.getAttribute("title"),
    "Debug panel 'cache' returned an invalid toolbar envelope: 'items'.",
  );
  assert.equal(
    element.shadowRoot.querySelector(".extensions-toggle .metric-value")
      .className,
    "metric-value badge-danger",
  );
});

test("a payload without extensions renders no Extensions menu", () => {
  var element = render("disabled-and-minimal");

  assert.equal(element.shadowRoot.querySelector(".extensions"), null);
  assert.deepEqual(titles(inlineChips(element)), ["Request", "Logs", "Events"]);
});

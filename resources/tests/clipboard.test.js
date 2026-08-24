import assert from "node:assert/strict";
import test from "node:test";

import { bindCopyControls, copyControlText } from "../src/core/clipboard.js";

function fixture(attributes = {}) {
  var nodesById = new Map();
  var statuses = [];
  var label = { textContent: "Copy link" };
  var listeners = new Map();
  var values = new Map(Object.entries(attributes));
  var documentValue = {
    createElement() {
      var element = {
        attributes: new Map(),
        className: "",
        id: "",
        textContent: "",
        getAttribute(name) {
          return this.attributes.get(name) ?? null;
        },
        setAttribute(name, value) {
          this.attributes.set(name, value);
        },
      };

      statuses.push(element);

      return element;
    },
    getElementById(id) {
      return nodesById.get(id) || null;
    },
  };
  var control = {
    ownerDocument: documentValue,
    addEventListener(name, listener) {
      listeners.set(name, listener);
    },
    after(status) {
      nodesById.set(status.id, status);
    },
    getAttribute(name) {
      return values.get(name) ?? null;
    },
    hasAttribute(name) {
      return values.has(name);
    },
    querySelector(selector) {
      return selector === "[data-yii-debug-copy-label]" ? label : null;
    },
    setAttribute(name, value) {
      values.set(name, value);
    },
  };
  var root = {
    getElementById: documentValue.getElementById,
    querySelectorAll() {
      return [control];
    },
  };

  return { control, label, listeners, nodesById, root, statuses, values };
}

test("copy controls announce successful permalink copies", async () => {
  var page = fixture({ "data-yii-debug-copy-link": "true" });
  var writes = [];

  assert.equal(
    bindCopyControls(
      page.root,
      {
        writeText(value) {
          writes.push(value);
          return Promise.resolve();
        },
      },
      "https://example.test/debug/request#request-panel-1",
    ),
    1,
  );

  page.listeners.get("click")();
  await Promise.resolve();
  await Promise.resolve();

  assert.deepEqual(writes, [
    "https://example.test/debug/request#request-panel-1",
  ]);
  assert.equal(page.label.textContent, "Copied");
  assert.equal(page.values.get("aria-label"), "Copied");
  assert.equal(page.statuses[0].getAttribute("aria-live"), "polite");
  assert.equal(page.statuses[0].textContent, "Copied to clipboard.");
  assert.equal(page.values.get("aria-describedby"), page.statuses[0].id);
});

test("generic copy controls resolve target ids and report unavailable APIs", () => {
  var page = fixture({
    "data-yii-debug-copy": "true",
    "data-yii-debug-copy-target": "#sql-7",
  });

  page.nodesById.set("sql-7", { textContent: "SELECT 1" });

  assert.equal(copyControlText(page.control, page.root, null), "SELECT 1");
  bindCopyControls(page.root, null, null);
  page.listeners.get("click")();

  assert.equal(page.label.textContent, "Unavailable");
  assert.equal(page.values.get("aria-label"), "Copy unavailable");
  assert.equal(
    page.statuses[0].textContent,
    "Clipboard access is unavailable.",
  );
});

import assert from "node:assert/strict";
import test from "node:test";

import {
  applyDensity,
  bindDensityToggle,
  DENSITY_STORAGE_KEY,
  normalizeDensity,
  readStoredDensity,
  updateDensityButton,
  writeStoredDensity,
} from "../src/core/density.js";

function element(attributes = {}) {
  var listeners = {};
  var label = { textContent: "" };

  return {
    attributes,
    label,
    addEventListener(type, listener) {
      listeners[type] = listener;
    },
    click() {
      listeners.click();
    },
    getAttribute(name) {
      return this.attributes[name] || null;
    },
    querySelector(selector) {
      return selector === "[data-yii-debug-density-label]" ? label : null;
    },
    setAttribute(name, value) {
      this.attributes[name] = value;
    },
  };
}

test("density values normalize to the public compact/cozy contract", () => {
  assert.equal(normalizeDensity("compact"), "compact");
  assert.equal(normalizeDensity("cozy"), "cozy");
  assert.equal(normalizeDensity("dense"), null);
  assert.equal(applyDensity(null, "invalid"), "cozy");
});

test("stored density is resilient to unavailable storage", () => {
  assert.equal(DENSITY_STORAGE_KEY, "yii-debug-density");
  assert.equal(
    readStoredDensity({
      getItem() {
        throw new Error("blocked");
      },
    }),
    null,
  );
  assert.equal(readStoredDensity({ getItem: () => "compact" }), "compact");
});

test("density toggle applies, labels, persists, and reverses the preference", () => {
  var root = element();
  var button = element();
  var values = new Map([[DENSITY_STORAGE_KEY, "compact"]]);
  var storage = {
    getItem(key) {
      return values.get(key) || null;
    },
    setItem(key, value) {
      values.set(key, value);
    },
  };

  assert.equal(bindDensityToggle(button, root, storage), "compact");
  assert.equal(root.getAttribute("data-yii-debug-density"), "compact");
  assert.equal(button.getAttribute("aria-label"), "Switch to cozy density");
  assert.equal(button.getAttribute("aria-pressed"), "true");
  assert.equal(button.getAttribute("title"), "Switch to cozy density");
  assert.equal(button.label.textContent, "Compact");

  button.click();

  assert.equal(root.getAttribute("data-yii-debug-density"), "cozy");
  assert.equal(button.getAttribute("aria-label"), "Switch to compact density");
  assert.equal(button.getAttribute("aria-pressed"), "false");
  assert.equal(button.getAttribute("title"), "Switch to compact density");
  assert.equal(button.label.textContent, "Cozy");
  assert.equal(values.get(DENSITY_STORAGE_KEY), "cozy");

  button.click();

  assert.equal(root.getAttribute("data-yii-debug-density"), "compact");
  assert.equal(values.get(DENSITY_STORAGE_KEY), "compact");
});

test("density writes never reach a falsy storage backend", () => {
  var calls = [];

  // A falsy primitive backend observes property access through its prototype,
  // proving the guard short-circuits before any `setItem` lookup.
  Boolean.prototype.setItem = function (key, value) {
    calls.push([key, value]);
  };

  try {
    assert.equal(writeStoredDensity("compact", false), undefined);
  } finally {
    delete Boolean.prototype.setItem;
  }

  assert.deepEqual(calls, []);
});

test("density helpers tolerate missing buttons, labels, and blocked storage", () => {
  assert.equal(writeStoredDensity("cozy", null), undefined);
  assert.equal(
    writeStoredDensity("cozy", {
      setItem() {
        throw new Error("blocked");
      },
    }),
    undefined,
  );
  assert.equal(updateDensityButton(null, "cozy"), undefined);
  assert.equal(bindDensityToggle(null, null, null), "cozy");

  var root = element({ "data-yii-debug-density": "compact" });
  var unlabeled = element();

  unlabeled.querySelector = () => null;

  assert.equal(bindDensityToggle(unlabeled, root, null), "compact");
  assert.equal(unlabeled.getAttribute("aria-pressed"), "true");
});

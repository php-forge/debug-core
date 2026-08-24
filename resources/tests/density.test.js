import assert from "node:assert/strict";
import test from "node:test";

import {
  applyDensity,
  bindDensityToggle,
  DENSITY_STORAGE_KEY,
  normalizeDensity,
  readStoredDensity,
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
  assert.equal(button.getAttribute("aria-pressed"), "true");
  assert.equal(button.label.textContent, "Compact");

  button.click();

  assert.equal(root.getAttribute("data-yii-debug-density"), "cozy");
  assert.equal(button.getAttribute("aria-pressed"), "false");
  assert.equal(button.label.textContent, "Cozy");
  assert.equal(values.get(DENSITY_STORAGE_KEY), "cozy");
});

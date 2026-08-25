import assert from "node:assert/strict";
import test from "node:test";

var sectionCalls = 0;

var section = {
  getAttribute() {
    sectionCalls += 1;

    return null;
  },
  addEventListener() {
    sectionCalls += 1;
  },
};

globalThis.document = {
  querySelector(selector) {
    return selector === "[data-yii-debug-history-cursor]" ? section : null;
  },
  querySelectorAll() {
    return [];
  },
};

await import("../src/core/history-cursor.js");

test("an empty history table short-circuits before wiring any behavior", () => {
  assert.equal(sectionCalls, 0);
});

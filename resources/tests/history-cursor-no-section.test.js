import assert from "node:assert/strict";
import test from "node:test";

var rowQueries = 0;

globalThis.document = {
  querySelector() {
    return null;
  },
  querySelectorAll() {
    rowQueries += 1;

    return [];
  },
};

await import("../src/core/history-cursor.js");

test("a page without the history section leaves the document untouched", () => {
  assert.equal(rowQueries, 0);
});

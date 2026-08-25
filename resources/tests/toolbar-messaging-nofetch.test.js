import assert from "node:assert/strict";
import test from "node:test";

globalThis.window = {
  location: new URL("https://example.test/"),
};
globalThis.document = {
  querySelector() {
    return null;
  },
};
globalThis.XMLHttpRequest = function XMLHttpRequest() {};
globalThis.XMLHttpRequest.prototype.open = function open() {};

const { trackRequests } = await import("../src/toolbar/messaging.js");

test("fetch tracking is skipped when the host exposes no fetch", () => {
  trackRequests();

  assert.equal(window.fetch, undefined);
  assert.equal(window.__yiiDebugToolbarTracking, true);
});

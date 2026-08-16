import assert from "node:assert/strict";
import test from "node:test";

var fetchCalls = [];
var toolbar = {
  getAttribute(name) {
    return name === "data-url" ? "/debug/default/toolbar?tag=page" : null;
  },
};

globalThis.window = {
  fetch(input, init) {
    fetchCalls.push({ input, init });

    return Promise.resolve({
      headers: {
        get() {
          return null;
        },
      },
      status: 200,
    });
  },
  location: new URL("https://example.test/"),
  Request,
  URL,
};
globalThis.document = {
  querySelector() {
    return toolbar;
  },
};
globalThis.XMLHttpRequest = function XMLHttpRequest() {};
globalThis.XMLHttpRequest.prototype.open = function open() {};

const { requestStack } = await import("../src/toolbar/state.js");
const { trackRequests } = await import("../src/toolbar/messaging.js");

test("fetch tracking supports URL-bearing request polyfills", async () => {
  var input = { url: "/api/items" };
  var methodInput = { method: "PATCH", url: "/api/items/1" };
  var requestInput = new Request("https://example.test/api/request", {
    method: "POST",
  });
  var stringInput = {
    toString() {
      return "/api/stringable";
    },
  };

  trackRequests();
  await window.fetch(input);
  await window.fetch(methodInput, { method: "DELETE" });
  await window.fetch(requestInput, { method: "DELETE" });
  await window.fetch(stringInput);
  await Promise.resolve();

  assert.equal(fetchCalls.length, 4);
  assert.equal(requestStack.length, 4);
  assert.equal(requestStack[0].url, "/api/items");
  assert.equal(requestStack[0].method, "GET");
  assert.equal(requestStack[1].url, "/api/items/1");
  assert.equal(requestStack[1].method, "DELETE");
  assert.equal(requestStack[2].url, "https://example.test/api/request");
  assert.equal(requestStack[2].method, "DELETE");
  assert.equal(requestStack[3].url, "/api/stringable");
  assert.equal(requestStack[3].method, "GET");

  requestStack.forEach(function (item) {
    assert.equal(item.loading, false);
    assert.equal(item.statusCode, 200);
  });
});

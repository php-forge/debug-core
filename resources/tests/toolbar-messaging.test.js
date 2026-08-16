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
globalThis.XMLHttpRequest = function XMLHttpRequest() {
  this.listeners = new Map();
  this.headers = {};
};
globalThis.XMLHttpRequest.prototype.open = function open() {};
globalThis.XMLHttpRequest.prototype.addEventListener =
  function addEventListener(type, listener) {
    var listeners = this.listeners.get(type) || new Set();

    listeners.add(listener);
    this.listeners.set(type, listeners);
  };
globalThis.XMLHttpRequest.prototype.removeEventListener =
  function removeEventListener(type, listener) {
    var listeners = this.listeners.get(type);

    if (listeners) {
      listeners.delete(listener);
    }
  };
globalThis.XMLHttpRequest.prototype.dispatch = function dispatch(type) {
  var listeners = this.listeners.get(type) || [];

  Array.from(listeners).forEach((listener) => listener());
};
globalThis.XMLHttpRequest.prototype.getResponseHeader =
  function getResponseHeader(name) {
    return this.headers[name] || null;
  };

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

test("XHR tracking detaches completed listeners before instance reuse", () => {
  var startIndex = requestStack.length;
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/first");
  xhr.readyState = 4;
  xhr.status = 201;
  xhr.headers = {
    "X-Debug-Duration": "10",
    "X-Debug-Tag": "first-tag",
    "X-Debug-Link": "/debug/first",
  };
  xhr.dispatch("readystatechange");

  var first = requestStack[startIndex];

  assert.equal(xhr.listeners.get("readystatechange").size, 0);
  assert.equal(first.statusCode, 201);
  assert.equal(first.profile, "first-tag");

  xhr.open("POST", "/api/second");
  xhr.readyState = 4;
  xhr.status = 202;
  xhr.headers = {
    "X-Debug-Duration": "20",
    "X-Debug-Tag": "second-tag",
    "X-Debug-Link": "/debug/second",
  };
  xhr.dispatch("readystatechange");

  var second = requestStack[startIndex + 1];

  assert.equal(xhr.listeners.get("readystatechange").size, 0);
  assert.equal(first.statusCode, 201);
  assert.equal(first.duration, "10");
  assert.equal(first.profile, "first-tag");
  assert.equal(first.profilerUrl, "/debug/first");
  assert.equal(second.statusCode, 202);
  assert.equal(second.duration, "20");
  assert.equal(second.profile, "second-tag");
  assert.equal(second.profilerUrl, "/debug/second");
});

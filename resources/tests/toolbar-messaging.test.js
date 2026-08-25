import assert from "node:assert/strict";
import test from "node:test";

var failNextFetch = false;
var fetchCalls = [];
var toolbar = {
  getAttribute(name) {
    return name === "data-url" ? "/debug/default/toolbar?tag=page" : null;
  },
};

globalThis.window = {
  fetch(input, init) {
    fetchCalls.push({ input, init });

    if (failNextFetch) {
      failNextFetch = false;

      return Promise.reject(new Error("network unreachable"));
    }

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
globalThis.XMLHttpRequest.prototype.open = function open() {
  if (this.readyState > 1 && this.readyState < 4) {
    this.readyState = 4;
    this.dispatch("readystatechange");
  }

  this.readyState = 1;
};
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

const { requestStack, toolbars } = await import("../src/toolbar/state.js");
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

test("XHR tracking detaches in-flight listeners before instance reuse", () => {
  var startIndex = requestStack.length;
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/first-in-flight");

  assert.equal(xhr.listeners.get("readystatechange").size, 1);

  xhr.readyState = 3;
  xhr.open("POST", "/api/replacement");

  assert.equal(xhr.listeners.get("readystatechange").size, 1);

  xhr.readyState = 4;
  xhr.status = 202;
  xhr.headers = {
    "X-Debug-Duration": "20",
    "X-Debug-Tag": "replacement-tag",
    "X-Debug-Link": "/debug/replacement",
  };
  xhr.dispatch("readystatechange");

  var first = requestStack[startIndex];
  var replacement = requestStack[startIndex + 1];

  assert.equal(xhr.listeners.get("readystatechange").size, 0);
  assert.equal(first.statusCode, undefined);
  assert.equal(first.profile, undefined);
  assert.equal(first.profilerUrl, undefined);
  assert.equal(replacement.statusCode, 202);
  assert.equal(replacement.duration, "20");
  assert.equal(replacement.profile, "replacement-tag");
  assert.equal(replacement.profilerUrl, "/debug/replacement");
});

test("AJAX tracking rejects unsafe debug profile response links", () => {
  var startIndex = requestStack.length;
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/hostile-link");
  xhr.readyState = 4;
  xhr.status = 200;
  xhr.headers = {
    "X-Debug-Tag": "hostile-tag",
    "X-Debug-Link": "javascript:alert(1)",
  };
  xhr.dispatch("readystatechange");

  assert.equal(requestStack[startIndex].profile, "hostile-tag");
  assert.equal(requestStack[startIndex].profilerUrl, null);

  xhr.open("GET", "/api/cross-origin-link");
  xhr.readyState = 4;
  xhr.status = 200;
  xhr.headers = {
    "X-Debug-Tag": "cross-origin-tag",
    "X-Debug-Link": "https://attacker.test/debug/view",
  };
  xhr.dispatch("readystatechange");

  assert.equal(requestStack[startIndex + 1].profilerUrl, null);
});

test("request tracking skips toolbar, cross-origin, and skip-listed URLs", async () => {
  var startIndex = requestStack.length;
  var originalGetAttribute = toolbar.getAttribute;
  var originalQuerySelector = document.querySelector;

  document.querySelector = function () {
    return null;
  };
  await window.fetch("/api/no-toolbar");
  document.querySelector = originalQuerySelector;

  toolbar.getAttribute = function (name) {
    if (name === "data-url") {
      return "/debug/default/toolbar?tag=page";
    }

    return name === "data-skip-urls" ? '["/api/health"]' : null;
  };

  await window.fetch(undefined).catch(function () {});
  await window.fetch("https://[bad");
  await window.fetch("https://attacker.test/api/items");
  await window.fetch("/debug/default/toolbar?tag=page");
  await window.fetch("/api/health");
  await window.fetch(new URL("https://example.test/api/from-url"));
  await Promise.resolve();

  assert.equal(requestStack.length, startIndex + 1);
  assert.equal(
    requestStack[startIndex].url,
    "https://example.test/api/from-url",
  );
  assert.equal(requestStack[startIndex].method, "GET");

  toolbar.getAttribute = originalGetAttribute;
});

test("ajax notifications drive registered toolbars and follow tagged requests", () => {
  var followed = [];
  var received = [];

  toolbars.push({
    followTag(tag) {
      followed.push(tag);
    },
    getAttribute(name) {
      return toolbar.getAttribute(name);
    },
    setAjaxRequests(stack) {
      received.push(stack.length);
    },
  });

  try {
    var tagged = new XMLHttpRequest();

    tagged.open("GET", "/api/tagged");
    tagged.readyState = 4;
    tagged.status = 200;
    tagged.headers = { "X-Debug-Tag": "fresh-tag" };
    tagged.dispatch("readystatechange");

    assert.equal(followed[followed.length - 1], "fresh-tag");

    var untagged = new XMLHttpRequest();

    untagged.open("GET", "/api/untagged");
    assert.equal(followed[followed.length - 1], "fresh-tag");

    untagged.readyState = 2;
    untagged.dispatch("readystatechange");

    untagged.readyState = 4;
    untagged.status = 200;
    untagged.headers = {};
    untagged.dispatch("readystatechange");

    assert.equal(followed[followed.length - 1], "fresh-tag");
    assert.equal(received.length > 0, true);
  } finally {
    toolbars.pop();
  }
});

test("failed fetch requests surface as errors", async () => {
  var startIndex = requestStack.length;

  failNextFetch = true;
  await window.fetch("/api/failing").catch(function () {});
  await Promise.resolve();

  assert.equal(requestStack[startIndex].loading, false);
  assert.equal(requestStack[startIndex].error, true);
});

test("fetch method resolution honors init overrides and request defaults", async () => {
  var startIndex = requestStack.length;

  await window.fetch("/api/string-init", { method: "PATCH" });
  await window.fetch("/api/string-empty-init", {});
  await window.fetch(new URL("https://example.test/api/url-init"), {
    method: "PUT",
  });
  await window.fetch(new Request("https://example.test/api/request-default"));
  await Promise.resolve();

  assert.equal(requestStack[startIndex].method, "PATCH");
  assert.equal(requestStack[startIndex + 1].method, "GET");
  assert.equal(requestStack[startIndex + 2].method, "PUT");
  assert.equal(requestStack[startIndex + 3].method, "GET");
});

test("request tracking installs only once", () => {
  var currentFetch = window.fetch;

  trackRequests();

  assert.equal(window.fetch, currentFetch);
});

test("the request stack keeps only the most recent hundred requests", async () => {
  for (var i = 0; i < 105; i++) {
    var xhr = new XMLHttpRequest();

    xhr.open("GET", "/api/bulk-" + i);
    xhr.readyState = 4;
    xhr.status = 200;
    xhr.headers = {};
    xhr.dispatch("readystatechange");
  }

  assert.equal(requestStack.length, 100);

  for (var j = 0; j < 101; j++) {
    await window.fetch("/api/fetch-bulk-" + j);
  }
  await Promise.resolve();

  assert.equal(requestStack.length, 100);
  assert.equal(requestStack[99].url, "/api/fetch-bulk-100");
});

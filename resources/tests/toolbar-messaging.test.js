import assert from "node:assert/strict";
import { test } from "vitest";

var failNextFetch = false;
var fetchCalls = [];
var finalizeNextOpen = false;
var throwNextOpen = false;
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
/**
 * Mirrors the native `open()`: it terminates the request underneath an active
 * instance and re-enters OPENED without ever reaching DONE, so the listeners of
 * the terminated request never see a final `readystatechange`. `throwNextOpen`
 * rejects the call as a browser does for an invalid state, and
 * `finalizeNextOpen` stands in for a host whose `open()` drives the previous
 * request to DONE itself.
 */
globalThis.XMLHttpRequest.prototype.open = function open() {
  if (throwNextOpen) {
    throwNextOpen = false;

    throw new Error("The object is in an invalid state.");
  }

  if (finalizeNextOpen) {
    finalizeNextOpen = false;
    this.readyState = 4;
    this.dispatch("readystatechange");
  }

  this.readyState = 1;
  this.dispatch("readystatechange");
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

test("XHR tracking finalizes an in-flight request before instance reuse", () => {
  var startIndex = requestStack.length;
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/first-in-flight");

  assert.equal(xhr.listeners.get("readystatechange").size, 1);

  xhr.readyState = 3;
  xhr.open("POST", "/api/replacement");

  var first = requestStack[startIndex];
  var replacement = requestStack[startIndex + 1];

  assert.equal(
    xhr.listeners.get("readystatechange").size,
    1,
    "Only the replacement may stay attached.",
  );
  assert.equal(first.loading, false, "The replaced entry must not stay open.");
  assert.equal(first.error, true, "An aborted request is an error.");
  assert.equal(first.statusCode, 0, "Transport failures report `0`.");
  assert.equal(
    replacement.url,
    "/api/replacement",
    "The reuse must be tracked.",
  );
  assert.equal(replacement.loading, true, "The replacement is still running.");

  xhr.readyState = 4;
  xhr.status = 202;
  xhr.headers = {
    "X-Debug-Duration": "20",
    "X-Debug-Tag": "replacement-tag",
    "X-Debug-Link": "/debug/replacement",
  };
  xhr.dispatch("readystatechange");

  assert.equal(xhr.listeners.get("readystatechange").size, 0);
  assert.equal(first.statusCode, 0, "The replaced entry must stay final.");
  assert.equal(first.profile, undefined);
  assert.equal(first.profilerUrl, undefined);
  assert.equal(replacement.statusCode, 202);
  assert.equal(replacement.duration, "20");
  assert.equal(replacement.profile, "replacement-tag");
  assert.equal(replacement.profilerUrl, "/debug/replacement");
});

test("an XHR finalized during open() is not failed a second time", () => {
  var startIndex = requestStack.length;
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/finalized-on-reuse");
  xhr.readyState = 3;
  xhr.status = 204;
  xhr.headers = { "X-Debug-Tag": "closing-tag" };
  finalizeNextOpen = true;
  xhr.open("GET", "/api/after-finalize");

  var first = requestStack[startIndex];

  assert.equal(first.statusCode, 204, "The host response must be kept.");
  assert.equal(first.error, false, "A completed request is no failure.");
  assert.equal(first.profile, "closing-tag", "Its metadata must survive.");
  assert.equal(
    requestStack[startIndex + 1].url,
    "/api/after-finalize",
    "The reuse must be tracked.",
  );
});

test("an open() that throws keeps the request it would have replaced", () => {
  var startIndex = requestStack.length;
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/kept");
  xhr.readyState = 3;
  throwNextOpen = true;

  assert.throws(
    function () {
      xhr.open("POST", "/api/rejected");
    },
    /invalid state/,
    "The native failure must propagate.",
  );

  var kept = requestStack[startIndex];

  assert.equal(
    requestStack.length,
    startIndex + 1,
    "A rejected open starts nothing.",
  );
  assert.equal(kept.loading, true, "The live entry must stay open.");
  assert.equal(
    xhr.listeners.get("readystatechange").size,
    1,
    "Its listeners must stay attached.",
  );

  xhr.readyState = 4;
  xhr.status = 200;
  xhr.headers = {};
  xhr.dispatch("readystatechange");

  assert.equal(kept.loading, false, "The kept entry must still finalize.");
  assert.equal(kept.statusCode, 200, "Its own response must be recorded.");
});

test("a single tracked XHR completes without the reuse guard", () => {
  var startIndex = requestStack.length;
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/single");

  assert.equal(requestStack.length, startIndex + 1, "One request, one entry.");
  assert.equal(requestStack[startIndex].loading, true, "It starts open.");

  xhr.readyState = 4;
  xhr.status = 200;
  xhr.headers = { "X-Debug-Tag": "single-tag" };
  xhr.dispatch("readystatechange");

  assert.equal(
    requestStack.length,
    startIndex + 1,
    "No extra entry may appear.",
  );
  assert.equal(requestStack[startIndex].loading, false, "It must finalize.");
  assert.equal(requestStack[startIndex].error, false, "`200` is no error.");
  assert.equal(requestStack[startIndex].statusCode, 200);
  assert.equal(requestStack[startIndex].profile, "single-tag");
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

  var skippedXhr = new XMLHttpRequest();

  skippedXhr.open("GET", "/debug/default/toolbar?tag=page");
  assert.equal(skippedXhr.readyState, 1);
  assert.equal(skippedXhr.listeners.has("readystatechange"), false);
  assert.equal(requestStack.length, startIndex);

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

test("ajax notifications hand the stack to every registered toolbar", () => {
  var received = [];

  /**
   * The registered toolbar exposes nothing but `setAjaxRequests()`: a tagged
   * completion must not ask it to move away from the page request.
   */
  toolbars.push({
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

    assert.equal(received.length, 1, "Opening must notify.");

    tagged.readyState = 4;
    tagged.status = 200;
    tagged.headers = { "X-Debug-Tag": "fresh-tag" };
    tagged.dispatch("readystatechange");

    assert.equal(received.length, 2, "Completing must notify.");
    assert.equal(
      received[1],
      requestStack.length,
      "Every notification must carry the whole stack.",
    );
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

/**
 * Newest tracked entry. The stack is bounded, so an absolute index taken
 * before the push stops resolving once the limit is reached.
 */
function lastRequest() {
  return requestStack[requestStack.length - 1];
}

test("a failed XHR is finalized instead of staying loading", () => {
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/unreachable");
  xhr.status = 0;
  xhr.dispatch("error");

  assert.equal(lastRequest().url, "/api/unreachable");
  assert.equal(lastRequest().loading, false);
  assert.equal(lastRequest().error, true);
  assert.equal(lastRequest().statusCode, 0);
});

test("a timed-out XHR is finalized instead of staying loading", () => {
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/slow");
  xhr.status = 0;
  xhr.dispatch("timeout");

  assert.equal(lastRequest().url, "/api/slow");
  assert.equal(lastRequest().loading, false);
  assert.equal(lastRequest().error, true);
  assert.equal(lastRequest().statusCode, 0);
});

test("an aborted XHR is finalized instead of staying loading", () => {
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/cancelled");
  xhr.status = 0;
  xhr.dispatch("abort");

  assert.equal(lastRequest().url, "/api/cancelled");
  assert.equal(lastRequest().loading, false);
  assert.equal(lastRequest().error, true);
  assert.equal(lastRequest().statusCode, 0);
});

test("a completed XHR is finalized exactly once when a failure follows", () => {
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/completed-then-error");
  xhr.readyState = 4;
  xhr.status = 204;
  xhr.headers = { "X-Debug-Tag": "completed-tag" };
  xhr.dispatch("readystatechange");
  xhr.status = 0;
  xhr.dispatch("error");
  xhr.dispatch("abort");

  assert.equal(lastRequest().url, "/api/completed-then-error");
  assert.equal(lastRequest().statusCode, 204);
  assert.equal(lastRequest().error, false);
  assert.equal(lastRequest().profile, "completed-tag");
  assert.equal(xhr.listeners.get("error").size, 0);
  assert.equal(xhr.listeners.get("abort").size, 0);
  assert.equal(xhr.listeners.get("timeout").size, 0);
});

test("a failed XHR is finalized exactly once when more failures follow", () => {
  var xhr = new XMLHttpRequest();

  xhr.open("GET", "/api/error-then-readystate");
  xhr.status = 0;
  xhr.dispatch("error");
  xhr.readyState = 4;
  xhr.status = 500;
  xhr.headers = { "X-Debug-Tag": "late-tag" };
  xhr.dispatch("readystatechange");
  xhr.dispatch("timeout");

  assert.equal(lastRequest().url, "/api/error-then-readystate");
  assert.equal(lastRequest().statusCode, 0);
  assert.equal(lastRequest().profile, undefined);
  assert.equal(lastRequest().duration, undefined);
  assert.equal(xhr.listeners.get("readystatechange").size, 0);
});

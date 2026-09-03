import assert from "node:assert/strict";
import { test } from "vitest";

import { AJAX_TIMEOUT, ajax, on } from "../src/core/dom.js";

globalThis.NodeList = class NodeList extends Array {};

test("on attaches a handler to each supported element", () => {
  var bindings = [];
  var elements = new NodeList(
    {
      addEventListener(event, handler, capture) {
        bindings.push([event, handler, capture]);
      },
    },
    {
      addEventListener(event, handler, capture) {
        bindings.push([event, handler, capture]);
      },
    },
  );
  var handler = function () {};

  var single = {
    addEventListener(event, handler, capture) {
      bindings.push([event, handler, capture]);
    },
  };

  on(elements, "click", handler);
  on(null, "click", handler);
  on([single, {}], "focus", handler);
  on(single, "keydown", handler);

  assert.deepEqual(bindings, [
    ["click", handler, false],
    ["click", handler, false],
    ["focus", handler, false],
    ["keydown", handler, false],
  ]);
});

test("ajax preserves object settings and encodes POST requests", () => {
  var request;
  var successes = 0;

  globalThis.XMLHttpRequest = function XMLHttpRequest() {
    request = this;
    this.headers = {};
  };
  globalThis.XMLHttpRequest.prototype.open = function open(method, url, async) {
    this.method = method;
    this.url = url;
    this.async = async;
  };
  globalThis.XMLHttpRequest.prototype.setRequestHeader = function setHeader(
    name,
    value,
  ) {
    this.headers[name] = value;
  };
  globalThis.XMLHttpRequest.prototype.send = function send(body) {
    this.body = body;
  };

  var settings = {
    url: "https://example.test/debug/user/set-identity",
    method: "post",
    data: "user_id=42",
    timeout: 25,
    success() {
      successes += 1;
    },
  };

  var returnedRequest = ajax(settings);

  assert.equal(settings.url, "https://example.test/debug/user/set-identity");
  assert.equal(returnedRequest, request);
  assert.equal(request.method, "post");
  assert.equal(request.url, settings.url);
  assert.equal(request.async, true);
  assert.equal(request.body, "user_id=42");
  assert.equal(request.timeout, 25);
  assert.equal(request.headers["X-Requested-With"], "XMLHttpRequest");
  assert.equal(request.headers.Accept, "text/html");
  assert.equal(
    request.headers["Content-Type"],
    "application/x-www-form-urlencoded",
  );

  request.readyState = 4;
  request.status = 200;
  request.onreadystatechange();

  assert.equal(successes, 1);
});

test("ajax treats every 2xx response as successful", () => {
  var request;

  globalThis.XMLHttpRequest = function XMLHttpRequest() {
    request = this;
  };
  globalThis.XMLHttpRequest.prototype.open = function () {};
  globalThis.XMLHttpRequest.prototype.setRequestHeader = function () {};
  globalThis.XMLHttpRequest.prototype.send = function () {};

  [199, 200, 201, 204, 299, 300].forEach(function (status) {
    var result = "";

    ajax("https://example.test/debug/action", {
      success() {
        result = "success";
      },
      error() {
        result = "error";
      },
    });

    request.readyState = 4;
    request.status = status;
    request.onreadystatechange();

    assert.equal(result, status >= 200 && status < 300 ? "success" : "error");
  });
});

test("ajax reports a timeout once and uses the default timeout", () => {
  var request;
  var errors = 0;

  globalThis.XMLHttpRequest = function XMLHttpRequest() {
    request = this;
  };
  globalThis.XMLHttpRequest.prototype.open = function () {};
  globalThis.XMLHttpRequest.prototype.setRequestHeader = function () {};
  globalThis.XMLHttpRequest.prototype.send = function () {};

  ajax("https://example.test/debug/db-explain", {
    error() {
      errors += 1;
    },
  });

  assert.equal(request.timeout, AJAX_TIMEOUT);

  request.ontimeout();
  request.readyState = 4;
  request.status = 0;
  request.onreadystatechange();

  assert.equal(errors, 1);
});

test("ajax settles once and ignores non-final ready states", () => {
  var request;
  var events = [];

  globalThis.XMLHttpRequest = function XMLHttpRequest() {
    request = this;
  };
  globalThis.XMLHttpRequest.prototype.open = function () {};
  globalThis.XMLHttpRequest.prototype.setRequestHeader = function () {};
  globalThis.XMLHttpRequest.prototype.send = function () {};

  ajax("https://example.test/debug/db-explain", {
    abort() {
      events.push("abort");
    },
    error() {
      events.push("error");
    },
    success() {
      events.push("success");
    },
  });

  request.readyState = 2;
  request.onreadystatechange();
  assert.deepEqual(events, []);

  request.readyState = 4;
  request.status = 200;
  request.onreadystatechange();
  request.onreadystatechange();
  request.onabort();
  request.ontimeout();

  assert.deepEqual(events, ["success"]);

  ajax("https://example.test/debug/db-explain");
  request.readyState = 4;
  request.status = 204;
  request.onreadystatechange();
  request.readyState = 4;
  request.status = 500;

  ajax("https://example.test/debug/db-explain");
  request.readyState = 4;
  request.status = 500;
  request.onreadystatechange();

  ajax("https://example.test/debug/db-explain");
  request.onabort();
  request.ontimeout();

  assert.deepEqual(events, ["success"]);
});

test("ajax exposes cancellation without reporting it as a request failure", () => {
  var request;
  var aborts = 0;
  var errors = 0;

  globalThis.XMLHttpRequest = function XMLHttpRequest() {
    request = this;
  };
  globalThis.XMLHttpRequest.prototype.open = function () {};
  globalThis.XMLHttpRequest.prototype.setRequestHeader = function () {};
  globalThis.XMLHttpRequest.prototype.send = function () {};

  ajax("https://example.test/debug/db-explain", {
    abort() {
      aborts += 1;
    },
    error() {
      errors += 1;
    },
  });

  request.onabort();
  request.readyState = 4;
  request.status = 0;
  request.onreadystatechange();

  assert.equal(aborts, 1);
  assert.equal(errors, 0);
});

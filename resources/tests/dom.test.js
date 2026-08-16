import assert from "node:assert/strict";
import test from "node:test";

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

  on(elements, "click", handler);
  on(null, "click", handler);

  assert.deepEqual(bindings, [
    ["click", handler, false],
    ["click", handler, false],
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

  ajax(settings);

  assert.equal(settings.url, "https://example.test/debug/user/set-identity");
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

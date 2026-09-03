import assert from "node:assert/strict";
import { test } from "vitest";

var clickHandler = null;
var reloads = 0;
var sends = 0;
var pending = [];
var inserted = [];

var form = {
  action: "https://example.test/debug/user/set-identity",
  nodeName: "FORM",
  firstChild: { marker: "first" },
  querySelector() {
    return null;
  },
  insertBefore(node) {
    inserted.push(node);
  },
};
var button = {
  addEventListener(event, handler) {
    if (event === "click") {
      clickHandler = handler;
    }
  },
};

globalThis.NodeList = class NodeList {};
globalThis.document = {
  getElementById(id) {
    if (id === "debug-userswitch__reset-identity") {
      return form;
    }
    if (id === "debug-userswitch__reset-identity-button") {
      return button;
    }

    return null;
  },
  createElement() {
    return { className: "", setAttribute() {}, textContent: "" };
  },
};
globalThis.FormData = class FormData {
  constructor(f) {
    assert.equal(f, form);
  }

  forEach(callback) {
    callback("42", "number");
  }
};
globalThis.window = {
  top: {
    location: {
      reload() {
        reloads += 1;
      },
    },
  },
};
globalThis.XMLHttpRequest = function XMLHttpRequest() {
  this.readyState = 0;
  this.status = 0;
  this.responseText = "";
};
globalThis.XMLHttpRequest.prototype.open = function open(method, url) {
  this.method = method;
  this.url = url;
};
globalThis.XMLHttpRequest.prototype.setRequestHeader = function () {};
globalThis.XMLHttpRequest.prototype.send = function send(body) {
  this.body = body;
  sends += 1;
  pending.push(this);
};

await import("../src/panels/userswitch.js");

var event = {
  preventDefault() {},
  stopPropagation() {},
};

test("concurrent submits collapse to a single in-flight request", () => {
  assert.equal(typeof clickHandler, "function");

  clickHandler(event);
  clickHandler(event);

  assert.equal(
    sends,
    1,
    "Second submit must be ignored while one is in flight.",
  );
  assert.equal(reloads, 0, "No reload before the request completes.");
});

test("a failed submit re-arms the guard and surfaces the error", () => {
  var xhr = pending[0];

  xhr.status = 400;
  xhr.responseText = "Identity not found.";
  xhr.readyState = 4;
  xhr.onreadystatechange();

  assert.equal(reloads, 0, "A failed switch must not reload.");
  assert.equal(inserted.length, 1, "The error message must render once.");

  clickHandler(event);

  assert.equal(sends, 2, "After a failure the next click must send again.");
});

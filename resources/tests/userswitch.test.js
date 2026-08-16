import assert from "node:assert/strict";
import test from "node:test";

var clickHandler = null;
var reloads = 0;
var request = null;
var resetForm = {
  action: "https://example.test/debug/user/reset-identity",
  nodeName: "FORM",
};
var resetButton = {
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
      return resetForm;
    }
    if (id === "debug-userswitch__reset-identity-button") {
      return resetButton;
    }

    return null;
  },
};
globalThis.FormData = class FormData {
  constructor(form) {
    assert.equal(form, resetForm);
  }

  forEach(callback) {
    callback("developer@example.test", "email");
    callback("42", "number");
    callback("admin", "roles[]");
    callback("operator", "roles[]");
    callback({}, "binary");
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
  request = this;
  this.readyState = 0;
  this.status = 0;
};
globalThis.XMLHttpRequest.prototype.open = function open(method, url) {
  this.method = method;
  this.url = url;
};
globalThis.XMLHttpRequest.prototype.setRequestHeader = function () {};
globalThis.XMLHttpRequest.prototype.send = function send(body) {
  this.body = body;
  this.readyState = 4;
  this.status = 200;
  this.onreadystatechange();
};

await import("../src/panels/userswitch.js");

test("user switch serializes native form entries as urlencoded data", () => {
  assert.equal(typeof clickHandler, "function");

  clickHandler({
    preventDefault() {},
    stopPropagation() {},
  });

  assert.equal(request.method, "post");
  assert.equal(request.url, resetForm.action);
  assert.equal(
    request.body,
    "email=developer%40example.test&number=42&roles%5B%5D=admin&roles%5B%5D=operator",
  );
  assert.equal(reloads, 1);
});

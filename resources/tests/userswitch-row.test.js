import assert from "node:assert/strict";
import test from "node:test";

var handlers = {};
var sends = 0;
var userId = { value: "" };
var error = { textContent: "" };
var form = {
  action: "https://example.test/debug/user/set-identity",
  nodeName: "FORM",
  querySelector() {
    return error;
  },
};
var filter = {
  addEventListener(event, handler) {
    handlers[event] = handler;
  },
  contains(node) {
    return node === row;
  },
};
var row = {
  dataset: { key: "42" },
  nodeType: 1,
  closest(selector) {
    return selector === "tbody tr[data-key]" ? this : null;
  },
  contains(node) {
    return [this, cell, link].includes(node);
  },
};
var cell = {
  nodeType: 1,
  closest(selector) {
    return selector === "tbody tr[data-key]" ? row : null;
  },
};
var link = {
  nodeType: 1,
  closest(selector) {
    return selector === "tbody tr[data-key]" ? row : this;
  },
};

globalThis.NodeList = class NodeList {};
globalThis.document = {
  getElementById(id) {
    if (id === "debug-userswitch__filter") {
      return filter;
    }
    if (id === "debug-userswitch__set-identity") {
      return form;
    }
    if (id === "user_id") {
      return userId;
    }

    return null;
  },
};
globalThis.FormData = class FormData {
  forEach() {}
};
globalThis.window = {
  top: {
    location: {
      reload() {},
    },
  },
};
globalThis.XMLHttpRequest = function XMLHttpRequest() {
  this.readyState = 0;
  this.responseText = "Identity not found.";
  this.status = 0;
};
globalThis.XMLHttpRequest.prototype.open = function open() {};
globalThis.XMLHttpRequest.prototype.setRequestHeader = function () {};
globalThis.XMLHttpRequest.prototype.send = function send() {
  sends += 1;
  this.readyState = 4;
  this.status = 400;
  this.onreadystatechange();
};

await import("../src/panels/userswitch.js");

function event(target, key = "") {
  return {
    key,
    prevented: false,
    stopped: false,
    target,
    preventDefault() {
      this.prevented = true;
    },
    stopPropagation() {
      this.stopped = true;
    },
  };
}

test("delegated row activation supports nested clicks without hijacking controls", () => {
  var interactiveClick = event(link);
  var cellClick = event(cell);

  handlers.click(interactiveClick);

  assert.equal(sends, 0);
  assert.equal(interactiveClick.stopped, false);

  handlers.click(cellClick);

  assert.equal(userId.value, "42");
  assert.equal(sends, 1);
  assert.equal(cellClick.stopped, true);
});

test("focusable rows activate with Enter and Space only", () => {
  sends = 0;
  var childEnter = event(cell, "Enter");
  var rowEnter = event(row, "Enter");
  var rowSpace = event(row, " ");
  var rowArrow = event(row, "ArrowDown");

  handlers.keydown(childEnter);

  assert.equal(sends, 0);
  assert.equal(childEnter.prevented, false);

  handlers.keydown(rowEnter);
  handlers.keydown(rowSpace);
  handlers.keydown(rowArrow);

  assert.equal(sends, 2);
  assert.equal(rowEnter.prevented, true);
  assert.equal(rowEnter.stopped, true);
  assert.equal(rowSpace.prevented, true);
  assert.equal(rowSpace.stopped, true);
  assert.equal(rowArrow.prevented, false);
});

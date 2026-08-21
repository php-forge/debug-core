import assert from "node:assert/strict";
import test from "node:test";

class ClassList {
  constructor() {
    this.values = new Set();
  }

  add(...names) {
    names.forEach((name) => this.values.add(name));
  }

  contains(name) {
    return this.values.has(name);
  }

  remove(...names) {
    names.forEach((name) => this.values.delete(name));
  }
}

class Element {
  constructor() {
    this.attributes = new Map();
    this.classList = new ClassList();
    this.dataset = {};
    this.innerHTML = "";
    this.listeners = new Map();
    this.textContent = "";
  }

  addEventListener(event, handler) {
    this.listeners.set(event, handler);
  }

  dispatchEvent(event) {
    var handler = this.listeners.get(event.type);

    if (handler) {
      handler.call(this, event);
    }

    return true;
  }

  getAttribute(name) {
    return this.attributes.get(name) ?? null;
  }

  removeAttribute(name) {
    this.attributes.delete(name);
  }

  setAttribute(name, value) {
    this.attributes.set(name, value);
  }
}

class Event {
  constructor(type) {
    this.type = type;
  }

  preventDefault() {}
}

globalThis.MouseEvent = Event;
globalThis.NodeList = class NodeList extends Array {};

var containers = [];
var requests = [];
var targets = [];
var toggles = [];

for (var i = 0; i < 2; i++) {
  const container = new Element();
  const target = new Element();
  const toggle = new Element();

  container.querySelector = () => target;
  toggle.closest = () => container;
  toggle.href = `/debug/db-explain?seq=${i}`;

  containers.push(container);
  targets.push(target);
  toggles.push(toggle);
}

var explainAll = new Element();

globalThis.document = {
  querySelectorAll(selector) {
    if (selector === ".yii-debug-db-explain-toggle") {
      return new NodeList(...toggles);
    }

    if (
      selector ===
      ".yii-debug-db-explain-all-toggle, .yii-debug-db-explain-all a"
    ) {
      return new NodeList(explainAll);
    }

    if (
      selector ===
      ".yii-debug-db-explain.is-open, .yii-debug-db-explain.is-loading"
    ) {
      return new NodeList(
        ...containers.filter(
          (item) =>
            item.classList.contains("is-open") ||
            item.classList.contains("is-loading"),
        ),
      );
    }

    return new NodeList();
  },
};

globalThis.XMLHttpRequest = function XMLHttpRequest() {
  this.headers = {};
  requests.push(this);
};
globalThis.XMLHttpRequest.prototype.open = function open(method, url) {
  this.method = method;
  this.url = url;
};
globalThis.XMLHttpRequest.prototype.setRequestHeader = function setHeader(
  name,
  value,
) {
  this.headers[name] = value;
};
globalThis.XMLHttpRequest.prototype.send = function send() {};
globalThis.XMLHttpRequest.prototype.respond = function respond(
  status,
  responseText = "",
) {
  this.readyState = 4;
  this.responseText = responseText;
  this.status = status;
  this.onreadystatechange();
};

await import("../src/panels/db.js");

test("native explain-all control synchronizes batch state and loading semantics", () => {
  assert.equal(explainAll.textContent, "Explain all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "false");

  explainAll.dispatchEvent(new Event("click"));

  assert.equal(requests.length, 2);
  assert.equal(explainAll.textContent, "Collapse all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "true");
  assert.equal(targets[0].getAttribute("aria-busy"), "true");
  assert.equal(targets[1].getAttribute("aria-busy"), "true");

  explainAll.dispatchEvent(new Event("click"));

  assert.equal(explainAll.textContent, "Explain all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "false");

  requests[0].respond(200, "<table>first plan</table>");
  requests[1].respond(200, "<table>second plan</table>");

  containers.forEach((item) => {
    assert.equal(item.classList.contains("is-open"), false);
  });
  targets.forEach((item) => {
    assert.equal(item.dataset.loaded, "1");
    assert.equal(item.getAttribute("aria-busy"), null);
  });

  explainAll.dispatchEvent(new Event("click"));

  containers.forEach((item) => {
    assert.equal(item.classList.contains("is-open"), true);
  });
  assert.equal(explainAll.textContent, "Collapse all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "true");

  explainAll.dispatchEvent(new Event("click"));

  containers.forEach((item) => {
    assert.equal(item.classList.contains("is-open"), false);
  });
  assert.equal(explainAll.textContent, "Explain all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "false");
});

test("failed EXPLAIN request clears busy state and exposes a retryable alert", () => {
  delete targets[0].dataset.loaded;

  toggles[0].dispatchEvent(new Event("click"));

  assert.equal(requests.length, 3);
  assert.equal(targets[0].getAttribute("aria-busy"), "true");

  requests[2].respond(500);

  assert.equal(containers[0].classList.contains("is-loading"), false);
  assert.equal(targets[0].classList.contains("is-error"), true);
  assert.equal(targets[0].getAttribute("aria-busy"), null);
  assert.equal(targets[0].getAttribute("role"), "alert");
  assert.equal(
    targets[0].textContent,
    "Unable to load the EXPLAIN output. Try again.",
  );
  assert.equal(toggles[0].getAttribute("aria-expanded"), "false");
  assert.equal(explainAll.getAttribute("aria-expanded"), "false");
});

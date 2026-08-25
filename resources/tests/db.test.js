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

for (var i = 0; i < 5; i++) {
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
var docMarkerRow = new Element();
var docMarker = new Element();
var filterLinks = [new Element(), new Element()];
var clearLink = new Element();
var docStatus = new Element();
var anchorTarget = {
  scrollIntoView() {
    this.scrolled = true;
  },
};
var historyPushes = [];
var historyStub = {
  pushState(_state, _title, url) {
    historyPushes.push(url);
  },
};

docMarker.setAttribute("data-yii-debug-n1-group", "group-live");
docMarker.closest = () => docMarkerRow;
filterLinks[0].setAttribute("data-yii-debug-n1-filter", "group-live");

globalThis.window = {
  history: historyStub,
  location: { href: "https://example.test/debug?tab=db" },
};

globalThis.document = {
  getElementById(id) {
    return id === "group-live" ? anchorTarget : null;
  },
  querySelector(selector) {
    if (selector === "[data-yii-debug-n1-clear]") {
      return clearLink;
    }

    return selector === "[data-yii-debug-n1-status]" ? docStatus : null;
  },
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

    if (selector === "[data-yii-debug-n1-filter]") {
      return new NodeList(...filterLinks);
    }

    if (selector === "[data-yii-debug-n1-clear]") {
      return new NodeList(clearLink);
    }

    if (selector === "[data-yii-debug-n1-group]") {
      return new NodeList(docMarker);
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
globalThis.XMLHttpRequest.prototype.abort = function abort() {
  this.aborted = true;

  if (this.onabort) {
    this.onabort();
  }
};
globalThis.XMLHttpRequest.prototype.respond = function respond(
  status,
  responseText = "",
) {
  this.readyState = 4;
  this.responseText = responseText;
  this.status = status;
  this.onreadystatechange();
};

const { applyNPlusOneFilter } = await import("../src/panels/db.js");

test("N+1 group links filter query rows and expose live progress", () => {
  var rows = [new Element(), new Element(), new Element(), new Element()];
  var markers = rows.slice(0, 3).map((row, index) => {
    var marker = new Element();
    marker.setAttribute(
      "data-yii-debug-n1-group",
      index < 2 ? "group-a" : "group-b",
    );
    marker.closest = () => row;
    return marker;
  });
  var links = ["group-a", "group-b"].map((group) => {
    var link = new Element();
    link.setAttribute("data-yii-debug-n1-filter", group);
    return link;
  });
  var clear = new Element();
  var status = new Element();
  var root = {
    querySelector(selector) {
      if (selector === "[data-yii-debug-n1-clear]") return clear;
      if (selector === "[data-yii-debug-n1-status]") return status;
      return null;
    },
    querySelectorAll(selector) {
      if (selector === "[data-yii-debug-n1-group]") return markers;
      if (selector === "[data-yii-debug-n1-filter]") return links;
      if (selector === ".yii-debug-grid-db tbody tr") return rows;
      return [];
    },
  };

  assert.deepEqual(applyNPlusOneFilter(root, "group-a"), {
    activeGroup: "group-a",
    total: 4,
    visible: 2,
  });
  assert.equal(rows[0].hidden, false);
  assert.equal(rows[2].hidden, true);
  assert.equal(rows[3].hidden, true);
  assert.equal(links[0].getAttribute("aria-current"), "true");
  assert.equal(clear.hidden, false);
  assert.equal(status.textContent, "Showing 2 potential N+1 queries.");

  applyNPlusOneFilter(root, null);
  rows.forEach((row) => assert.equal(row.hidden, false));
  assert.equal(clear.hidden, true);
});

test("native explain-all control synchronizes batch state and loading semantics", () => {
  assert.equal(explainAll.textContent, "Explain all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "false");
  assert.equal(explainAll.getAttribute("aria-label"), "Explain all queries");

  explainAll.dispatchEvent(new Event("click"));

  assert.equal(requests.length, 3);
  assert.equal(explainAll.textContent, "Explaining 0/5");
  assert.equal(explainAll.getAttribute("aria-expanded"), "true");
  assert.equal(explainAll.getAttribute("aria-busy"), "true");
  targets.forEach((target) => {
    assert.equal(target.getAttribute("aria-busy"), "true");
  });

  toggles[0].dispatchEvent(new Event("click"));
  assert.equal(requests.length, 3);

  requests[0].respond(200, "<table>first plan</table>");
  assert.equal(requests.length, 4);
  assert.equal(explainAll.textContent, "Explaining 1/5");

  requests[1].respond(500);
  assert.equal(requests.length, 5);
  assert.equal(explainAll.textContent, "Explaining 2/5");

  requests[2].respond(200, "<table>third plan</table>");
  requests[3].respond(200, "<table>fourth plan</table>");
  requests[4].respond(200, "<table>fifth plan</table>");

  assert.equal(explainAll.textContent, "Collapse all");
  assert.equal(explainAll.getAttribute("aria-busy"), null);
  assert.equal(containers[0].classList.contains("is-open"), true);
  assert.equal(containers[1].classList.contains("is-open"), false);
  assert.equal(targets[1].getAttribute("role"), "alert");
  [0, 2, 3, 4].forEach((index) => {
    assert.equal(targets[index].dataset.loaded, "1");
  });

  explainAll.dispatchEvent(new Event("click"));

  containers.forEach((item) => {
    assert.equal(item.classList.contains("is-open"), false);
  });
  assert.equal(explainAll.textContent, "Explain all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "false");

  explainAll.dispatchEvent(new Event("click"));

  assert.equal(requests.length, 6);
  assert.equal(explainAll.textContent, "Explaining 4/5");

  requests[5].respond(200, "<table>retried plan</table>");

  targets.forEach((item) => {
    assert.equal(item.dataset.loaded, "1");
    assert.equal(item.getAttribute("aria-busy"), null);
  });
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

test("collapsing an EXPLAIN batch cancels active and queued requests", () => {
  targets.forEach((target) => {
    delete target.dataset.loaded;
  });

  var requestCount = requests.length;

  explainAll.dispatchEvent(new Event("click"));

  assert.equal(requests.length, requestCount + 3);
  assert.equal(explainAll.textContent, "Explaining 0/5");

  var activeRequests = requests.slice(requestCount);

  explainAll.dispatchEvent(new Event("click"));

  assert.equal(requests.length, requestCount + 3);
  activeRequests.forEach((request) => {
    assert.equal(request.aborted, true);
  });
  containers.forEach((container) => {
    assert.equal(container.classList.contains("is-loading"), false);
    assert.equal(container.classList.contains("is-open"), false);
  });
  targets.forEach((target) => {
    assert.equal(target.getAttribute("aria-busy"), null);
    assert.equal(target.dataset.loaded, undefined);
  });
  assert.equal(explainAll.textContent, "Explain all");
  assert.equal(explainAll.getAttribute("aria-busy"), null);
});

test("explain toggles reuse loaded output and explain-all short-circuits loaded plans", () => {
  var requestCount = requests.length;

  explainAll.dispatchEvent(new Event("click"));
  assert.equal(requests.length, requestCount + 3);
  requests.slice(requestCount, requestCount + 3).forEach((request) => {
    request.respond(200, "<table>plan</table>");
  });
  requests.slice(requestCount + 3).forEach((request) => {
    request.respond(200, "<table>plan</table>");
  });

  assert.equal(requests.length, requestCount + 5);
  assert.equal(explainAll.textContent, "Collapse all");

  toggles[0].dispatchEvent(new Event("click"));
  assert.equal(containers[0].classList.contains("is-open"), false);
  assert.equal(requests.length, requestCount + 5);

  toggles[0].dispatchEvent(new Event("click"));
  assert.equal(containers[0].classList.contains("is-open"), true);
  assert.equal(requests.length, requestCount + 5);

  explainAll.dispatchEvent(new Event("click"));
  containers.forEach((container) => {
    assert.equal(container.classList.contains("is-open"), false);
  });

  explainAll.dispatchEvent(new Event("click"));
  assert.equal(requests.length, requestCount + 5);
  assert.equal(explainAll.textContent, "Collapse all");
  containers.forEach((container) => {
    assert.equal(container.classList.contains("is-open"), true);
  });

  explainAll.dispatchEvent(new Event("click"));

  containers[4].querySelector = () => null;
  toggles[4].dispatchEvent(new Event("click"));
  assert.equal(requests.length, requestCount + 5);

  toggles[4].closest = () => null;
  toggles[4].dispatchEvent(new Event("click"));
  assert.equal(requests.length, requestCount + 5);
  toggles[4].closest = () => containers[4];

  explainAll.dispatchEvent(new Event("click"));
  assert.equal(containers[4].classList.contains("is-open"), false);
  assert.equal(containers[0].classList.contains("is-open"), true);
  assert.equal(explainAll.textContent, "Collapse all");

  containers[4].querySelector = () => targets[4];
  explainAll.dispatchEvent(new Event("click"));

  targets.forEach((target) => {
    delete target.dataset.loaded;
  });
});

test("N+1 filter links navigate, scroll, and clear through the document handlers", () => {
  historyPushes.length = 0;

  filterLinks[0].dispatchEvent(new Event("click"));

  assert.equal(historyPushes.length, 1);
  assert.match(historyPushes[0], /#group-live$/);
  assert.equal(anchorTarget.scrolled, true);
  assert.equal(docStatus.textContent, "Showing 1 potential N+1 queries.");
  assert.equal(clearLink.hidden, false);
  assert.equal(filterLinks[0].getAttribute("aria-current"), "true");

  clearLink.dispatchEvent(new Event("click"));
  assert.equal(docStatus.textContent, "Showing all database queries.");
  assert.equal(clearLink.hidden, true);

  filterLinks[1].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 1);

  delete anchorTarget.scrollIntoView;
  delete anchorTarget.scrolled;
  filterLinks[0].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 2);
  assert.equal(anchorTarget.scrolled, undefined);

  delete window.history;
  filterLinks[0].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 2);
  window.history = historyStub;
});

test("failed individual EXPLAIN request exposes a retryable alert", () => {
  var requestCount = requests.length;

  toggles[0].dispatchEvent(new Event("click"));

  assert.equal(requests.length, requestCount + 1);
  assert.equal(targets[0].getAttribute("aria-busy"), "true");

  requests[requestCount].respond(500);

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

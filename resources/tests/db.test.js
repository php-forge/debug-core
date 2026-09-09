import assert from "node:assert/strict";
import { test } from "vitest";

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
    window.location.href = url;
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

const { applyNPlusOneFilter, updateNPlusOneBanner } =
  await import("../src/panels/db.js");

function bannerFixture(existing = false, withSummary = false) {
  var pills = existing ? [new Element()] : [];
  var label = new Element();
  var clear = new Element();
  clear.setAttribute("href", "/debug?panel=db&sort=-duration");
  var list = { appendChild: (pill) => pills.push(pill) };
  var banner = new Element();
  var attached = existing;
  banner.querySelector = (selector) => {
    if (selector === ".yii-debug-active-filters-label") return label;
    if (selector === ".yii-debug-active-filters-list") return list;
    if (selector === ".yii-debug-active-filters-clear") return clear;
    return (
      pills.find((pill) => pill.getAttribute("data-yii-debug-n1-pill")) || null
    );
  };
  banner.querySelectorAll = () => pills;
  banner.remove = () => {
    attached = false;
  };
  var grid = {
    before: () => {
      attached = true;
    },
  };
  var filterLink = new Element();
  var root = {
    querySelector(selector) {
      if (selector === "[data-yii-debug-n1-filter]") return filterLink;
      if (selector === ".yii-debug-db-n1-summary")
        return withSummary ? grid : null;
      return selector === ".yii-debug-grid-db"
        ? grid
        : attached
          ? banner
          : null;
    },
    createElement(tag) {
      if (tag === "div") return banner;
      var pill = new Element();
      var value = new Element();
      pill.querySelector = () => value;
      pill.remove = () => {
        pills = pills.filter((item) => item !== pill);
      };
      return pill;
    },
  };
  filterLink.focus = () => {
    root.activeElement = filterLink;
  };
  return { root, banner, label, clear, filterLink };
}

test("N+1 creates the standard active-filter banner and clears it without navigation", () => {
  var { root, banner, label, clear } = bannerFixture();
  var cleared = 0;
  var clearFilter = () => {
    cleared += 1;
  };
  var inactive = { activeGroup: null, visible: 4 };
  updateNPlusOneBanner(root, inactive, clearFilter);
  assert.equal(root.querySelector(".yii-debug-active-filters"), null);
  updateNPlusOneBanner(
    { querySelector: () => null },
    { activeGroup: "a" },
    clearFilter,
  );

  updateNPlusOneBanner(root, { activeGroup: "a", visible: 3 }, clearFilter);
  assert.equal(root.querySelector(".yii-debug-active-filters"), banner);
  assert.equal(banner.getAttribute("aria-label"), "Active filters");
  assert.equal(label.textContent, "1 filter active");
  var pill = banner.querySelector("[data-yii-debug-n1-pill]");
  assert.equal(
    pill.getAttribute("aria-label"),
    "Remove N+1: 3 similar queries filter",
  );
  assert.equal(
    pill.querySelector(".yii-debug-active-filter-value").textContent,
    "3 similar queries",
  );

  updateNPlusOneBanner(root, { activeGroup: "b", visible: 5 }, clearFilter);
  assert.equal(
    banner.querySelectorAll(".yii-debug-active-filter-pill").length,
    1,
  );
  assert.equal(
    pill.querySelector(".yii-debug-active-filter-value").textContent,
    "5 similar queries",
  );
  pill.dispatchEvent(new Event("click"));
  clear.dispatchEvent(new Event("click"));
  assert.equal(cleared, 2);

  updateNPlusOneBanner(root, inactive, clearFilter);
  assert.equal(root.querySelector(".yii-debug-active-filters"), null);
});

test("N+1 inserts its banner before the summary instead of the grid", () => {
  var { root, banner } = bannerFixture(false, true);
  var querySelector = root.querySelector;
  root.querySelector = (selector) => {
    assert.notEqual(selector, ".yii-debug-grid-db");
    return querySelector(selector);
  };
  updateNPlusOneBanner(root, { activeGroup: "a", visible: 3 }, () => {});
  assert.equal(root.querySelector(".yii-debug-active-filters"), banner);
});

test("N+1 restores focus only when removing the focused control", () => {
  var { root, banner, clear, filterLink } = bannerFixture();
  var active = { activeGroup: "a", visible: 3 };
  var inactive = { activeGroup: null, visible: 4 };
  var clearFilter = () => {};

  updateNPlusOneBanner(root, active, clearFilter);
  root.activeElement = banner.querySelector("[data-yii-debug-n1-pill]");
  updateNPlusOneBanner(root, inactive, clearFilter);
  assert.equal(root.activeElement, filterLink);

  updateNPlusOneBanner(root, active, clearFilter);
  root.activeElement = clear;
  updateNPlusOneBanner(root, inactive, clearFilter);
  assert.equal(root.activeElement, filterLink);

  var existing = bannerFixture(true);
  updateNPlusOneBanner(existing.root, active, clearFilter);
  existing.root.activeElement = existing.clear;
  updateNPlusOneBanner(existing.root, inactive, clearFilter);
  assert.equal(existing.root.activeElement, existing.clear);

  updateNPlusOneBanner(existing.root, active, clearFilter);
  existing.root.activeElement = existing.banner.querySelector(
    "[data-yii-debug-n1-pill]",
  );
  updateNPlusOneBanner(existing.root, inactive, clearFilter);
  assert.equal(existing.root.activeElement, existing.filterLink);
});

test("N+1 shares the existing banner while preserving server filters and Clear all URL", () => {
  var { root, banner, label, clear } = bannerFixture(true);
  var inactive = { activeGroup: null, visible: 4 };
  updateNPlusOneBanner(root, inactive, () => {});
  assert.equal(label.textContent, "1 filter active");

  updateNPlusOneBanner(root, { activeGroup: "a", visible: 3 }, () => {});
  assert.equal(label.textContent, "2 filters active");
  assert.equal(clear.getAttribute("href"), "/debug?panel=db&sort=-duration");
  assert.equal(clear.listeners.size, 0);

  updateNPlusOneBanner(root, inactive, () => {});
  assert.equal(root.querySelector(".yii-debug-active-filters"), banner);
  assert.equal(
    banner.querySelectorAll(".yii-debug-active-filter-pill").length,
    1,
  );
  assert.equal(label.textContent, "1 filter active");
});

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

  markers[0].classList.add("yii-debug-deep-link-target");
  applyNPlusOneFilter(root, "group-b");
  assert.equal(
    markers[0].classList.contains("yii-debug-deep-link-target"),
    false,
  );

  markers[2].classList.add("yii-debug-deep-link-target");
  applyNPlusOneFilter(root, null);
  assert.equal(
    markers[2].classList.contains("yii-debug-deep-link-target"),
    false,
  );
  rows.forEach((row) => assert.equal(row.hidden, false));
  assert.equal(clear.hidden, true);
});

test("N+1 filtering tolerates orphan markers and missing optional controls", () => {
  var orphan = new Element();

  orphan.setAttribute("data-yii-debug-n1-group", "group-orphan");
  orphan.closest = () => null;

  var root = {
    querySelector() {
      return null;
    },
    querySelectorAll(selector) {
      return selector === "[data-yii-debug-n1-group]" ? [orphan] : [];
    },
  };

  assert.deepEqual(applyNPlusOneFilter(root, "group-orphan"), {
    activeGroup: "group-orphan",
    total: 0,
    visible: 0,
  });
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
  var originalShift = Array.prototype.shift;

  Array.prototype.shift = function () {
    var task = originalShift.call(this);

    if (task && task.toggle === toggles[0]) {
      task.cancelled = true;
    }

    return task;
  };

  try {
    explainAll.dispatchEvent(new Event("click"));
  } finally {
    Array.prototype.shift = originalShift;
  }

  assert.equal(requests.length, requestCount + 3);
  assert.equal(requests[requestCount].url, toggles[1].href);
  assert.equal(explainAll.textContent, "Explaining 0/5");

  var activeRequests = requests.slice(requestCount);

  toggles[4].closest = () => null;

  try {
    explainAll.dispatchEvent(new Event("click"));
  } finally {
    toggles[4].closest = () => containers[4];
  }

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

test("N+1 filter links navigate, toggle off, and clear through the document handlers", () => {
  historyPushes.length = 0;
  window.location.href = "https://example.test/debug?tab=db&sort=-duration";

  filterLinks[0].dispatchEvent(new Event("click"));

  assert.equal(historyPushes.length, 1);
  assert.match(historyPushes[0], /#group-live$/);
  assert.equal(anchorTarget.scrolled, true);
  assert.equal(docStatus.textContent, "Showing 1 potential N+1 queries.");
  assert.equal(clearLink.hidden, false);
  assert.equal(filterLinks[0].getAttribute("aria-current"), "true");

  docMarker.classList.add("yii-debug-deep-link-target");
  delete anchorTarget.scrolled;
  filterLinks[0].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 2);
  assert.equal(
    window.location.href,
    "https://example.test/debug?tab=db&sort=-duration",
  );
  assert.equal(anchorTarget.scrolled, undefined);
  assert.equal(filterLinks[0].getAttribute("aria-current"), null);
  assert.equal(
    docMarker.classList.contains("yii-debug-deep-link-target"),
    false,
  );
  assert.equal(docStatus.textContent, "Showing all database queries.");
  assert.equal(clearLink.hidden, true);

  filterLinks[0].dispatchEvent(new Event("click"));
  docMarker.classList.add("yii-debug-deep-link-target");
  clearLink.dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 4);
  assert.equal(new URL(window.location.href).hash, "");
  assert.equal(
    docMarker.classList.contains("yii-debug-deep-link-target"),
    false,
  );
  assert.equal(docStatus.textContent, "Showing all database queries.");
  assert.equal(clearLink.hidden, true);

  window.location.href += "#unrelated-section";
  filterLinks[1].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 4);
  assert.equal(new URL(window.location.href).hash, "#unrelated-section");

  delete anchorTarget.scrollIntoView;
  filterLinks[0].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 5);

  // Applying an already linked group does not add a duplicate history entry.
  filterLinks[0].removeAttribute("aria-current");
  filterLinks[0].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 5);

  delete window.history;
  filterLinks[0].dispatchEvent(new Event("click"));
  assert.equal(historyPushes.length, 5);
  window.history = historyStub;
});

test("an in-flight individual EXPLAIN keeps the batch control as a collapse action", () => {
  var requestCount = requests.length;

  toggles[0].dispatchEvent(new Event("click"));

  assert.equal(requests.length, requestCount + 1);
  assert.equal(explainAll.textContent, "Collapse all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "true");
  assert.equal(explainAll.getAttribute("aria-busy"), null);

  explainAll.dispatchEvent(new Event("click"));

  assert.equal(requests.length, requestCount + 1);
  assert.equal(requests[requestCount].aborted, true);
  assert.equal(containers[0].classList.contains("is-loading"), false);
  assert.equal(explainAll.textContent, "Explain all");
  assert.equal(explainAll.getAttribute("aria-expanded"), "false");
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

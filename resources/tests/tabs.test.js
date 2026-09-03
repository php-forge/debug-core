import assert from "node:assert/strict";
import { test } from "vitest";

import {
  activateTab,
  activateTabForFragment,
  initTabs,
  tabUrl,
} from "../src/core/tabs.js";

class ClassList {
  constructor(values = []) {
    this.values = new Set(values);
  }

  add(value) {
    this.values.add(value);
  }

  contains(value) {
    return this.values.has(value);
  }

  remove(value) {
    this.values.delete(value);
  }
}

function tabsFixture(tabCount = 2) {
  var rootListeners = new Map();
  var windowListeners = new Map();
  var pushes = [];
  var pushTitles = [];
  var indexes = Array.from({ length: tabCount }, (_, index) => index);
  var panes = indexes.map((index) => ({
    classList: new ClassList([
      "yii-debug-tab-panel",
      ...(index === 0 ? ["is-active"] : []),
    ]),
    hidden: index !== 0,
    id: "request-panel-" + index,
    parentElement: null,
  }));
  var content = { children: panes };
  panes.forEach((pane) => {
    pane.parentElement = content;
  });
  var attributes = indexes.map(
    (index) =>
      new Map([
        ["aria-controls", "request-panel-" + index],
        ["aria-selected", index === 0 ? "true" : "false"],
        ["href", "#request-panel-" + index],
        ["tabindex", index === 0 ? "0" : "-1"],
      ]),
  );
  var links = indexes.map((index) => ({
    nodeType: 1,
    classList: new ClassList([
      "yii-debug-tab-link",
      ...(index === 0 ? ["is-active"] : []),
    ]),
    closest(selector) {
      if (selector === ".yii-debug-tabs" || selector === '[role="tablist"]') {
        return list;
      }
      if (selector === '[data-yii-debug-toggle="tab"]') {
        return this;
      }
      return null;
    },
    focus() {
      this.focused = true;
    },
    getAttribute(name) {
      return attributes[index].get(name) ?? null;
    },
    setAttribute(name, value) {
      attributes[index].set(name, value);
    },
  }));
  var list = {
    querySelectorAll(selector) {
      return selector === '[data-yii-debug-toggle="tab"]' ? links : [];
    },
  };
  var byId = new Map(panes.map((pane) => [pane.id, pane]));
  var root = {
    addEventListener(name, listener) {
      rootListeners.set(name, listener);
    },
    getElementById(id) {
      return byId.get(id) || null;
    },
    querySelectorAll(selector) {
      return selector === '[data-yii-debug-toggle="tab"]' ? links : [];
    },
  };
  var location = {
    hash: "",
    href: "https://example.test/debug/request?tag=1&sort=-duration",
  };
  var windowValue = {
    addEventListener(name, listener) {
      windowListeners.set(name, listener);
    },
    history: {
      pushState(_state, title, url) {
        pushTitles.push(title);
        pushes.push(url);
        location.href = url;
        location.hash = new URL(url).hash;
      },
    },
    location,
  };

  return {
    attributes,
    links,
    panes,
    pushes,
    pushTitles,
    root,
    rootListeners,
    windowListeners,
    windowValue,
  };
}

test("activateTab synchronizes selected, focusable, and hidden state", () => {
  var page = tabsFixture();

  assert.equal(activateTab(page.links[1], page.root), true);
  assert.equal(page.attributes[0].get("aria-selected"), "false");
  assert.equal(page.attributes[0].get("tabindex"), "-1");
  assert.equal(page.links[0].classList.contains("is-active"), false);
  assert.equal(page.panes[0].classList.contains("is-active"), false);
  assert.equal(page.panes[0].hidden, true);
  assert.equal(page.attributes[1].get("aria-selected"), "true");
  assert.equal(page.attributes[1].get("tabindex"), "0");
  assert.equal(page.links[1].classList.contains("is-active"), true);
  assert.equal(page.panes[1].classList.contains("is-active"), true);
  assert.equal(page.panes[1].hidden, false);
});

test("tab fragments preserve query state and restore history selections", () => {
  var page = tabsFixture();

  assert.equal(
    tabUrl(page.windowValue.location.href, "request-panel-1"),
    "https://example.test/debug/request?tag=1&sort=-duration#request-panel-1",
  );

  page.windowValue.location.hash = "#request-panel-1";
  assert.equal(
    activateTabForFragment(page.root, page.windowValue.location),
    true,
  );
  assert.equal(page.panes[1].hidden, false);

  page.windowValue.location.hash = "#%E0%A4%A";
  assert.equal(
    activateTabForFragment(page.root, page.windowValue.location),
    false,
  );
});

test("tab keyboard navigation wraps with arrows and jumps with home or end", () => {
  var page = tabsFixture();

  initTabs(page.root, page.windowValue);

  var keydown = page.rootListeners.get("keydown");
  var prevented = 0;

  function press(key, target) {
    keydown({
      key: key,
      preventDefault() {
        prevented += 1;
      },
      target: target,
    });
  }

  var orphan = {
    closest(selector) {
      return selector === '[data-yii-debug-toggle="tab"]' ? orphan : null;
    },
    nodeType: 1,
  };

  press("a", page.links[0]);
  press("ArrowRight", { nodeType: 3, parentElement: null });
  press("ArrowRight", orphan);
  assert.equal(prevented, 0);

  press("ArrowRight", page.links[0]);
  assert.equal(page.links[1].focused, true);
  press("ArrowRight", page.links[1]);
  assert.equal(page.links[0].focused, true);
  press("ArrowLeft", page.links[0]);
  press("Home", page.links[1]);
  press("End", page.links[0]);
  assert.equal(prevented, 5);
  assert.equal(page.pushes.length, 5);

  delete page.links[1].focused;
  delete page.links[1].focus;
  press("End", page.links[0]);
  assert.equal(prevented, 6);
  assert.equal(page.links[1].focused, undefined);
});

test("controlled panels fall back to href fragments and reject unresolvable tabs", () => {
  var page = tabsFixture();

  page.attributes[1].delete("aria-controls");
  assert.equal(activateTab(page.links[1], page.root), true);

  page.attributes[1].set("href", "request-panel-1");
  assert.equal(activateTab(page.links[1], page.root), false);
  assert.equal(activateTab(page.links[0], {}), false);

  page.windowValue.location.hash = "#request-panel-1";
  assert.equal(
    activateTabForFragment(page.root, page.windowValue.location),
    false,
  );
});

test("clicks outside tabs and unresolvable selections leave history untouched", () => {
  var page = tabsFixture();

  initTabs(page.root, page.windowValue);

  var click = page.rootListeners.get("click");

  click({
    preventDefault() {},
    target: { nodeType: 3, parentElement: null },
  });

  var dead = {
    closest(selector) {
      return selector === '[data-yii-debug-toggle="tab"]' ? dead : null;
    },
    getAttribute() {
      return null;
    },
    nodeType: 1,
  };

  click({ preventDefault() {}, target: dead });
  assert.equal(page.pushes.length, 0);

  page.attributes[1].delete("aria-controls");
  click({ preventDefault() {}, target: page.links[1] });
  assert.equal(page.pushes.length, 0);
  assert.equal(page.panes[1].hidden, false);

  page.windowValue.location.hash = "";
  page.windowListeners.get("hashchange")();
});

test("tab selection without history support still activates panels", () => {
  var page = tabsFixture();

  delete page.windowValue.history;
  initTabs(page.root, page.windowValue);
  page.rootListeners.get("click")({
    preventDefault() {},
    target: page.links[1],
  });

  assert.equal(page.panes[1].hidden, false);
  assert.equal(page.pushes.length, 0);
});

test("tabs activate detached panels and resolve fragments nested inside panels", () => {
  var page = tabsFixture();
  var detachedPane = {
    classList: new ClassList(),
    hidden: true,
    id: "detached",
    parentElement: null,
  };
  var loner = {
    classList: new ClassList(),
    closest() {
      return null;
    },
    getAttribute(name) {
      return name === "aria-controls" ? "detached" : null;
    },
    nodeType: 1,
    setAttribute(name, value) {
      this[name] = value;
    },
  };
  var lonerRoot = {
    getElementById(id) {
      return id === "detached" ? detachedPane : null;
    },
  };

  assert.equal(activateTab(loner, lonerRoot), true);
  assert.equal(detachedPane.hidden, false);

  var inner = {
    classList: new ClassList(),
    closest(selector) {
      return selector === ".yii-debug-tab-panel" ? page.panes[1] : null;
    },
    nodeType: 1,
  };
  var nestedRoot = {
    getElementById(id) {
      return id === "inner" ? inner : page.root.getElementById(id);
    },
    querySelectorAll(selector) {
      return page.root.querySelectorAll(selector);
    },
  };

  assert.equal(activateTabForFragment(nestedRoot, { hash: "#inner" }), true);
  assert.equal(page.panes[1].hidden, false);
});

test("tab runtime pushes fragments and supports browser back or forward", () => {
  var page = tabsFixture();
  var prevented = false;

  initTabs(page.root, page.windowValue);
  page.rootListeners.get("click")({
    preventDefault() {
      prevented = true;
    },
    target: page.links[1],
  });

  assert.equal(prevented, true);
  assert.equal(page.pushes.length, 1);
  assert.match(page.pushes[0], /#request-panel-1$/);
  assert.equal(page.pushTitles[0], "");
  assert.equal(page.links[1].focused, undefined);
  assert.equal(page.panes[1].hidden, false);

  page.windowValue.location.hash = "#request-panel-0";
  page.windowListeners.get("popstate")();
  assert.equal(page.panes[0].hidden, false);
});

test("activating a tab leaves non-panel siblings visible", () => {
  var page = tabsFixture();
  var note = {
    classList: new ClassList(["yii-debug-tab-note"]),
    hidden: false,
  };

  page.panes[0].parentElement.children.push(note);

  assert.equal(activateTab(page.links[1], page.root), true);
  assert.equal(note.hidden, false);
  assert.equal(note.classList.contains("yii-debug-tab-note"), true);
});

test("clicks on text nodes inside a tab resolve to the owning tab", () => {
  var page = tabsFixture();

  initTabs(page.root, page.windowValue);
  page.rootListeners.get("click")({
    preventDefault() {},
    target: { nodeType: 3, parentElement: page.links[1] },
  });

  assert.equal(page.pushes.length, 1);
  assert.equal(page.panes[1].hidden, false);
});

test("click targets without closest support are ignored", () => {
  var page = tabsFixture();

  initTabs(page.root, page.windowValue);
  page.rootListeners.get("click")({
    preventDefault() {},
    target: { nodeType: 1 },
  });

  assert.equal(page.pushes.length, 0);
  assert.equal(page.panes[1].hidden, true);
});

test("non-fragment hrefs never resolve a controlled panel", () => {
  var hijacked = {
    classList: new ClassList(),
    hidden: true,
    id: "hijacked",
    parentElement: null,
  };
  var link = {
    classList: new ClassList(),
    closest() {
      return null;
    },
    getAttribute(name) {
      return name === "href" ? "xrequest-panel-0" : null;
    },
    nodeType: 1,
    setAttribute() {},
  };
  var root = {
    getElementById(id) {
      return id === "" ? null : hijacked;
    },
  };

  assert.equal(activateTab(link, root), false);
  assert.equal(hijacked.hidden, true);
});

test("missing href attributes fall back to an empty fragment id", () => {
  var link = {
    classList: new ClassList(),
    closest() {
      return null;
    },
    getAttribute() {
      return null;
    },
    nodeType: 1,
    setAttribute() {},
  };
  var seen = [];
  var charAt = String.prototype.charAt;
  var result;

  String.prototype.charAt = function (...args) {
    seen.push(String(this));
    return charAt.apply(this, args);
  };

  try {
    result = activateTab(link, {});
  } finally {
    String.prototype.charAt = charAt;
  }

  assert.equal(result, false);
  assert.deepEqual(seen, [""]);
});

test("detached panel activation never iterates fabricated siblings", () => {
  var detachedPane = {
    classList: new ClassList(),
    hidden: true,
    id: "detached",
    parentElement: null,
  };
  var loner = {
    classList: new ClassList(),
    closest() {
      return null;
    },
    getAttribute(name) {
      return name === "aria-controls" ? "detached" : null;
    },
    nodeType: 1,
    setAttribute() {},
  };
  var root = {
    getElementById(id) {
      return id === "detached" ? detachedPane : null;
    },
  };
  var result;

  Object.defineProperty(String.prototype, "classList", {
    configurable: true,
    get() {
      throw new Error("fabricated pane observed");
    },
  });

  try {
    result = activateTab(loner, root);
  } finally {
    delete String.prototype.classList;
  }

  assert.equal(result, true);
  assert.equal(detachedPane.hidden, false);
});

test("empty fragments never resolve panels even when the root would", () => {
  var page = tabsFixture();
  var root = {
    getElementById(id) {
      return id === "" ? page.panes[1] : page.root.getElementById(id);
    },
    querySelectorAll(selector) {
      return page.root.querySelectorAll(selector);
    },
  };

  assert.equal(activateTabForFragment(root, { hash: "" }), false);
  assert.equal(page.panes[1].hidden, true);
});

test("tabs pointing at missing panels never push history entries", () => {
  var page = tabsFixture();

  page.attributes[1].set("aria-controls", "missing-panel");
  initTabs(page.root, page.windowValue);
  page.rootListeners.get("click")({
    preventDefault() {},
    target: page.links[1],
  });

  assert.equal(page.pushes.length, 0);
  assert.equal(page.panes[1].hidden, true);
});

test("navigation keys on non-tab targets read the key exactly once", () => {
  var page = tabsFixture();
  var keyReads = 0;

  initTabs(page.root, page.windowValue);
  page.rootListeners.get("keydown")({
    get key() {
      keyReads += 1;
      return "ArrowRight";
    },
    preventDefault() {},
    target: { nodeType: 3, parentElement: null },
  });

  assert.equal(keyReads, 1);
});

test("arrow keys move in opposite directions across three tabs", () => {
  var page = tabsFixture(3);

  initTabs(page.root, page.windowValue);

  var keydown = page.rootListeners.get("keydown");

  keydown({ key: "ArrowRight", preventDefault() {}, target: page.links[0] });
  assert.equal(page.links[1].focused, true);
  assert.equal(page.links[2].focused, undefined);

  keydown({ key: "ArrowLeft", preventDefault() {}, target: page.links[0] });
  assert.equal(page.links[2].focused, true);
});

test("tabs restore the fragment selection during initialization", () => {
  var page = tabsFixture();

  page.windowValue.location.hash = "#request-panel-1";
  initTabs(page.root, page.windowValue);

  assert.equal(page.panes[1].hidden, false);
  assert.equal(page.panes[0].hidden, true);
});

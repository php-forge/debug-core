import assert from "node:assert/strict";
import test from "node:test";

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

function tabsFixture() {
  var rootListeners = new Map();
  var windowListeners = new Map();
  var pushes = [];
  var panes = [0, 1].map((index) => ({
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
  var attributes = [0, 1].map(
    (index) =>
      new Map([
        ["aria-controls", "request-panel-" + index],
        ["aria-selected", index === 0 ? "true" : "false"],
        ["href", "#request-panel-" + index],
        ["tabindex", index === 0 ? "0" : "-1"],
      ]),
  );
  var links = [0, 1].map((index) => ({
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
    querySelectorAll() {
      return links;
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
    querySelectorAll() {
      return links;
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
      pushState(_state, _title, url) {
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
  assert.equal(page.panes[0].hidden, true);
  assert.equal(page.attributes[1].get("aria-selected"), "true");
  assert.equal(page.attributes[1].get("tabindex"), "0");
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
  assert.equal(page.panes[1].hidden, false);

  page.windowValue.location.hash = "#request-panel-0";
  page.windowListeners.get("popstate")();
  assert.equal(page.panes[0].hidden, false);
});

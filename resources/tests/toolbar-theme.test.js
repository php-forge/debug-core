import assert from "node:assert/strict";
import { test } from "vitest";

globalThis.window = {
  location: new URL("https://example.test/"),
  localStorage: {
    getItem() {
      return null;
    },
    setItem() {},
  },
};
globalThis.localStorage = window.localStorage;
globalThis.document = { cookie: "" };
globalThis.XMLHttpRequest = function XMLHttpRequest() {};
globalThis.XMLHttpRequest.prototype.open = function open() {};

const {
  addThemeToUrl,
  delegateThemeToHost,
  getComputedTheme,
  getElementTheme,
  getHostThemeControl,
  getStorageTheme,
  hostHasThemeControl,
  normalizeTheme,
  resetHostThemeControlCache,
  writeThemeCookie,
} = await import("../src/toolbar/theme.js");
const { readStorageItem, writeStorageItem } =
  await import("../src/toolbar/state.js");

test("storage helpers tolerate denied localStorage access", () => {
  var storage = window.localStorage;

  try {
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      get() {
        throw new Error("Storage access denied.");
      },
    });

    assert.equal(readStorageItem("yii-debug-toolbar-expanded"), null);
    assert.equal(writeStorageItem("yii-debug-toolbar-expanded", "1"), false);
    assert.equal(getStorageTheme(), null);
  } finally {
    Object.defineProperty(window, "localStorage", {
      configurable: true,
      value: storage,
      writable: true,
    });
  }
});

test("normalizeTheme accepts explicit aliases", () => {
  assert.equal(normalizeTheme("night"), "dark");
  assert.equal(normalizeTheme("day"), "light");
  assert.equal(normalizeTheme("dark light"), null);
});

test("addThemeToUrl stamps same-origin debug links", () => {
  assert.equal(
    addThemeToUrl("/debug/view?tag=1", "dark"),
    "https://example.test/debug/view?tag=1&yii_debug_theme=dark",
  );
  assert.equal(
    addThemeToUrl("https://other.test/debug/view?tag=1", "dark"),
    null,
  );
  assert.equal(addThemeToUrl("javascript:alert(1)", "dark"), null);
});

test("writeThemeCookie persists the shared toolbar preference", () => {
  writeThemeCookie("dark");

  assert.match(document.cookie, /^yii-debug-toolbar-theme=dark;/);
});

test("host theme control candidates exclude ordinary links", () => {
  var selector;

  document.querySelectorAll = function (value) {
    selector = value;

    return [];
  };

  resetHostThemeControlCache();

  assert.equal(hostHasThemeControl(), false);
  assert.equal(
    selector,
    'button, [role="switch"], [role="button"], [data-theme-toggle], [data-bs-theme-toggle]',
  );
});

test("host theme control detection stays cached until reset", () => {
  var sweeps = 0;
  var nodes = [];
  var themeControl = {
    clicks: 0,
    dataset: {},
    click() {
      this.clicks += 1;
    },
    getAttribute(name) {
      return name === "aria-label" ? "Toggle dark mode" : null;
    },
    getClientRects() {
      return [];
    },
    offsetParent: {},
    textContent: "",
  };

  document.querySelectorAll = function () {
    sweeps += 1;

    return nodes;
  };

  resetHostThemeControlCache();

  assert.equal(hostHasThemeControl(), false);
  assert.equal(hostHasThemeControl(), false);
  assert.equal(sweeps, 1);
  assert.equal(delegateThemeToHost("dark", "light"), false);

  nodes = [themeControl];

  assert.equal(hostHasThemeControl(), false);
  assert.equal(sweeps, 1);

  resetHostThemeControlCache();

  assert.equal(hostHasThemeControl(), true);
  assert.equal(getHostThemeControl(), themeControl);
  assert.equal(hostHasThemeControl(), true);
  assert.equal(sweeps, 2);

  assert.equal(delegateThemeToHost("dark", "light"), true);
  assert.equal(themeControl.clicks, 1);

  assert.equal(delegateThemeToHost("dark", "dark"), true);
  assert.equal(themeControl.clicks, 1);
});

test("element and computed theme detection walk attributes, classes, and styles", () => {
  var attrElement = {
    getAttribute(name) {
      return name === "data-theme" ? "dark" : null;
    },
  };
  var classElement = {
    className: "app shell dark",
    getAttribute() {
      return null;
    },
  };
  var objectClassElement = {
    className: {},
    getAttribute() {
      return null;
    },
  };

  assert.equal(getElementTheme(null), null);
  assert.equal(getElementTheme(attrElement), "dark");
  assert.equal(getElementTheme(classElement), "dark");
  assert.equal(getElementTheme(objectClassElement), null);

  delete window.getComputedStyle;
  assert.equal(getComputedTheme(), null);

  document.documentElement = { id: "root" };
  document.body = null;
  globalThis.getComputedStyle = function () {
    return { colorScheme: "" };
  };
  window.getComputedStyle = globalThis.getComputedStyle;
  assert.equal(getComputedTheme(), null);

  document.body = { id: "body" };
  globalThis.getComputedStyle = function (element) {
    return { colorScheme: element.id === "body" ? "dark" : "" };
  };
  window.getComputedStyle = globalThis.getComputedStyle;
  assert.equal(getComputedTheme(), "dark");

  globalThis.getComputedStyle = function () {
    return { colorScheme: "light" };
  };
  window.getComputedStyle = globalThis.getComputedStyle;
  assert.equal(getComputedTheme(), "light");
});

test("storage theme resolution returns the first recognizable key", () => {
  var storage = window.localStorage;

  window.localStorage = {
    getItem(key) {
      return key === "vite-ui-theme" ? "dark" : null;
    },
  };
  globalThis.localStorage = window.localStorage;

  assert.equal(getStorageTheme(), "dark");

  window.localStorage = storage;
  globalThis.localStorage = storage;
});

test("host control sweeps skip unrelated or invisible candidates and drop disconnected caches", () => {
  var unrelated = {
    dataset: {},
    getAttribute() {
      return null;
    },
    getClientRects() {
      return [];
    },
    offsetParent: {},
    textContent: "Save changes",
  };
  var invisible = {
    dataset: { themeToggle: "1" },
    getAttribute() {
      return null;
    },
    getClientRects() {
      return [];
    },
    offsetParent: null,
    textContent: "",
  };
  var rectsOnly = {
    dataset: {},
    getAttribute(name) {
      return name === "title" ? "Theme" : null;
    },
    getClientRects() {
      return [{}];
    },
    isConnected: true,
    offsetParent: null,
    textContent: "",
  };

  document.querySelectorAll = function () {
    return [unrelated, invisible, rectsOnly];
  };

  resetHostThemeControlCache();
  assert.equal(getHostThemeControl(), rectsOnly);

  rectsOnly.isConnected = false;
  document.querySelectorAll = function () {
    return [];
  };
  assert.equal(getHostThemeControl(), null);

  var clickless = {
    dataset: {},
    getAttribute(name) {
      return name === "aria-label" ? "theme" : null;
    },
    getClientRects() {
      return [{}];
    },
    offsetParent: null,
    textContent: "",
  };

  document.querySelectorAll = function () {
    return [clickless];
  };
  resetHostThemeControlCache();
  assert.equal(delegateThemeToHost("dark", "light"), false);
});

test("theme cookie writes tolerate missing themes and blocked cookies", () => {
  var originalDocument = globalThis.document;

  assert.equal(writeThemeCookie(null), undefined);

  globalThis.document = {
    get cookie() {
      return "";
    },
    set cookie(_value) {
      throw new Error("blocked");
    },
  };
  assert.equal(writeThemeCookie("dark"), undefined);
  globalThis.document = originalDocument;

  assert.equal(addThemeToUrl("/debug/view", null), "/debug/view");
});
